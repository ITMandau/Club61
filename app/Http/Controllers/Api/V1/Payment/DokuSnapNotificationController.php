<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use App\Models\Payment\DokuNotification;
use App\Models\Pos\Payment;
use App\Services\Payment\Doku\DokuClient;
use App\Services\Payment\Doku\DokuNotificationProcessor;
use App\Services\Payment\Doku\DokuSessionGuard;
use App\Services\Payment\Doku\Snap\DokuSnapClient;
use App\Services\Payment\Doku\Snap\DokuSnapService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

/**
 * Permintaan dari DOKU SNAP ke kita (Modul 27b). Alamatnya didaftarkan di dashboard DOKU:
 *   - token     : Integration > API Keys           → POST /api/v1/payments/doku-snap/v1.0/access-token/b2b
 *   - VA        : Virtual Account SNAP > Configure  → POST /api/v1/payments/doku-snap/v1.1/transfer-va/payment
 *   - QRIS      : QRIS Notify URL                   → POST /api/v1/payments/doku-snap/v1.0/qr/qr-mpm-notify
 *
 * Token: tanda tangan RSA DOKU dicek dengan public key DOKU, dibalas JWT kita. Notifikasi wajib membawa JWT itu.
 * Seperti DOKU Checkout, notifikasi TIDAK langsung melunasi: disimpan di doku_notifications lalu
 * DokuNotificationProcessor mengonfirmasi ke API status SNAP (DokuCheckoutService::state → DokuSnapService) dan
 * mencocokkan nominal sebelum melunasi. Balasan 200 dikirim segera.
 */
class DokuSnapNotificationController extends Controller
{
    public function __construct(protected DokuSnapClient $client) {}

    public function token(Request $request): JsonResponse
    {
        $this->debugInbound('token', $request);
        $timestamp = DokuSnapClient::timestamp();
        $valid = $this->client->verifyInboundTokenRequest(
            (string) $request->header('X-CLIENT-KEY', ''),
            (string) $request->header('X-TIMESTAMP', ''),
            (string) $request->header('X-SIGNATURE', ''),
        );
        $token = $valid ? $this->client->issueInboundToken() : null;

        if (! $token) {
            DokuClient::alert('DOKU SNAP: permintaan token DITOLAK (tanda tangan / client key / jam tidak cocok)', ['ip' => $request->ip()]);

            return response()->json(['responseCode' => '4017300', 'responseMessage' => 'Unauthorized. Invalid Signature'], 401)
                ->header('X-TIMESTAMP', $timestamp);
        }

        return response()->json([
            'responseCode' => '2007300',
            'responseMessage' => 'Successful',
            'accessToken' => $token,
            'tokenType' => 'Bearer',
            'expiresIn' => DokuSnapClient::INBOUND_TOKEN_TTL,
            'additionalInfo' => '',
        ])->header('X-CLIENT-KEY', $this->client->clientId())->header('X-TIMESTAMP', $timestamp);
    }

    public function virtualAccountPayment(Request $request): JsonResponse
    {
        $this->debugInbound('va', $request);
        if (! $this->client->verifyInboundToken($request->header('Authorization'))) {
            DokuClient::alert('Notifikasi VA DOKU SNAP DITOLAK: token tidak sah', ['ip' => $request->ip()]);

            return response()->json(['responseCode' => '4012501', 'responseMessage' => 'Unauthorized. Invalid Token (B2B)'], 401);
        }

        $data = $request->json()->all();
        $invoice = is_string($data['trxId'] ?? null) && $data['trxId'] !== '' ? $data['trxId'] : $this->invoiceForVa((string) ($data['virtualAccountNo'] ?? ''));
        $this->store('VA', $request, $data, $invoice, 'SUCCESS', $data['paidAmount']['value'] ?? null);

        return response()->json([
            'responseCode' => '2002500',
            'responseMessage' => 'Successful',
            'virtualAccountData' => array_filter([
                'partnerServiceId' => $data['partnerServiceId'] ?? null,
                'customerNo' => $data['customerNo'] ?? null,
                'virtualAccountNo' => $data['virtualAccountNo'] ?? null,
                'virtualAccountName' => $data['virtualAccountName'] ?? null,
                'paymentRequestId' => $data['paymentRequestId'] ?? null,
            ], fn ($v) => $v !== null),
        ]);
    }

