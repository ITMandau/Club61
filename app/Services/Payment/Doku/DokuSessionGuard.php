<?php

namespace App\Services\Payment\Doku;

use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Satu tagihan = paling banyak SATU sesi DOKU yang bisa dibayar pada satu waktu (versi DOKU dari SnapSessionGuard):
 *   - metode & nominal sama, sesi terakhir masih berlaku → sesi itu dipakai lagi;
 *   - ganti metode → semua sesi lama DIBATALKAN di DOKU dan dibuktikan lewat API status, baru sesi baru dibuat;
 *     sesi lama ternyata sudah dibayar → tidak ada sesi baru, tagihan dilunasi; DOKU tidak bisa dihubungi / sesi
 *     belum pasti mati → ditolak (lebih baik customer menunggu daripada ada dua sesi aktif);
 *   - begitu satu sesi lunas, sesi lain ditutup (closeSiblings) dan disapu rekonsiliasi 48 jam (sweepPaidSiblings).
 *
 * Catatan sesi di payload_log tagihan: doku_sessions[invoice] = {method, amount, payment_url, token_id, request_id,
 * expires_at, status, created_at}; doku_invoice_id = sesi terbaru; doku_invoice_ids = semua sesi (webhook & rekonsiliasi).
 */
class DokuSessionGuard
{
    /** Sesi pertama + 5x ganti metode (sama dengan Midtrans). */
    public const MAX_SESSIONS = 6;

    public const SIBLING_WATCH_HOURS = 48;

    public const ACTIVE = 'ACTIVE';

    public const CLOSED = 'CLOSED';

    public const EXPIRED = 'EXPIRED';

    public const DUPLICATE = 'DUPLICATE';

    public const REUSE = 'REUSE';

    public const PAID = 'PAID';

    public const CREATE = 'CREATE';

    private const DEAD = [self::CLOSED, self::EXPIRED, self::DUPLICATE];

    public function __construct(protected DokuCheckoutService $checkout) {}

    /** Invoice DOKU milik tagihan, urut lama → baru. */
    public static function sessionsOf(Payment $bill): array
    {
        $log = self::log($bill);
        $ids = array_merge((array) ($log['doku_invoice_ids'] ?? []), [$log['doku_invoice_id'] ?? null]);

        return array_values(array_unique(array_filter($ids, fn ($id) => is_string($id) && $id !== '')));
    }

    public static function session(Payment $bill, string $invoice): ?array
    {
        $session = self::log($bill)['doku_sessions'][$invoice] ?? null;

        return is_array($session) ? $session : null;
    }

    /**
     * Tagihan pemilik invoice ini (webhook / pembatalan). Nomor order dibaca dari invoice, lalu kepemilikan dicocokkan
     * dengan catatan sesi tagihan — invoice yang tidak tercatat di tagihan mana pun tidak pernah diterima.
     */
    public static function findBill(string $invoice, bool $lock = false): ?Payment
    {
        $orderNumber = DokuCheckoutService::orderNumberOf($invoice);
        $order = $orderNumber ? Order::where('order_number', $orderNumber)->first() : null;
        if (! $order) {
            return null;
        }

        $query = Payment::where('order_id', $order->id);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get()->first(fn (Payment $p) => in_array($invoice, self::sessionsOf($p), true));
    }

    /**
     * Dipanggil DI DALAM transaksi yang sudah mengunci tagihan, sebelum membuat sesi DOKU baru.
     *
     * @return array{action: string, session_id?: string, session?: array, status?: ?array}
     *
     * @throws HttpException 429 batas ganti metode, 503 sesi lama tidak bisa dipastikan tertutup
     */
    public function prepare(?Payment $bill, string $method, float $amount): array
    {
        if (! $bill) {
            return ['action' => self::CREATE];
        }

        $log = self::log($bill);
        $sessions = self::sessionsOf($bill);
        $latest = $log['doku_invoice_id'] ?? (end($sessions) ?: null);
        $meta = $latest ? ($log['doku_sessions'][$latest] ?? null) : null;

        // Sesi yang sudah tercatat mati (dibatalkan & diterima DOKU) tidak pernah dipakai lagi — cek status VA SNAP yang
        // sudah dihapus tetap menjawab "pending".
        if ($latest && is_array($meta) && ! in_array($meta['status'] ?? null, self::DEAD, true) && $this->matches($meta, $method, $amount)) {
            $remote = $this->checkout->state($latest, self::expiresAt($meta));
            if ($remote['state'] === DokuCheckoutService::PAID) {
                return ['action' => self::PAID, 'session_id' => $latest, 'status' => $remote['data']];
            }
            // Masih bisa dibayar, atau belum bisa dicek → pakai sesi yang sama (tidak menambah sesi baru).
            if (in_array($remote['state'], [DokuCheckoutService::PENDING, DokuCheckoutService::ERROR, DokuCheckoutService::NOT_FOUND], true)) {
                return ['action' => self::REUSE, 'session_id' => $latest, 'session' => $meta];
            }
            // Sesi itu sudah mati di DOKU → lanjut buat sesi baru.
        }

        // Batas dicek SEBELUM menutup apa pun: customer yang kena batas tetap bisa membayar sesi terakhirnya.
        if (count($sessions) >= self::MAX_SESSIONS) {
            throw new HttpException(429, \App\Support\SiteLocale::text('payment.too_many_switches'));
        }

        return $this->closeAll($bill, $log, $sessions);
    }

