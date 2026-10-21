<?php

namespace App\Services\Payment\Doku\Snap;

use App\Exceptions\PaymentGatewayUnavailableException;
use App\Models\Pos\Payment;
use App\Services\Payment\Doku\DokuCheckoutService;
use App\Services\Payment\Doku\DokuClient;
use App\Services\Payment\Doku\DokuSessionGuard;
use App\Services\Payment\GatewayCustomer;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Modul 27b — QRIS (MPM) & VA BCA lewat DOKU SNAP. Halaman bayar dibuat sendiri (teks QR / nomor VA), jadi tidak ada
 * halaman DOKU yang dibuka customer.
 *
 * Sesi SNAP dicatat di format DokuSessionGuard (doku_sessions[invoice] + 'api' => 'SNAP'), invoice = partnerReferenceNo
 * (QRIS) / trxId (VA). DokuCheckoutService::state()/close()/cancel() meneruskan sesi SNAP ke kelas ini, jadi webhook,
 * rekonsiliasi, ganti metode, pembayaran ganda, kasir & pembersih memakai jalur DOKU yang sudah ada tanpa diubah.
 *
 * state() mengembalikan bentuk data yang sama dengan API status DOKU Checkout (order.amount, transaction.status, ...)
 * supaya DokuReconciliationService::settle()/amountMatches()/summary() tetap berlaku.
 */
class DokuSnapService
{
    public const API = 'SNAP';

    /** Header CHANNEL-ID semua request SNAP QRIS & VA (dokumentasi resmi: "Please fill with H2H (Host-To-Host)"). */
    public const CHANNEL_ID = 'H2H';

    public const QRIS = 'QRIS';

    public const VA = 'VA';

    /** Kanal DOKU (nama di OnlinePaymentCatalog::DOKU_CHANNELS) yang sudah lewat SNAP. */
    public const SUPPORTED = ['QRIS' => self::QRIS, 'VIRTUAL_ACCOUNT_BCA' => self::VA];

    public const QRIS_GENERATE_PATH = '/snap-adapter/b2b/v1.0/qr/qr-mpm-generate';

    public const QRIS_QUERY_PATH = '/snap-adapter/b2b/v1.0/qr/qr-mpm-query';

    public const QRIS_EXPIRE_PATH = '/snap-adapter/b2b/v1.0/qr/qr-expire';

    public const VA_CREATE_PATH = '/virtual-accounts/bi-snap-va/v1.1/transfer-va/create-va';

    public const VA_DELETE_PATH = '/virtual-accounts/bi-snap-va/v1.1/transfer-va/delete-va';

    public const VA_STATUS_PATH = '/orders/v1.0/transfer-va/status';

    /** Nama bank untuk kanal VA (tampilan & struk). */
    public const BANKS = ['VIRTUAL_ACCOUNT_BCA' => 'BCA'];

    public function __construct(protected DokuSnapClient $client) {}

    /** SNAP bisa dipakai untuk kanal ini (aktif, kunci lengkap, data akun kanalnya terisi)? Null = pakai DOKU Checkout. */
    public function productFor(array $channels): ?string
    {
        $channels = array_values($channels);
        if (count($channels) !== 1 || ! isset(self::SUPPORTED[$channels[0]]) || ! $this->client->isEnabled() || ! $this->client->isConfigured()) {
            return null;
        }

        $product = self::SUPPORTED[$channels[0]];
        $invalid = self::invalidAccountData($product);
        if ($invalid !== []) {
            // Tanpa ini SNAP diam-diam jatuh ke DOKU Checkout dan tidak ada yang tahu sebabnya (dicatat maks. 1x/10 menit).
            if (Cache::add('doku_snap_invalid_'.$product, true, now()->addMinutes(10))) {
                Log::channel('doku')->warning("[ALERT] DOKU SNAP {$product} tidak dipakai (pakai DOKU Checkout): isian .env kosong / format salah", ['isian' => $invalid]);
            }

            return null;
        }

        return $product;
    }

