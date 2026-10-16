<?php

namespace App\Services\Payment;

use App\Exceptions\PaymentGatewayUnavailableException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MidtransService
{
    protected string $serverKey;
    protected string $clientKey;
    protected bool $isProduction;
    protected string $snapApiUrl;

    public function __construct()
    {
        $this->serverKey = config('services.midtrans.server_key') ?? '';
        $this->clientKey = (string) config('services.midtrans.client_key');
        $this->isProduction = (bool) config('services.midtrans.is_production', false);

        $this->snapApiUrl = $this->isProduction
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
    }

    /**
     * Membuat Snap Transaction Token dengan asersi matematis anti-mismatch.
     *
     * @param array $params [
     *     'order_id' => string,
     *     'gross_amount' => int,
     *     'item_details' => array,
     *     'customer_details' => array,
     * ]
     * @return array ['snap_token' => string, 'redirect_url' => string, 'is_mock' => bool]
     * @throws InvalidArgumentException
     */
    public function createSnapTransaction(array $params): array
    {
        $orderId = $params['order_id'];
        $grossAmount = (int) $params['gross_amount'];
        $itemDetails = $params['item_details'] ?? [];
        $customerDetails = $params['customer_details'] ?? [];

        // 🛡️ QA DEFENSE 3: Gross Amount Mismatch Exact Math Assertion
        $calculatedSum = 0;
        foreach ($itemDetails as $item) {
            $calculatedSum += (int) $item['price'] * (int) $item['quantity'];
        }

        if ($calculatedSum !== $grossAmount) {
            Log::error("Midtrans Gross Amount Mismatch", [
                'order_id' => $orderId,
                'calculated_sum' => $calculatedSum,
                'gross_amount' => $grossAmount,
                'items' => $itemDetails,
            ]);
            throw new InvalidArgumentException(
                "Gross amount mismatch: calculated sum ({$calculatedSum}) does not equal gross amount ({$grossAmount})."
            );
        }

        // Token mock (= pemanggil langsung menganggap order LUNAS) HANYA boleh di mesin developer /
        // automated test. Di server mana pun (sandbox, staging, production) key kosong berarti salah
        // konfigurasi — tolak checkout, jangan kasih booking/membership gratis.
        if (app()->environment('testing') || (empty($this->serverKey) && app()->environment('local'))) {
            Log::info("Midtrans Sandbox Mock Token generated for order: {$orderId}");
            return [
                'snap_token' => 'DEMO-SNAP-' . Str::uuid(),
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/' . Str::uuid(),
                'is_mock' => true,
            ];
        }

        if (empty($this->serverKey)) {
            Log::critical("[ALERT] MIDTRANS_SERVER_KEY kosong di environment '" . app()->environment() . "' — checkout ditolak [{$orderId}].");
            throw new PaymentGatewayUnavailableException();
        }

        $payload = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $grossAmount,
            ],
            'item_details' => $itemDetails,
            // Batas bayar dari pengaturan admin (BookingTimeService) — SAMA dengan batas pelepasan slot. Bayar ulang
            // mengirim sisa waktunya sendiri (`expiry_minutes`) supaya ganti metode tidak memperpanjang batas bayar.
            // Nilai di sini mengalahkan pengaturan "Payment Expiry" di dashboard Midtrans.
            'expiry' => [
                'start_time' => now()->timezone('Asia/Jakarta')->format('Y-m-d H:i:s O'),
                'unit' => 'minute',
                'duration' => max(1, (int) ($params['expiry_minutes'] ?? app(\App\Services\Padel\BookingTimeService::class)->paymentWindowMinutes())),
            ],
        ];

        // Order tanpa data customer (F&B kasir): customer_details kosong terkirim sebagai [] (bukan objek) dan ditolak.
        if (! empty($customerDetails)) {
            $payload['customer_details'] = $customerDetails;
        }

        if (!empty($params['payment_method'])) {
            // Pemetaan dari katalog resmi (metode bawaan) / kanal pilihan admin (metode tambahan, hanya kode resmi) —
            // satu sumber dengan halaman checkout & validasi.
            $enabledPayments = app(OnlinePaymentMethodService::class)->midtransChannels((string) $params['payment_method']);

            if ($enabledPayments) {
                $payload['enabled_payments'] = $enabledPayments;
            }

            // Kartu online WAJIB 3D Secure (OTP bank) — tanpa ini kartu curian bisa dipakai lalu berujung chargeback.
            if (in_array('credit_card', $enabledPayments ?? [], true)) {
                $payload['credit_card'] = ['secure' => true];
            }
        }

        // Fail-closed: dulu SEMUA error di sini (jaringan putus, key salah, request ditolak) dibalas
        // token mock → pemanggil menandai order LUNAS tanpa uang masuk. Sekarang error dilempar,
        // transaksi checkout di-rollback, dan customer diminta mencoba lagi.
        try {
            // Timeout eksplisit: panggilan ini berjalan di dalam transaksi DB yang mengunci baris booking/court —
            // Midtrans yang lambat (default 30 detik) membuat antrean lock menumpuk.
            $response = Http::withBasicAuth($this->serverKey, '')
                ->withHeaders($this->snapHeaders())
                ->timeout(10)
                ->post($this->snapApiUrl, $payload);
        } catch (\Throwable $e) {
            Log::error("[ALERT] Midtrans Snap tidak bisa dihubungi [{$orderId}]: " . $e->getMessage());
            throw new PaymentGatewayUnavailableException(previous: $e);
        }

        $data = $response->json();

        if (! $response->successful() || empty($data['token'])) {
            Log::error("[ALERT] Midtrans Snap menolak transaksi [{$orderId}] HTTP {$response->status()}: " . $response->body());
            throw new PaymentGatewayUnavailableException();
        }

        return [
            'snap_token' => $data['token'],
            'redirect_url' => $data['redirect_url'] ?? null,
            'is_mock' => false,
        ];
    }

    /**
     * Header request Snap. MIDTRANS_NOTIFICATION_URL (https) → X-Override-Notification: webhook transaksi ini dikirim ke
     * URL tersebut, bukan ke "Payment Notification URL" dashboard (satu akun sandbox dipakai server & laptop ngrok).
     */
    public function snapHeaders(): array
    {
        $headers = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];
        $url = trim((string) config('services.midtrans.notification_url'));

        // Tidak pernah berlaku di production: salah isi .env di sana akan mengirim webhook uang sungguhan ke laptop orang.
        if ($url !== '' && ! $this->isProduction && ! app()->environment('production')
            && str_starts_with($url, 'https://') && filter_var($url, FILTER_VALIDATE_URL)) {
            $headers['X-Override-Notification'] = $url;
        }

        return $headers;
    }

    /**
     * Membatalkan transaksi di sisi gateway Midtrans (Core API Cancel). Best-effort — pemanggil tidak memakai
     * hasilnya untuk keputusan uang; yang butuh kepastian memakai closeTransaction().
     */
    public function cancelTransaction(string $orderId): bool
    {
        if (empty($this->serverKey) || app()->environment('testing')) {
            Log::info("Midtrans Mock Cancel executed for order: {$orderId}");
            return true;
        }

        $response = $this->postAction($orderId, 'cancel');

        return $response !== null && $this->actionAccepted($response);
    }

    public const CLOSE_CLOSED = 'CLOSED';

    public const CLOSE_NOT_FOUND = 'NOT_FOUND';

    public const CLOSE_PAID = 'PAID';

    public const CLOSE_FAILED = 'FAILED';

    /**
     * Pastikan sesi pembayaran ini TIDAK BISA dibayar lagi (VA / QR-nya mati), lalu buktikan lewat Status API —
     * jawaban API expire/cancel saja tidak dipercaya (Midtrans bisa membalas HTTP 200 dengan status_code gagal).
     *
     * @return array{result: string, status: ?array}
     *   CLOSED    — sudah expire / cancel / deny / gagal di Midtrans
     *   NOT_FOUND — Midtrans belum mengenal order_id ini (popup Snap belum memilih metode)
     *   PAID      — ternyata SUDAH dibayar (status berisi data Status API) → jangan buka sesi baru
     *   FAILED    — Midtrans tidak bisa dihubungi / masih pending setelah dicoba ditutup → jangan buka sesi baru
     */
    public function closeTransaction(string $orderId): array
    {
        // Mode mock / test tanpa server key: tidak ada transaksi sungguhan di Midtrans.
        if (empty($this->serverKey)) {
            return ['result' => self::CLOSE_NOT_FOUND, 'status' => null];
        }

        $status = $this->getTransactionStatus($orderId);
        $state = $this->closeState($status);
        if ($state !== null) {
            return ['result' => $state, 'status' => $status];
        }

        // Masih pending → expire (pembayaran yang menunggu: VA, QRIS, e-wallet); cancel sebagai cadangan.
        foreach (['expire', 'cancel'] as $action) {
            $this->postAction($orderId, $action);
            $status = $this->getTransactionStatus($orderId);
            $state = $this->closeState($status);
            if ($state !== null) {
                return ['result' => $state, 'status' => $status];
            }
        }

        Log::warning("[ALERT] Sesi Midtrans [{$orderId}] belum bisa ditutup (masih ".($status['transaction_status'] ?? 'tidak diketahui').').');

        return ['result' => self::CLOSE_FAILED, 'status' => $status];
    }

    /** Status API → hasil penutupan; null = masih bisa dibayar (pending / challenge). */
    private function closeState(?array $status): ?string
    {
        if ($status === null) {
            return self::CLOSE_FAILED;
        }
        if ((string) ($status['status_code'] ?? '') === '404') {
            return self::CLOSE_NOT_FOUND;
        }
        if (($status['transaction_status'] ?? null) === 'failure') {
            return self::CLOSE_CLOSED;
        }

        return match (self::normalizeStatus((string) ($status['transaction_status'] ?? ''), $status['fraud_status'] ?? null)) {
            'PAID' => self::CLOSE_PAID,
            'PENDING', 'CHALLENGE' => null,
            'CANCELLED' => self::CLOSE_CLOSED,
            // refund / chargeback dll.: transaksi ini dulu dibayar — jangan dianggap aman untuk ditinggal.
            default => self::CLOSE_FAILED,
        };
    }

    private function postAction(string $orderId, string $action): ?\Illuminate\Http\Client\Response
    {
        $url = ($this->isProduction ? 'https://api.midtrans.com/v2/' : 'https://api.sandbox.midtrans.com/v2/')
            .rawurlencode($orderId).'/'.$action;

        try {
            return Http::withBasicAuth($this->serverKey, '')
                ->acceptJson()
                ->asJson()
                ->timeout(5)
                ->post($url);
        } catch (\Throwable $e) {
            Log::warning("Midtrans {$action} gagal ({$orderId}): ".$e->getMessage());

            return null;
        }
    }

    /** Midtrans bisa membalas HTTP 200 dengan status_code gagal (412, 404, …) — cek isi responsnya. */
    private function actionAccepted(\Illuminate\Http\Client\Response $response): bool
    {
        $code = (string) ($response->json('status_code') ?? '');

        return $response->successful()
            && ($code === '' || str_starts_with($code, '2') || $code === '407'
                || in_array($response->json('transaction_status'), ['cancel', 'expire'], true));
    }

    /**
     * Tanya status transaksi langsung ke Midtrans (Core API GET /v2/{order_id}/status) — jalur
     * cadangan kalau webhook tidak pernah sampai (URL notifikasi belum didaftarkan, server down,
     * timeout). Mengembalikan:
     *   - array respons Midtrans kalau transaksinya ada,
     *   - ['status_code' => '404'] kalau Midtrans tidak kenal order_id tersebut,
     *   - null kalau gagal menghubungi Midtrans (jangan diartikan "belum bayar").
     */
    /** Tanpa server key = mode mock/lokal; tidak ada transaksi Midtrans sungguhan yang bisa dicek. */
    public function isConfigured(): bool
    {
        return ! empty($this->serverKey);
    }

    public function getTransactionStatus(string $orderId): ?array
    {
        if (empty($this->serverKey)) {
            return null;
        }

        $url = ($this->isProduction ? 'https://api.midtrans.com/v2/' : 'https://api.sandbox.midtrans.com/v2/')
            .rawurlencode($orderId).'/status';

        try {
            $response = Http::withBasicAuth($this->serverKey, '')
                ->acceptJson()
                ->timeout(5)
                ->get($url);
        } catch (\Throwable $e) {
            Log::warning("Midtrans status check gagal ({$orderId}): ".$e->getMessage());

            return null;
        }

        $data = $response->json();

        if (! is_array($data)) {
            Log::warning("Midtrans status check: respons tidak valid ({$orderId}) HTTP {$response->status()}");

            return null;
        }

        if ((string) ($data['status_code'] ?? '') === '404' || $response->status() === 404) {
            return ['status_code' => '404'];
        }

        if (! $response->successful() || empty($data['transaction_status'])) {
            Log::warning("Midtrans status check: HTTP {$response->status()} ({$orderId})", ['body' => $data]);

            return null;
        }

        return $data;
    }

    /**
     * Normalisasi status Midtrans → status internal. Dipakai webhook DAN status check supaya
     * aturan "kapan dianggap lunas" hanya ada di satu tempat.
     */
    public static function normalizeStatus(string $transactionStatus, ?string $fraudStatus = null): string
    {
        return match ($transactionStatus) {
            // Kartu: lunas HANYA kalau fraud_status = accept (rekomendasi Midtrans). Challenge / deny / status lain =
            // belum lunas (menunggu keputusan di dashboard Midtrans). Kanal non-kartu tidak mengirim fraud_status.
            'capture' => in_array($fraudStatus, [null, '', 'accept'], true) ? 'PAID' : 'CHALLENGE',
            'settlement' => 'PAID',
            'pending' => 'PENDING',
            'deny', 'expire', 'cancel' => 'CANCELLED',
            default => 'UNKNOWN',
        };
    }

    /**
     * Verifikasi Signature Anti-Spoofing (SHA512 + hash_equals).
     *
     * Rumus: SHA512(order_id + status_code + gross_amount + server_key)
     */
    public static function verifySignature(string $orderId, string $statusCode, string $grossAmount, string $incomingSignature, ?string $serverKey = null): bool
    {
        $key = $serverKey ?? (config('services.midtrans.server_key') ?? '');

        if (empty($key)) {
            // Pada environment production, penandatangan tanpa server key wajib ditolak demi keamanan (fail-closed)
            if (app()->environment('production')) {
                return false;
            }
            return true;
        }

        $calculated = hash('sha512', $orderId . $statusCode . $grossAmount . $key);

        return hash_equals($calculated, $incomingSignature);
    }
}