    public function qrisNotify(Request $request): JsonResponse
    {
        $this->debugInbound('qris', $request);
        if (! $this->client->verifyInboundToken($request->header('Authorization'))) {
            DokuClient::alert('Notifikasi QRIS DOKU SNAP DITOLAK: token tidak sah', ['ip' => $request->ip()]);

            return response()->json(['responseCode' => '4015201', 'responseMessage' => 'Unauthorized. Invalid Token (B2B)'], 401);
        }

        $data = $request->json()->all();
        $status = (string) ($data['latestTransactionStatus'] ?? '');
        $this->store('QRIS', $request, $data, $data['originalPartnerReferenceNo'] ?? null, $status === '00' ? 'SUCCESS' : 'QRIS_'.$status, $data['amount']['value'] ?? null);

        return response()->json(['responseCode' => '2005200', 'responseMessage' => 'Successful']);
    }

    /** Simpan sekali per notifikasi lalu proses setelah respons (sama dengan DokuNotificationController). */
    private function store(string $kind, Request $request, array $data, mixed $invoice, string $status, mixed $amount): void
    {
        $externalId = trim((string) $request->header('X-EXTERNAL-ID', ''));
        $requestId = mb_substr('SNAP-'.$kind.'-'.($externalId !== '' ? $externalId : sha1($request->getContent())), 0, 128);

        try {
            $notification = DokuNotification::firstOrCreate(['request_id' => $requestId], [
                'invoice_number' => is_string($invoice) ? mb_substr($invoice, 0, 64) : null,
                'status' => mb_substr($status, 0, 20),
                'amount' => is_numeric($amount) ? (float) $amount : null,
                // Data pribadi customer (nama, email, telepon) tidak disimpan.
                'payload' => Arr::except($data, ['virtualAccountName', 'virtualAccountEmail', 'virtualAccountPhone', 'customerName', 'customerEmail', 'customerPhone']),
                'received_at' => now(),
            ]);
        } catch (QueryException $e) {
            $notification = DokuNotification::where('request_id', $requestId)->firstOrFail();
        }

        if (! DokuNotification::isFinal($notification->result)) {
            $id = $notification->id;
            dispatch(fn () => app(DokuNotificationProcessor::class)->process($id))->afterResponse();
        }
    }