    /**
     * Data akun SNAP per produk yang kosong / formatnya ditolak DOKU (dokumentasi resmi SNAP QRIS & VA BCA DGPC).
     *
     * @return array<int, string> nama variabel .env yang bermasalah
     */
    public static function invalidAccountData(string $product): array
    {
        $rules = $product === self::QRIS
            ? [
                'DOKU_SNAP_QRIS_MERCHANT_ID' => ['qris_merchant_id', '/^[A-Za-z0-9_-]{1,64}$/'],
                'DOKU_SNAP_QRIS_TERMINAL_ID' => ['qris_terminal_id', '/^[A-Za-z0-9]{3,16}$/'],
                'DOKU_SNAP_QRIS_POSTAL_CODE' => ['qris_postal_code', '/^\d{5}$/'],
            ]
            : [
                'DOKU_SNAP_BCA_PARTNER_SERVICE_ID' => ['bca_partner_service_id', '/^\d{1,8}$/'],
                'DOKU_SNAP_BCA_CUSTOMER_NO' => ['bca_customer_no', '/^\d{1,20}$/'],
            ];

        return array_keys(array_filter($rules, fn (array $rule) => ! preg_match($rule[1], trim((string) config('services.doku.snap.'.$rule[0])))));
    }

    public static function isSnapSession(?array $session): bool
    {
        return is_array($session) && ($session['api'] ?? null) === self::API;
    }

    /**
     * Bikin tagihan SNAP. Fail-closed: gagal = exception (transaksi checkout di-rollback). Kanal ditolak DOKU → 422
     * (customer pilih metode lain); kunci / server DOKU bermasalah → 503.
     *
     * @return array{api: string, product: string, channel: string, request_id: string, expires_at: string, reference_no?: ?string, qr_content?: string, partner_service_id?: string, customer_no?: string, va_number?: string, bank?: string}
     */
    public function create(string $invoice, int $amount, array $channels, array $customer, int $dueMinutes): array
    {
        $product = $this->productFor($channels);
        $channel = array_values($channels)[0] ?? '';
        if ($product === null || $amount <= 0 || ! preg_match('/^[A-Za-z0-9-]{1,64}$/', $invoice)) {
            throw new \InvalidArgumentException("Data tagihan DOKU SNAP tidak valid [{$invoice}].");
        }

        $expiresAt = now()->addMinutes(max(1, $dueMinutes));

        return $product === self::QRIS
            ? $this->createQris($invoice, $amount, $expiresAt)
            : $this->createVa($invoice, $amount, $channel, $customer, $expiresAt);
    }

    /**
     * Status satu sesi SNAP, bentuknya sama dengan DokuCheckoutService::state().
     *
     * @return array{state: string, data: ?array}
     */
    public function state(string $invoice, array $session): array
    {
        return ($session['product'] ?? null) === self::QRIS
            ? $this->qrisState($invoice, $session)
            : $this->vaState($invoice, $session);
    }

    /**
     * Pastikan sesi tidak bisa dibayar lagi (QR di-expire / VA dihapus) lalu cek ulang statusnya.
     *
     * @return array{result: string, data: ?array} CLOSED / NOT_FOUND / PAID / FAILED
     */
    public function close(string $invoice, array $session): array
    {
        $before = $this->state($invoice, $session);
        if ($early = self::closeResult($before['state'])) {
            return ['result' => $early, 'data' => $before['data']];
        }

        $cancelled = $this->cancel($invoice, $session);
        $after = $this->state($invoice, $session);
        $result = self::closeResult($after['state']);

        // QR yang di-expire / VA yang dihapus tidak bisa dibayar lagi walau query status belum menyebutnya batal —
        // asal DOKU menerima pembatalannya dan statusnya bukan lunas.
        if ($result === null && $cancelled && $after['state'] === DokuCheckoutService::PENDING) {
            $result = DokuCheckoutService::CLOSED;
        }
        if ($result !== null) {
            return ['result' => $result, 'data' => $after['data']];
        }

        DokuClient::alert("Sesi DOKU SNAP [{$invoice}] belum bisa ditutup (status {$after['state']}).");

        return ['result' => 'FAILED', 'data' => $after['data']];
    }

