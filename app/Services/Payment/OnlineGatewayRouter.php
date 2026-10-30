<?php

namespace App\Services\Payment;

use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\Pos\PaymentGatewaySetting;
use App\Services\Payment\Doku\DokuCheckoutService;
use App\Services\Payment\Doku\DokuReconciliationService;
use App\Services\Payment\Doku\DokuSessionGuard;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Pengarah gateway pembayaran online (Modul 27 §5.3). Semua kode di luar folder gateway (pembersih slot, kasir, Kelola
 * Pemesanan, invoice, membership, checkout) bertanya ke sini, bukan langsung ke Midtrans / DOKU:
 *   - tagihan yang SUDAH punya sesi diurus gateway yang membuatnya (selama gateway itu masih bisa dipakai);
 *   - tagihan BARU memakai gateway pilihan admin (online customer & QR kasir dipilih terpisah);
 *   - tagihan yang pindah gateway: semua sesi di gateway lama DITUTUP & dibuktikan dulu, baru sesi baru dibuat.
 * Hasil cek status diseragamkan: PAID / PENDING / NOT_PAID / ERROR (arti sama dengan MidtransReconciliationService).
 *
 * Kode Midtrans tidak diubah: jalur Midtrans memanggil MidtransReconciliationService / SnapSessionGuard / PaymentManager
 * persis seperti sebelumnya.
 */
class OnlineGatewayRouter
{
    public const MIDTRANS = 'MIDTRANS';

    public const DOKU = 'DOKU';

    public const PAID = MidtransReconciliationService::PAID;

    public const PENDING = MidtransReconciliationService::PENDING;

    public const NOT_PAID = MidtransReconciliationService::NOT_PAID;

    public const ERROR = MidtransReconciliationService::ERROR;

    /** Hasil prepareSession(): pakai lagi sesi lama / sesi lama ternyata lunas / boleh buat sesi baru. */
    public const SESSION_REUSE = SnapSessionGuard::REUSE;

    public const SESSION_PAID = SnapSessionGuard::PAID;

    public const SESSION_CREATE = SnapSessionGuard::CREATE;

    public const CACHE_KEY = 'payment_gateway_settings';

    public const CACHE_TTL_SECONDS = 300;

    /** Gateway yang integrasi pembayaran online customer-nya sudah ada di aplikasi. */
    private const INSTALLED = [self::MIDTRANS, self::DOKU];