    /**
     * Diagnosa SEMENTARA (DOKU_SNAP_DEBUG_INBOUND=true, bawaan mati): bentuk permintaan DOKU ke kita ditulis ke log doku
     * SEBELUM diverifikasi — untuk melacak kenapa DOKU tidak meminta token B2B. Hasil verifikasi tidak berubah.
     * Token, tanda tangan, client key & isi body (data customer) TIDAK ditulis: hanya nama header/field, panjang, cocok/tidak.
     */
    private function debugInbound(string $endpoint, Request $request): void
    {
        if (! config('services.doku.snap.debug_inbound')) {
            return;
        }

        rescue(function () use ($endpoint, $request) {
            // Skema (mis. "Bearer") hanya diambil kalau berupa kata pendek, supaya token tanpa skema tidak ikut tertulis.
            $authorization = trim((string) $request->header('Authorization', ''));
            $scheme = preg_match('/^([A-Za-z]{1,12})\s+(.*)$/s', $authorization, $m) ? $m[1] : null;
            $credential = $scheme !== null ? trim($m[2]) : $authorization;
            $jwt = explode('.', $credential);
            $jwtHeader = count($jwt) === 3 ? json_decode((string) base64_decode(strtr($jwt[0], '-_', '+/'), true), true) : null;

            // Panjang tanda tangan setelah base64 menunjukkan jenisnya: 256/512 byte = RSA (asimetris), 64 byte = HMAC-SHA512.
            $signature = (string) $request->header('X-SIGNATURE', '');
            $signatureRaw = $signature === '' ? false : base64_decode($signature, true);
            $signatureBytes = $signatureRaw === false ? null : strlen($signatureRaw);

            $timestamp = (string) $request->header('X-TIMESTAMP', '');
            try {
                $skew = $timestamp === '' ? null : (int) now()->diffInSeconds(\Illuminate\Support\Carbon::parse($timestamp), false);
            } catch (\Throwable) {
                $skew = 'tidak terbaca';
            }

            $clientId = $this->client->clientId();
            $matchesClientId = function (string $header) use ($request, $clientId): ?bool {
                $value = (string) $request->header($header, '');

                return $value === '' ? null : ($clientId !== '' && hash_equals($clientId, $value));
            };

            $body = json_decode($request->getContent(), true);

            Log::channel('doku')->info("DOKU SNAP masuk [{$endpoint}] (diagnosa)", [
                'ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 120),
                'headers' => array_values(array_diff(array_keys($request->headers->all()), ['cookie'])),
                'authorization' => $authorization === '' ? null : [
                    'scheme' => $scheme,
                    'length' => strlen($credential),
                    'jwt_alg' => is_array($jwtHeader) ? mb_substr((string) ($jwtHeader['alg'] ?? ''), 0, 10) : null,
                ],
                'x_signature' => $signature === '' ? null : [
                    'length' => strlen($signature),
                    'decoded_bytes' => $signatureBytes,
                    'kind' => match (true) {
                        in_array($signatureBytes, [256, 384, 512], true) => 'asimetris (RSA)',
                        $signatureBytes === 64 => 'simetris (HMAC-SHA512)',
                        default => 'lain',
                    },
                ],
                'x_timestamp' => $timestamp === '' ? null : mb_substr($timestamp, 0, 40),
                'clock_skew_seconds' => $skew,
                'x_client_key_matches' => $matchesClientId('X-CLIENT-KEY'),
                'x_partner_id_matches' => $matchesClientId('X-PARTNER-ID'),
                'x_external_id' => mb_substr((string) $request->header('X-EXTERNAL-ID', ''), 0, 64) ?: null,
                'channel_id' => mb_substr((string) $request->header('CHANNEL-ID', ''), 0, 20) ?: null,
                'content_type' => mb_substr((string) $request->header('Content-Type', ''), 0, 60) ?: null,
                'body_bytes' => strlen($request->getContent()),
                'body_fields' => is_array($body) ? array_slice(array_keys($body), 0, 40) : 'bukan JSON',
                'additional_info_fields' => is_array($body['additionalInfo'] ?? null) ? array_slice(array_keys($body['additionalInfo']), 0, 40) : null,
            ]);
        }, null, false);
    }

    /** Notifikasi VA tanpa trxId: cari sesi SNAP dengan nomor VA itu (dicatat saat VA dibuat). */
    private function invoiceForVa(string $virtualAccountNo): ?string
    {
        $digits = preg_replace('/\D/', '', $virtualAccountNo);
        if ($digits === '') {
            return null;
        }

        if ($invoice = DokuSnapService::invoiceForVaNumber($digits)) {
            return $invoice;
        }

        // Cadangan untuk VA yang dibuat sebelum pencatatan nomor VA ada: tagihan DOKU 7 hari terakhir.
        foreach (Payment::where('payment_gateway', 'DOKU')->where('created_at', '>=', now()->subDays(7))->latest()->get(['id', 'order_id', 'transaction_id', 'payload_log', 'created_at']) as $bill) {
            foreach (DokuSessionGuard::sessionsOf($bill) as $invoice) {
                $session = DokuSessionGuard::session($bill, $invoice);
                if (DokuSnapService::isSnapSession($session) && preg_replace('/\D/', '', (string) ($session['va_number'] ?? '')) === $digits) {
                    return $invoice;
                }
            }
        }

        return null;
    }
}