    /** Batalkan tanpa menunggu bukti (best-effort). true = DOKU menerima pembatalannya. */
    public function cancel(string $invoice, array $session): bool
    {
        if (($session['product'] ?? null) === self::QRIS) {
            $response = $this->client->request('POST', self::QRIS_EXPIRE_PATH, [
                'partnerReferenceNo' => $invoice,
                'referenceNo' => (string) ($session['reference_no'] ?? ''),
                'merchantId' => (string) config('services.doku.snap.qris_merchant_id'),
                'reason' => 'Club 61: tagihan ditutup',
            ], self::CHANNEL_ID);
        } else {
            $response = $this->client->request('DELETE', self::VA_DELETE_PATH, [
                'partnerServiceId' => (string) ($session['partner_service_id'] ?? ''),
                'customerNo' => (string) ($session['customer_no'] ?? ''),
                'virtualAccountNo' => (string) ($session['va_number'] ?? ''),
                'trxId' => $invoice,
                'additionalInfo' => ['channel' => (string) ($session['channel'] ?? '')],
            ], self::CHANNEL_ID);
        }

        return $response !== null && $response['status'] >= 200 && $response['status'] < 300 && self::isSuccessCode($response['json']['responseCode'] ?? null);
    }

    /**
     * Data halaman bayar untuk tagihan yang masih menunggu: sesi SNAP terbaru yang aktif & belum lewat batas waktu.
     * Null = tidak ada (tagihan Midtrans / DOKU Checkout / sudah lunas / kedaluwarsa).
     */
    public static function display(?Payment $bill): ?array
    {
        if (! $bill || $bill->status !== 'PENDING' || $bill->payment_gateway !== 'DOKU') {
            return null;
        }

        $log = is_array($bill->payload_log) ? $bill->payload_log : [];
        $invoice = $log['doku_invoice_id'] ?? null;
        $session = is_string($invoice) ? DokuSessionGuard::session($bill, $invoice) : null;
        if (! self::isSnapSession($session) || ($session['status'] ?? DokuSessionGuard::ACTIVE) !== DokuSessionGuard::ACTIVE) {
            return null;
        }

        $expiresAt = DokuSessionGuard::expiresAt($session);
        if ($expiresAt && $expiresAt->isPast()) {
            return null;
        }

        $method = strtoupper((string) ($session['method'] ?? ''));

        return [
            'gateway' => 'DOKU',
            'type' => $session['product'],
            'method' => $method,
            'method_label' => app(\App\Services\Payment\OnlinePaymentMethodService::class)->label($method) ?? $method,
            'amount' => (float) ($session['amount'] ?? $bill->amount),
            'expires_at' => $expiresAt?->toIso8601String(),
            'qr_content' => $session['product'] === self::QRIS ? ($session['qr_content'] ?? null) : null,
            'va_number' => $session['product'] === self::VA ? preg_replace('/\D/', '', (string) ($session['va_number'] ?? '')) : null,
            'bank' => $session['bank'] ?? null,
            'sandbox' => ! app(DokuSnapClient::class)->isProduction(),
        ];
    }

    // ── QRIS ────────────────────────────────────────────────────────────────────────────────────────────────────