    /** Gateway yang integrasi QR otomatis kasirnya sudah ada (DOKU kasir = Modul 27 Tahap 3, DokuPosQrisService). */
    private const POS_INSTALLED = [self::MIDTRANS, self::DOKU];

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [self::MIDTRANS => 'Midtrans', self::DOKU => 'DOKU'];
    }

    public static function label(string $gateway): string
    {
        return self::labels()[strtoupper($gateway)] ?? $gateway;
    }

    // ── Gateway untuk tagihan BARU ───────────────────────────────────────────────────────────────────────────────

    /** Gateway untuk pembayaran online customer (checkout padel, invoice, membership). */
    public function onlineGateway(): string
    {
        $gateway = strtoupper((string) $this->settings()['online_gateway']);

        return $this->isUsable($gateway) ? $gateway : self::MIDTRANS;
    }

    /** Gateway untuk "Bayar Otomatis" (QR) di layar kasir. Mode Manual (QRIS statis / EDC) tidak lewat sini. */
    public function posQrisGateway(): string
    {
        $gateway = strtoupper((string) $this->settings()['pos_qris_gateway']);

        return $this->isUsable($gateway, forPos: true) ? $gateway : self::MIDTRANS;
    }

    /** Pilihan yang TERSIMPAN (bisa berbeda dari yang dipakai kalau gateway itu sedang tidak siap). */
    public function savedSettings(): array
    {
        return $this->settings();
    }

    /** Gateway boleh dipakai untuk tagihan baru? Midtrans selalu (perilaku lama, termasuk mode mock di lokal). */
    public function isUsable(string $gateway, bool $forPos = false): bool
    {
        return $this->unavailableReason($gateway, $forPos) === null;
    }

    public function unavailableReason(string $gateway, bool $forPos = false): ?string
    {
        $gateway = strtoupper($gateway);

        if (! array_key_exists($gateway, self::labels())) {
            return 'Gateway tidak dikenal.';
        }
        if (! in_array($gateway, $forPos ? self::POS_INSTALLED : self::INSTALLED, true)) {
            return $forPos
                ? 'QR otomatis kasir lewat '.self::label($gateway).' belum terpasang di aplikasi.'
                : 'Integrasi '.self::label($gateway).' belum terpasang di aplikasi.';
        }
        if ($gateway === self::DOKU) {
            if (! config('services.doku.enabled')) {
                return 'DOKU dimatikan di server (DOKU_ENABLED=false).';
            }
            if (! app(DokuCheckoutService::class)->isConfigured()) {
                return 'Kunci DOKU belum diisi di server.';
            }
        }

        return null;
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    // ── Tagihan yang sudah ada: diurus gateway yang membuatnya ───────────────────────────────────────────────────

    /**
     * Gateway yang perlu ditanya untuk order ini. Order tanpa jejak DOKU = Midtrans saja (perilaku lama; rekonsiliasi
     * Midtrans juga menjawab order tanpa percobaan bayar online dari database). Order yang hanya punya sesi DOKU tidak
     * ditanyakan ke Midtrans.
     *
     * @return array<int, string>
     */
    public function gatewaysOf(Order $order): array
    {
        $bills = Payment::where('order_id', $order->id)->get();
        $hasDoku = $bills->contains(fn (Payment $p) => self::hasDokuTrace($p));

        if (! $hasDoku) {
            return [self::MIDTRANS];
        }

        return $bills->contains(fn (Payment $p) => self::hasMidtransTrace($p)) ? [self::MIDTRANS, self::DOKU] : [self::DOKU];
    }

    /** Nama gateway order ini untuk pesan ke admin / kasir, mis. "Midtrans" / "DOKU". */
    public function gatewayLabel(Order $order): string
    {
        return implode(' / ', array_map([self::class, 'label'], $this->gatewaysOf($order)));
    }

    /**
     * Cek status ke gateway tiap tagihan, lunasi lewat inti bersama kalau sudah dibayar. Lebih dari satu gateway:
     * PAID menang, lalu PENDING, lalu ERROR (belum pasti = jangan dianggap belum bayar), baru NOT_PAID.
     */
    public function reconcileOrder(Order $order, int $cacheSeconds = 0): string
    {
        $results = array_map(
            fn (string $gateway) => $this->reconciler($gateway)->reconcileOrder($order, cacheSeconds: $cacheSeconds),
            $this->gatewaysOf($order)
        );

        return self::combine($results);
    }

    /** @param  array<int, string>  $results */
    public static function combine(array $results): string
    {
        foreach ([self::PAID, self::PENDING, self::ERROR] as $winner) {
            if (in_array($winner, $results, true)) {
                return $winner;
            }
        }

        return self::NOT_PAID;
    }

    /** Ada tagihan PENDING yang pernah dicoba dibayar online? Wajib dicek sebelum kasir menerima uang. */
    public function hasOnlinePaymentAttempt(Order $order): bool
    {
        foreach ($this->gatewaysOf($order) as $gateway) {
            if ($this->reconciler($gateway)->hasOnlinePaymentAttempt($order)) {
                return true;
            }
        }

        return false;
    }

    /** Order pernah punya tagihan lewat gateway online (bukan kasir)? */
    public function hasGatewayPayment(Order $order): bool
    {
        return Payment::where('order_id', $order->id)
            ->whereIn('payment_gateway', array_keys(self::labels()))
            ->exists();
    }

    /** Gateway online yang sedang memegang tagihan ini (null = tagihan kasir / belum pernah dibayar online). */
    public static function billGateway(?Payment $bill): ?string
    {
        $gateway = strtoupper((string) $bill?->payment_gateway);

        return array_key_exists($gateway, self::labels()) ? $gateway : null;
    }

    /** Gateway untuk sesi bayar BARU tagihan ini: gateway pemegangnya kalau masih bisa dipakai, selain itu pilihan admin. */
    public function targetGatewayFor(?Payment $bill): string
    {
        $gateway = self::billGateway($bill);

        return $gateway !== null && $this->isUsable($gateway) ? $gateway : $this->onlineGateway();
    }

    /**
     * Aturan "satu tagihan = satu sesi bayar hidup". Dipanggil di dalam transaksi yang sudah mengunci tagihan.
     * Sesi tagihan di gateway LAIN ditutup dulu (dibuktikan lewat status); kalau ternyata sudah dibayar → PAID.
     *
     * @return array{action: string, gateway: string, session_id?: string, session?: array, status?: ?array}
     */
    public function prepareSession(?Payment $bill, string $method, float $amount, ?string $gateway = null): array
    {
        $gateway = strtoupper($gateway ?? $this->targetGatewayFor($bill));

        if (! $bill) {
            return ['action' => self::SESSION_CREATE, 'gateway' => $gateway];
        }

        if ($gateway !== self::DOKU && self::hasDokuTrace($bill)) {
            $closed = app(DokuSessionGuard::class)->closeAll($bill);
            if ($closed['action'] === self::SESSION_PAID) {
                return $closed + ['gateway' => self::DOKU];
            }
            $bill->refresh();
        }

        if ($gateway !== self::MIDTRANS && self::hasMidtransTrace($bill)) {
            // Metode yang tidak pernah cocok = semua sesi Snap ditutup & dibuktikan (logika SnapSessionGuard apa adanya).
            $closed = app(SnapSessionGuard::class)->prepare($bill, '__CLOSE_ALL__', -1);
            if ($closed['action'] === self::SESSION_PAID) {
                return $closed + ['gateway' => self::MIDTRANS];
            }
            $bill->refresh();
        }

        // Tagihan tanpa sesi Midtrans (tagihan kasir / pindahan dari DOKU): tidak ada yang perlu ditutup di Midtrans.
        if ($gateway === self::MIDTRANS && ! self::hasMidtransTrace($bill)) {
            return ['action' => self::SESSION_CREATE, 'gateway' => $gateway];
        }

        $prepared = $gateway === self::DOKU
            ? app(DokuSessionGuard::class)->prepare($bill, $method, $amount)
            : app(SnapSessionGuard::class)->prepare($bill, $method, $amount);

        return $prepared + ['gateway' => $gateway];
    }

    /**
     * Buat sesi bayar baru di gateway itu. Fail-closed (gagal = exception, transaksi checkout di-rollback).
     *
     * @param  array{order_number: string, session_id?: string, amount: int, items: array, customer: array{name?: ?string, email?: ?string, phone?: ?string}, method: string, expiry_minutes?: ?int, callback_url: string}  $p
     *   `session_id` = order_id Snap yang dipakai Midtrans (nomor order / dengan akhiran); DOKU membuat invoice sendiri.
     * @return array{gateway: string, driver: string, session_id: string, snap_token: ?string, payment_url: ?string, redirect_url: ?string, checkout_mode: string, is_mock: bool, token_id?: ?string, request_id?: string, expires_at?: CarbonInterface}
     */
    public function createSession(string $gateway, array $p): array
    {
        $method = strtoupper($p['method']);

        if (strtoupper($gateway) === self::DOKU) {
            $invoice = DokuCheckoutService::newInvoiceNumber($p['order_number']);
            $dueMinutes = $p['expiry_minutes'] ?? app(\App\Services\Padel\BookingTimeService::class)->paymentWindowMinutes();
            $channels = app(OnlinePaymentMethodService::class)->channels($method, self::DOKU) ?? [];

            // Modul 27b: QRIS & VA BCA lewat DOKU SNAP (halaman bayar sendiri) kalau SNAP aktif; lainnya DOKU Checkout.
            $snapService = app(\App\Services\Payment\Doku\Snap\DokuSnapService::class);
            if ($snapService->productFor($channels) !== null) {
                $snap = $snapService->create($invoice, (int) $p['amount'], $channels, $p['customer'] ?? [], (int) $dueMinutes);

                return [
                    'gateway' => self::DOKU,
                    'driver' => 'doku',
                    'session_id' => $invoice,
                    'snap_token' => null,
                    'payment_url' => null,
                    'redirect_url' => null,
                    'checkout_mode' => 'SNAP',
                    'is_mock' => false,
                    'snap' => $snap,
                    'request_id' => $snap['request_id'],
                    'expires_at' => \Illuminate\Support\Carbon::parse($snap['expires_at']),
                ];
            }

            $created = app(DokuCheckoutService::class)->createSession([
                'invoice' => $invoice,
                'amount' => (int) $p['amount'],
                'channels' => $channels,
                'items' => $p['items'],
                'customer' => $p['customer'],
                'due_minutes' => (int) $dueMinutes,
                'callback_url' => $p['callback_url'],
            ]);

            return [
                'gateway' => self::DOKU,
                'driver' => 'doku',
                'session_id' => $invoice,
                'snap_token' => null,
                'payment_url' => $created['payment_url'],
                'redirect_url' => $created['payment_url'],
                'checkout_mode' => 'REDIRECT',
                'is_mock' => false,
                'token_id' => $created['token_id'],
                'request_id' => $created['request_id'],
                'expires_at' => $created['expires_at'],
            ];
        }

        $params = [
            'order_id' => $p['session_id'],
            'gross_amount' => (int) $p['amount'],
            'item_details' => $p['items'],
            'customer_details' => GatewayCustomer::forMidtrans($p['customer'] ?? []),
            'payment_method' => $p['method'],
        ];
        if (isset($p['expiry_minutes'])) {
            $params['expiry_minutes'] = $p['expiry_minutes'];
        }

        return app(PaymentManager::class)->createPayment($params) + ['gateway' => self::MIDTRANS, 'session_id' => $p['session_id']];
    }

    /** Catat sesi yang baru dibuat di payload_log tagihan (format catatan sesi milik gateway-nya). */
    public static function recordSession(array $log, array $created, string $method, float $amount, ?CarbonInterface $expiresAt): array
    {
        if (($created['gateway'] ?? null) === self::DOKU && ($created['checkout_mode'] ?? null) === 'SNAP') {
            return DokuSessionGuard::recordSnap($log, $created['session_id'], $method, $amount, $created['snap']);
        }
        if (($created['gateway'] ?? null) === self::DOKU) {
            return DokuSessionGuard::record($log, $created['session_id'], $method, $amount, $created['payment_url'], $created['token_id'] ?? null, $created['request_id'], $created['expires_at'] ?? $expiresAt);
        }

        return SnapSessionGuard::record($log, $created['session_id'], $method, $amount, $created['snap_token'] ?? null, $created['redirect_url'] ?? $created['payment_url'] ?? null, $expiresAt);
    }

    /** Respons "pakai lagi sesi ini" (dari prepareSession REUSE) — bentuknya sama dengan createSession(). */
    public static function reusedPayment(array $prepared): array
    {
        return ($prepared['gateway'] ?? null) === self::DOKU
            ? DokuSessionGuard::reusedPayment($prepared['session'])
            : SnapSessionGuard::reusedPayment($prepared['session']) + ['driver' => 'midtrans'];
    }

    /** Sesi dari prepareSession() ternyata sudah dibayar → lunasi lewat jalur yang sama dengan webhook (idempoten). */
    public function settlePreparedSession(Order $order, array $prepared): void
    {
        if (($prepared['gateway'] ?? self::MIDTRANS) === self::DOKU) {
            $bill = DokuSessionGuard::findBill($prepared['session_id']);
            $session = $bill ? (DokuSessionGuard::session($bill, $prepared['session_id']) ?? []) : [];
            if (! app(DokuReconciliationService::class)->amountMatches($prepared['status'] ?? null, $session, $prepared['session_id'])) {
                throw new HttpException(409, \App\Support\SiteLocale::text('payment.under_review'));
            }
            app(DokuReconciliationService::class)->settle($order, $prepared['session_id'], $prepared['status'] ?? [], 'DOKU_STATUS_API');

            return;
        }

        if (! app(MidtransReconciliationService::class)->settle($order, $prepared['session_id'], $prepared['status'] ?? [])) {
            throw new HttpException(409, \App\Support\SiteLocale::text('payment.under_review'));
        }
    }

    /**
     * Tutup sesi bayar di gateway supaya tidak bisa dibayar lagi. Best-effort, dipanggil di luar transaksi DB: kalau
     * tetap terbayar, uangnya tercatat sebagai pembayaran ganda + refund PENDING (PaymentOrchestratorService).
     *
     * @param  iterable<int, mixed>  $sessionIds
     */
    public function cancelSessions(iterable $sessionIds, string $gateway = self::MIDTRANS): void
    {
        foreach ($sessionIds as $sessionId) {
            if (! is_string($sessionId) || $sessionId === '') {
                continue;
            }

            try {
                match (strtoupper($gateway)) {
                    self::MIDTRANS => app(MidtransService::class)->cancelTransaction($sessionId),
                    self::DOKU => $this->cancelDokuSession($sessionId),
                };
            } catch (\Throwable $e) {
                // Best-effort (lihat di atas).
            }
        }
    }

    /**
     * Order dibatalkan / hangus: tutup sesi bayarnya di gateway (best-effort, di luar transaksi DB). Midtrans & DOKU:
     * semua sesi tagihan order itu yang belum mati / belum lunas (dulu Midtrans hanya sesi bernomor order polos —
     * sesi bayar ulang / ganti metode tetap bisa dibayar → uang masuk untuk booking yang sudah dilepas → refund).
     *
     * @param  iterable<int, mixed>  $orderNumbers
     */
    public function cancelOrderSessions(iterable $orderNumbers): void
    {
        $orderNumbers = array_values(array_unique(array_filter(is_array($orderNumbers) ? $orderNumbers : iterator_to_array($orderNumbers), fn ($n) => is_string($n) && $n !== '')));
        $orders = Order::whereIn('order_number', $orderNumbers)->get()->keyBy('order_number');

        foreach ($orderNumbers as $orderNumber) {
            $order = $orders->get($orderNumber);
            if (! $order || in_array(self::MIDTRANS, $this->gatewaysOf($order), true)) {
                $this->cancelSessions([$orderNumber]);
            }
            if (! $order) {
                continue;
            }

            $invoices = [];
            foreach (Payment::where('order_id', $order->id)->whereNotIn('status', ['SUCCESS', PaymentOrchestratorService::DUPLICATE_STATUS])->get() as $bill) {
                if (self::hasMidtransTrace($bill)) {
                    try {
                        app(SnapSessionGuard::class)->closeOpenSessions($bill, skip: [$orderNumber]);
                    } catch (\Throwable $e) {
                        // Best-effort (lihat di atas); sweep berkala + webhook tetap mencatat pembayaran yang lolos.
                    }
                }
                foreach (DokuSessionGuard::sessionsOf($bill) as $invoice) {
                    if (! in_array(DokuSessionGuard::session($bill, $invoice)['status'] ?? null, [DokuSessionGuard::CLOSED, DokuSessionGuard::EXPIRED, DokuSessionGuard::DUPLICATE], true)) {
                        $invoices[] = $invoice;
                    }
                }
            }
            $this->cancelSessions($invoices, self::DOKU);
        }
    }

    /** Jejak sesi DOKU di tagihan (gateway DOKU, atau pernah membuka sesi DOKU lalu dikembalikan ke kasir). */
    public static function hasDokuTrace(Payment $bill): bool
    {
        return $bill->payment_gateway === self::DOKU || DokuSessionGuard::sessionsOf($bill) !== [];
    }

    /** Jejak sesi Midtrans (aturan yang sama dengan MidtransReconciliationService::hasOnlinePaymentAttempt). */
    public static function hasMidtransTrace(Payment $bill): bool
    {
        $log = is_array($bill->payload_log) ? $bill->payload_log : [];

        return $bill->payment_gateway === self::MIDTRANS
            || ! empty($log['midtrans_order_ids'])
            || ! empty($log['midtrans_order_id']);
    }

    // ── Internal ─────────────────────────────────────────────────────────────────────────────────────────────────

    private function cancelDokuSession(string $invoice): void
    {
        $bill = DokuSessionGuard::findBill($invoice);
        $requestId = $bill ? (DokuSessionGuard::session($bill, $invoice)['request_id'] ?? null) : null;
        if (is_string($requestId) && $requestId !== '') {
            app(DokuCheckoutService::class)->cancel($invoice, $requestId);
        }
    }

    /** Diambil saat dipanggil (bukan di constructor) supaya binding / mock di container selalu terpakai. */
    private function reconciler(string $gateway): MidtransReconciliationService|DokuReconciliationService
    {
        return match (strtoupper($gateway)) {
            self::MIDTRANS => app(MidtransReconciliationService::class),
            self::DOKU => app(DokuReconciliationService::class),
        };
    }

    /** @return array{online_gateway: string, pos_qris_gateway: string} */
    private function settings(): array
    {
        $defaults = ['online_gateway' => self::MIDTRANS, 'pos_qris_gateway' => self::MIDTRANS];

        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () use ($defaults) {
                $row = PaymentGatewaySetting::query()->first();

                return $row ? [
                    'online_gateway' => strtoupper((string) $row->online_gateway),
                    'pos_qris_gateway' => strtoupper((string) $row->pos_qris_gateway),
                ] : $defaults;
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // Tabel belum ada (deploy lupa `php artisan migrate`): tetap Midtrans supaya pembayaran jalan.
            report($e);

            return $defaults;
        }
    }
}