    /**
     * Tutup SEMUA sesi DOKU tagihan (dipakai juga saat tagihan pindah ke gateway lain).
     *
     * @return array{action: string, session_id?: string, status?: ?array} PAID atau CREATE
     *
     * @throws HttpException 503
     */
    public function closeAll(Payment $bill, ?array $log = null, ?array $sessions = null): array
    {
        $log ??= self::log($bill);
        $sessions ??= self::sessionsOf($bill);

        foreach ($sessions as $invoice) {
            $meta = $log['doku_sessions'][$invoice] ?? [];
            if (in_array($meta['status'] ?? null, self::DEAD, true)) {
                continue;
            }

            $closed = $this->checkout->close($invoice, $meta['request_id'] ?? null, self::expiresAt($meta));

            if ($closed['result'] === DokuCheckoutService::PAID) {
                return ['action' => self::PAID, 'session_id' => $invoice, 'status' => $closed['data']];
            }
            if ($closed['result'] === 'FAILED') {
                throw new HttpException(503, 'Pembayaran sebelumnya belum bisa ditutup karena layanan pembayaran sedang tidak bisa dihubungi. Coba lagi beberapa saat — jangan bayar memakai kode / VA yang lama.');
            }

            $log = self::withStatus($log, $invoice, self::CLOSED);
        }

        $bill->update(['payload_log' => $log]);

        return ['action' => self::CREATE];
    }

    /** Catat sesi baru sebagai sesi aktif tagihan. */
    public static function record(array $log, string $invoice, string $method, float $amount, string $paymentUrl, ?string $tokenId, string $requestId, ?CarbonInterface $expiresAt): array
    {
        $log['doku_invoice_id'] = $invoice;
        $log['doku_invoice_ids'] = array_values(array_unique(array_merge((array) ($log['doku_invoice_ids'] ?? []), [$invoice])));
        $log['doku_sessions'] = (array) ($log['doku_sessions'] ?? []);
        $log['doku_sessions'][$invoice] = [
            'method' => strtoupper($method),
            'amount' => round($amount, 2),
            'payment_url' => $paymentUrl,
            'token_id' => $tokenId,
            'request_id' => $requestId,
            'expires_at' => $expiresAt?->toIso8601String(),
            'status' => self::ACTIVE,
            'created_at' => now()->toIso8601String(),
        ];

        return $log;
    }

    /**
     * Catat sesi DOKU SNAP (Modul 27b) sebagai sesi aktif tagihan — format sama dengan record() + data SNAP (teks QR /
     * nomor VA, referensi DOKU) dan 'api' => 'SNAP'. Tanpa payment_url: halaman bayarnya buatan kita sendiri.
     */
    public static function recordSnap(array $log, string $invoice, string $method, float $amount, array $snap): array
    {
        $log['doku_invoice_id'] = $invoice;
        $log['doku_invoice_ids'] = array_values(array_unique(array_merge((array) ($log['doku_invoice_ids'] ?? []), [$invoice])));
        $log['doku_sessions'] = (array) ($log['doku_sessions'] ?? []);
        $log['doku_sessions'][$invoice] = array_merge($snap, [
            'method' => strtoupper($method),
            'amount' => round($amount, 2),
            'payment_url' => null,
            'status' => self::ACTIVE,
            'created_at' => now()->toIso8601String(),
        ]);

        return $log;
    }