    private function createQris(string $invoice, int $amount, Carbon $expiresAt): array
    {
        $response = $this->client->request('POST', self::QRIS_GENERATE_PATH, [
            'partnerReferenceNo' => $invoice,
            'amount' => ['value' => self::money($amount), 'currency' => 'IDR'],
            'merchantId' => (string) config('services.doku.snap.qris_merchant_id'),
            'terminalId' => (string) config('services.doku.snap.qris_terminal_id'),
            'validityPeriod' => $expiresAt->copy()->timezone('Asia/Jakarta')->format('c'),
            'additionalInfo' => array_filter([
                'postalCode' => (string) config('services.doku.snap.qris_postal_code'),
                'feeType' => '1',
            ], fn ($v) => $v !== ''),
        ], self::CHANNEL_ID);

        $qr = $response['json']['qrContent'] ?? null;
        if (! $this->accepted($response) || ! is_string($qr) || $qr === '') {
            $this->failCreate($invoice, 'QRIS', $response);
        }

        return [
            'api' => self::API,
            'product' => self::QRIS,
            'channel' => 'QRIS',
            'request_id' => $response['external_id'],
            'reference_no' => is_string($response['json']['referenceNo'] ?? null) ? $response['json']['referenceNo'] : null,
            'qr_content' => $qr,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    private function qrisState(string $invoice, array $session): array
    {
        $response = $this->client->request('POST', self::QRIS_QUERY_PATH, [
            'originalReferenceNo' => (string) ($session['reference_no'] ?? ''),
            'originalPartnerReferenceNo' => $invoice,
            'serviceCode' => '47',
            'merchantId' => (string) config('services.doku.snap.qris_merchant_id'),
        ], self::CHANNEL_ID);

        if ($response === null) {
            return ['state' => DokuCheckoutService::ERROR, 'data' => null];
        }
        $json = $response['json'] ?? [];
        $status = (string) ($json['latestTransactionStatus'] ?? '');
        if ($response['status'] === 404 || $status === '07') {
            return ['state' => DokuCheckoutService::NOT_FOUND, 'data' => null];
        }
        if ($response['status'] < 200 || $response['status'] >= 300 || $status === '') {
            return ['state' => DokuCheckoutService::ERROR, 'data' => null];
        }
        // Jawaban untuk tagihan lain = janggal; jangan dipakai.
        if (isset($json['originalPartnerReferenceNo']) && $json['originalPartnerReferenceNo'] !== $invoice) {
            DokuClient::alert("DOKU SNAP QRIS: partnerReferenceNo respons tidak cocok [{$invoice}]");

            return ['state' => DokuCheckoutService::ERROR, 'data' => null];
        }

        // Kode status SNAP: 00 sukses, 01 initiated, 02 paying, 03 pending, 04 refunded, 05 canceled, 06 failed, 07 not found.
        $state = match ($status) {
            '00' => DokuCheckoutService::PAID,
            '01', '02', '03' => DokuCheckoutService::PENDING,
            '04' => DokuCheckoutService::REFUNDED,
            '05' => DokuCheckoutService::CLOSED,
            '06' => ($expires = DokuSessionGuard::expiresAt($session)) && $expires->isPast() ? DokuCheckoutService::CLOSED : DokuCheckoutService::PENDING,
            default => DokuCheckoutService::ERROR,
        };

        return ['state' => $state, 'data' => [
            'order' => ['invoice_number' => $invoice, 'amount' => (float) ($json['amount']['value'] ?? $json['paidAmount']['value'] ?? 0)],
            'transaction' => ['status' => self::transactionStatus($state), 'date' => $json['paidTime'] ?? null],
            'service' => ['id' => 'QRIS'],
            'channel' => ['id' => 'QRIS'],
            'emoney_payment' => ['approval_code' => $json['additionalInfo']['approvalCode'] ?? null],
        ]];
    }

    // ── VA ──────────────────────────────────────────────────────────────────────────────────────────────────────

    private function createVa(string $invoice, int $amount, string $channel, array $customer, Carbon $expiresAt): array
    {
        // DGPC (spesifikasi resmi "SNAP v1.1 - BCA VA - DGPC"): partnerServiceId = BIN rata kanan 8 karakter ("   19008"),
        // customerNo = prefix sub-BIN merchant ("0"), virtualAccountNo = gabungan keduanya. Nomor VA lengkap dibuat DOKU
        // dan dibaca dari respons (virtualAccountData.virtualAccountNo).
        $partnerServiceId = str_pad(trim((string) config('services.doku.snap.bca_partner_service_id')), 8, ' ', STR_PAD_LEFT);
        $customerNo = trim((string) config('services.doku.snap.bca_customer_no'));

        $clean = GatewayCustomer::clean($customer, 'Club 61 Customer');
        $response = $this->client->request('POST', self::VA_CREATE_PATH, array_filter([
            'partnerServiceId' => $partnerServiceId,
            'customerNo' => $customerNo,
            'virtualAccountNo' => $partnerServiceId.$customerNo,
            'virtualAccountName' => mb_substr((string) ($clean['name'] ?? 'Club 61 Customer'), 0, 255),
            'virtualAccountEmail' => $clean['email'] ?? null,
            'virtualAccountPhone' => $clean['phone'] ?? null,
            'trxId' => $invoice,
            'totalAmount' => ['value' => self::money($amount), 'currency' => 'IDR'],
            'additionalInfo' => ['channel' => $channel, 'virtualAccountConfig' => ['reusableStatus' => false]],
            'virtualAccountTrxType' => 'C',
            'expiredDate' => $expiresAt->copy()->timezone('Asia/Jakarta')->format('c'),
        ], fn ($v) => $v !== null), self::CHANNEL_ID);

        $data = $response['json']['virtualAccountData'] ?? null;
        $vaNumber = is_array($data) ? ($data['virtualAccountNo'] ?? null) : null;
        // Nomor dari DOKU harus berawalan BIN BCA kita dan sudah dilengkapi DOKU (lebih panjang dari BIN-nya saja).
        $bin = trim($partnerServiceId);
        $digits = is_string($vaNumber) ? preg_replace('/\D/', '', $vaNumber) : '';
        if (! $this->accepted($response) || strlen($digits) <= strlen($bin) || ! str_starts_with($digits, $bin)) {
            $this->failCreate($invoice, $channel, $response);
        }

        return [
            'api' => self::API,
            'product' => self::VA,
            'channel' => $channel,
            'bank' => self::BANKS[$channel] ?? null,
            'request_id' => $response['external_id'],
            'partner_service_id' => (string) ($data['partnerServiceId'] ?? $partnerServiceId),
            'customer_no' => (string) ($data['customerNo'] ?? $customerNo),
            'va_number' => $vaNumber,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    private function vaState(string $invoice, array $session): array
    {
        $response = $this->client->request('POST', self::VA_STATUS_PATH, [
            'partnerServiceId' => (string) ($session['partner_service_id'] ?? ''),
            'customerNo' => (string) ($session['customer_no'] ?? ''),
            'virtualAccountNo' => (string) ($session['va_number'] ?? ''),
            'additionalInfo' => (object) [],
        ], self::CHANNEL_ID);

        if ($response === null) {
            return ['state' => DokuCheckoutService::ERROR, 'data' => null];
        }
        $json = $response['json'] ?? [];
        $data = is_array($json['virtualAccountData'] ?? null) ? $json['virtualAccountData'] : [];
        if ($response['status'] === 404 || str_starts_with((string) ($json['responseCode'] ?? ''), '404')) {
            return ['state' => DokuCheckoutService::NOT_FOUND, 'data' => null];
        }
        if ($response['status'] < 200 || $response['status'] >= 300 || $data === []) {
            return ['state' => DokuCheckoutService::ERROR, 'data' => null];
        }
        // Contoh resmi menaruh trxId di additionalInfo (bukan di virtualAccountData).
        $trxId = $json['additionalInfo']['trxId'] ?? $data['trxId'] ?? null;
        if (is_string($trxId) && $trxId !== '' && $trxId !== $invoice) {
            DokuClient::alert("DOKU SNAP VA: trxId respons tidak cocok [{$invoice}]");

            return ['state' => DokuCheckoutService::ERROR, 'data' => null];
        }

        $paid = (float) ($data['paidAmount']['value'] ?? 0);
        $state = self::vaPaid($data) ? DokuCheckoutService::PAID : DokuCheckoutService::PENDING;

        return ['state' => $state, 'data' => [
            'order' => ['invoice_number' => $invoice, 'amount' => $paid],
            'transaction' => ['status' => self::transactionStatus($state), 'date' => $data['trxDateTime'] ?? null],
            'service' => ['id' => 'VIRTUAL_ACCOUNT'],
            'acquirer' => ['id' => $session['bank'] ?? null],
            'channel' => ['id' => $session['channel'] ?? null],
            'virtual_account_info' => ['virtual_account_number' => preg_replace('/\D/', '', (string) ($session['va_number'] ?? ''))],
        ]];
    }

    /**
     * VA sudah dibayar menurut cek status SNAP. Contoh resmi DOKU untuk VA yang BELUM dibayar tetap mengisi paidAmount
     * sebesar tagihan (paymentFlagReason "Pending"/"Belum Terbayar"), jadi paidAmount tidak boleh dipakai sebagai bukti.
     * Bukti bayar = paymentRequestId ("Mandatory if Payment happened") dan alasan status bukan Pending.
     */
    private static function vaPaid(array $data): bool
    {
        $paymentRequestId = $data['paymentRequestId'] ?? null;
        $reason = strtolower(trim((string) ($data['paymentFlagReason']['english'] ?? '')));

        return is_string($paymentRequestId) && trim($paymentRequestId) !== '' && ! str_contains($reason, 'pending');
    }

    // ── Bantu ───────────────────────────────────────────────────────────────────────────────────────────────────

    /** Nominal SNAP: rupiah bulat dengan 2 desimal ("10000.00"). */
    public static function money(int|float $amount): string
    {
        return number_format(round((float) $amount), 2, '.', '');
    }

    /** responseCode SNAP: 3 digit pertama = HTTP; 2xx = sukses. */
    public static function isSuccessCode(mixed $code): bool
    {
        return is_string($code) && str_starts_with($code, '200');
    }

    private function accepted(?array $response): bool
    {
        return $response !== null && $response['status'] >= 200 && $response['status'] < 300 && self::isSuccessCode($response['json']['responseCode'] ?? null);
    }

    private function failCreate(string $invoice, string $channel, ?array $response): never
    {
        DokuClient::alert("DOKU SNAP gagal membuat tagihan [{$invoice}]", [
            'kanal' => $channel,
            'http' => $response['status'] ?? null,
            'responseCode' => $response['json']['responseCode'] ?? null,
            'responseMessage' => $response['json']['responseMessage'] ?? null,
        ]);

        $status = (int) ($response['status'] ?? 0);
        if ($status >= 400 && $status < 500 && ! DokuClient::isCredentialError($status, $response['error'] ?? null)) {
            throw new HttpException(422, 'Metode pembayaran ini sedang tidak bisa dipakai. Belum ada tagihan yang dibuat — silakan pilih metode pembayaran lain.');
        }

        throw new PaymentGatewayUnavailableException();
    }

    private static function closeResult(string $state): ?string
    {
        return match ($state) {
            DokuCheckoutService::PAID => DokuCheckoutService::PAID,
            DokuCheckoutService::CLOSED => DokuCheckoutService::CLOSED,
            DokuCheckoutService::NOT_FOUND => DokuCheckoutService::NOT_FOUND,
            DokuCheckoutService::REFUNDED, DokuCheckoutService::ERROR => 'FAILED',
            default => null,
        };
    }

    /** Status sesi kita → transaction.status format DOKU Checkout (dipakai ringkasan struk / ledger). */
    private static function transactionStatus(string $state): string
    {
        return match ($state) {
            DokuCheckoutService::PAID => 'SUCCESS',
            DokuCheckoutService::CLOSED => 'EXPIRED',
            DokuCheckoutService::REFUNDED => 'REFUNDED',
            default => 'PENDING',
        };
    }
}