    /**
     * Tandai satu sesi CLOSED di tagihan yang masih PENDING (transaksi sendiri, dikunci). Dipakai setelah DOKU SNAP
     * MENERIMA pembatalan (VA dihapus / QR di-expire) tapi transaksi pemanggilnya di-rollback — mis. ganti metode:
     * VA lama sudah dihapus di DOKU lalu VA baru gagal dibuat. Tanpa ini sesi yang sudah mati tercatat ACTIVE lagi,
     * nomor VA mati tampil di invoice & dipakai ulang (cek status VA DOKU tetap menjawab "pending" untuk VA terhapus).
     */
    public static function markClosed(string $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            $found = self::findBill($invoice);
            $bill = $found ? Payment::whereKey($found->id)->lockForUpdate()->first() : null;
            if (! $bill || $bill->status !== 'PENDING') {
                return;
            }
            $log = self::log($bill);
            $status = $log['doku_sessions'][$invoice]['status'] ?? null;
            if (! isset($log['doku_sessions'][$invoice]) || in_array($status, self::DEAD, true)) {
                return;
            }
            $bill->update(['payload_log' => self::withStatus($log, $invoice, self::CLOSED)]);
        });
    }

    /** Respons "pakai lagi sesi ini" — bentuknya sama dengan hasil pembuatan sesi (OnlineGatewayRouter::createSession). */
    public static function reusedPayment(array $session): array
    {
        if (\App\Services\Payment\Doku\Snap\DokuSnapService::isSnapSession($session)) {
            return [
                'driver' => 'doku',
                'snap_token' => null,
                'redirect_url' => null,
                'payment_url' => null,
                'checkout_mode' => 'SNAP',
                'is_mock' => false,
                'reused' => true,
            ];
        }

        return [
            'driver' => 'doku',
            'snap_token' => null,
            'redirect_url' => $session['payment_url'] ?? null,
            'payment_url' => $session['payment_url'] ?? null,
            'checkout_mode' => 'REDIRECT',
            'is_mock' => false,
            'reused' => true,
        ];
    }

    public static function expiresAt(array $session): ?Carbon
    {
        return empty($session['expires_at']) ? null : Carbon::parse($session['expires_at']);
    }

    /**
     * Setelah tagihan lunas lewat satu sesi: tutup sesi lain tagihan itu supaya tidak bisa dibayar lagi.
     * Sesi yang ternyata ikut dibayar dibiarkan — webhook / sweepPaidSiblings mencatatnya sebagai pembayaran ganda.
     */
    public function closeSiblings(string $paidBillId): void
    {
        $bill = Payment::find($paidBillId);
        if (! $bill || $bill->status !== 'SUCCESS') {
            return;
        }

        $results = [];
        foreach ($this->openSiblings($bill) as $invoice) {
            $meta = self::session($bill, $invoice) ?? [];
            $closed = $this->checkout->close($invoice, $meta['request_id'] ?? null, self::expiresAt($meta));
            if (in_array($closed['result'], [DokuCheckoutService::CLOSED, DokuCheckoutService::NOT_FOUND], true)) {
                $results[$invoice] = self::CLOSED;
            }
        }

        $this->markSessions($paidBillId, $results);
    }

    /**
     * Rekonsiliasi berkala: sesi lain dari tagihan DOKU yang lunas dalam 48 jam terakhir. Sesi yang ternyata ikut
     * dibayar (webhook-nya hilang) dicatat sebagai pembayaran ganda + refund PENDING; yang masih menunggu ditutup.
     *
     * @return int jumlah pembayaran ganda yang baru ditemukan
     */
    public function sweepPaidSiblings(DokuReconciliationService $reconciler): int
    {
        if (! $this->checkout->isConfigured()) {
            return 0;
        }

        $found = 0;
        // Index payments(payment_gateway, status, updated_at); order dimuat sekaligus (bukan satu query per tagihan).
        $bills = Payment::with('order')
            ->where('status', 'SUCCESS')
            ->where('payment_gateway', 'DOKU')
            ->where('updated_at', '>=', now()->subHours(self::SIBLING_WATCH_HOURS))
            ->get()
            ->filter(fn (Payment $bill) => count(self::sessionsOf($bill)) > 1);

        foreach ($bills as $bill) {
            $results = [];
            foreach ($this->openSiblings($bill) as $invoice) {
                $meta = self::session($bill, $invoice) ?? [];
                $state = $this->checkout->state($invoice, self::expiresAt($meta));

                if ($state['state'] === DokuCheckoutService::PAID && $bill->order) {
                    $reconciler->settle($bill->order, $invoice, $state['data'] ?? [], 'DOKU_STATUS_API');
                    $results[$invoice] = self::DUPLICATE;
                    $found++;
                } elseif ($state['state'] === DokuCheckoutService::PENDING) {
                    if ($this->checkout->close($invoice, $meta['request_id'] ?? null, self::expiresAt($meta))['result'] === DokuCheckoutService::CLOSED) {
                        $results[$invoice] = self::CLOSED;
                    }
                } elseif (in_array($state['state'], [DokuCheckoutService::CLOSED, DokuCheckoutService::NOT_FOUND], true)) {
                    $results[$invoice] = self::CLOSED;
                }
            }

            $this->markSessions($bill->id, $results);
        }

        return $found;
    }

    /** Notifikasi / status EXPIRED: sesi ditandai mati. Sesi yang sudah lunas / tercatat ganda tidak pernah diturunkan. */
    public function markExpired(string $invoice): void
    {
        $bill = self::findBill($invoice);
        if (! $bill || Payment::where('order_id', $bill->order_id)->where('transaction_id', $invoice)
            ->whereIn('status', ['SUCCESS', \App\Services\Payment\PaymentOrchestratorService::DUPLICATE_STATUS])->exists()) {
            return;
        }

        $current = self::session($bill, $invoice)['status'] ?? null;
        if ($current === self::ACTIVE) {
            $this->markSessions($bill->id, [$invoice => self::EXPIRED]);
        }
    }

    private function matches(array $meta, string $method, float $amount): bool
    {
        $expiresAt = self::expiresAt($meta);

        return ($meta['status'] ?? self::ACTIVE) === self::ACTIVE
            && strtoupper((string) ($meta['method'] ?? '')) === strtoupper($method)
            && abs((float) ($meta['amount'] ?? 0) - $amount) < 1
            // Sesi SNAP tidak punya halaman DOKU — yang dipakai lagi teks QR / nomor VA-nya.
            && (! empty($meta['payment_url']) || (\App\Services\Payment\Doku\Snap\DokuSnapService::isSnapSession($meta) && (! empty($meta['qr_content']) || ! empty($meta['va_number']))))
            && ($expiresAt === null || $expiresAt->gt(now()->addMinute()));
    }

    /** Sesi selain sesi yang melunasi tagihan, yang belum pasti mati / belum tercatat. */
    private function openSiblings(Payment $bill): array
    {
        $log = self::log($bill);
        $recorded = Payment::where('order_id', $bill->order_id)->pluck('transaction_id')->filter()->all();

        return array_values(array_filter(
            self::sessionsOf($bill),
            fn (string $id) => $id !== $bill->transaction_id
                && ! in_array($id, $recorded, true)
                && ! in_array($log['doku_sessions'][$id]['status'] ?? null, self::DEAD, true),
        ));
    }

    private function markSessions(string $billId, array $statuses): void
    {
        if ($statuses === []) {
            return;
        }

        DB::transaction(function () use ($billId, $statuses) {
            $bill = Payment::whereKey($billId)->lockForUpdate()->first();
            if (! $bill) {
                return;
            }
            $log = self::log($bill);
            foreach ($statuses as $invoice => $status) {
                // Status tidak pernah mundur: sesi yang sudah tercatat ganda tetap DUPLICATE.
                if (($log['doku_sessions'][$invoice]['status'] ?? null) === self::DUPLICATE && $status !== self::DUPLICATE) {
                    continue;
                }
                $log = self::withStatus($log, $invoice, $status);
            }
            $bill->update(['payload_log' => $log]);
        });
    }

    private static function withStatus(array $log, string $invoice, string $status): array
    {
        $log['doku_sessions'] = (array) ($log['doku_sessions'] ?? []);
        $log['doku_sessions'][$invoice] = array_merge((array) ($log['doku_sessions'][$invoice] ?? []), [
            'status' => $status,
            'closed_at' => now()->toIso8601String(),
        ]);

        return $log;
    }

    private static function log(Payment $bill): array
    {
        return is_array($bill->payload_log) ? $bill->payload_log : [];
    }
}
