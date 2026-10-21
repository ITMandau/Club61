<?php

namespace App\Services\Payment;

use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Satu tagihan = paling banyak SATU sesi Snap Midtrans yang bisa dibayar pada satu waktu.
 *
 * Dulu setiap klik "Bayar" / ganti metode membuka sesi baru dan sesi lama tetap hidup — customer bisa membayar QRIS
 * baru DAN mentransfer VA lama (uang ketarik dua kali, terdeteksi sebagai pembayaran ganda + refund manual).
 * Sekarang:
 *   - metode & nominal sama, sesi terakhir masih berlaku → sesi itu dipakai lagi (token Snap yang sama);
 *   - ganti metode → semua sesi lama DITUTUP DULU di Midtrans (dibuktikan lewat Status API), baru sesi baru dibuat;
 *     sesi lama ternyata sudah dibayar → tidak ada sesi baru, tagihan dilunasi; Midtrans tidak bisa dihubungi →
 *     tidak ada sesi baru (lebih baik customer menunggu daripada ada dua sesi aktif);
 *   - begitu satu sesi lunas, sesi lain ditutup (closeSiblings) dan disapu rekonsiliasi 48 jam (sweepPaidSiblings).
 *
 * Catatan sesi disimpan di payload_log tagihan: midtrans_sessions[order_id] = {method, amount, snap_token,
 * redirect_url, expires_at, status}. midtrans_order_id / midtrans_order_ids tetap diisi (webhook & rekonsiliasi).
 */
class SnapSessionGuard
{
    /** Sesi pertama + 5x ganti metode. */
    public const MAX_SESSIONS = 6;

    /** Sesi lain dari tagihan yang sudah lunas tetap dicek selama ini (VA lama bisa ditransfer belakangan). */
    public const SIBLING_WATCH_HOURS = 48;

    public const ACTIVE = 'ACTIVE';

    public const CLOSED = 'CLOSED';

    public const DUPLICATE = 'DUPLICATE';

    public const REUSE = 'REUSE';

    public const PAID = 'PAID';

    public const CREATE = 'CREATE';

    public function __construct(protected MidtransService $midtrans) {}

    /** order_id Midtrans milik tagihan, urut lama → baru. Id tagihan kasir (SUPP- / POS-) bukan sesi Midtrans. */
    public static function sessionsOf(Payment $bill): array
    {
        $log = self::log($bill);
        $tx = (string) $bill->transaction_id;

        $ids = array_merge(
            [$tx !== '' && ! str_starts_with($tx, 'SUPP-') && ! str_starts_with($tx, 'POS-') ? $tx : null],
            (array) ($log['midtrans_order_ids'] ?? []),
            [$log['midtrans_order_id'] ?? null],
        );

        return array_values(array_unique(array_filter($ids, fn ($id) => is_string($id) && $id !== '')));
    }

    /**
     * Dipanggil DI DALAM transaksi yang sudah mengunci tagihan, sebelum membuat sesi Snap baru.
     *
     * @return array{action: string, session_id?: string, session?: array, status?: ?array}
     *   REUSE  — pakai lagi sesi `session_id` (data di `session`)
     *   PAID   — sesi `session_id` ternyata sudah dibayar (`status` = data Status API) → lunasi, jangan buat sesi
     *   CREATE — boleh membuat sesi baru (sesi lama sudah ditutup)
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
        $latest = $log['midtrans_order_id'] ?? (end($sessions) ?: null);
        $meta = $latest ? ($log['midtrans_sessions'][$latest] ?? null) : null;

        if ($latest && is_array($meta) && $this->matches($meta, $method, $amount)) {
            $remote = $this->remoteState($latest);
            if ($remote['state'] === self::PAID) {
                return ['action' => self::PAID, 'session_id' => $latest, 'status' => $remote['status']];
            }
            if ($remote['state'] === 'OPEN') {
                return ['action' => self::REUSE, 'session_id' => $latest, 'session' => $meta];
            }
            // Sesi itu sudah mati di Midtrans → tidak bisa dipakai lagi, lanjut buat sesi baru.
        }

        // Batas dicek SEBELUM menutup apa pun: customer yang kena batas tetap bisa membayar sesi terakhirnya.
        if (count($sessions) >= self::MAX_SESSIONS) {
            throw new HttpException(429, 'Metode pembayaran sudah diganti terlalu sering untuk tagihan ini. Selesaikan dengan metode terakhir, atau hubungi front desk Club 61.');
        }

        foreach ($sessions as $sessionId) {
            if (in_array($log['midtrans_sessions'][$sessionId]['status'] ?? null, [self::CLOSED, self::DUPLICATE], true)) {
                continue;
            }

            $closed = $this->midtrans->closeTransaction($sessionId);

            if ($closed['result'] === MidtransService::CLOSE_PAID) {
                return ['action' => self::PAID, 'session_id' => $sessionId, 'status' => $closed['status']];
            }
            if ($closed['result'] === MidtransService::CLOSE_FAILED) {
                throw new HttpException(503, 'Pembayaran sebelumnya belum bisa ditutup karena layanan pembayaran sedang tidak bisa dihubungi. Coba lagi beberapa saat — jangan bayar memakai kode / VA yang lama.');
            }

            // Popup yang belum pernah dibuka (Midtrans belum mengenalnya) TIDAK bisa dimatikan dan masih bisa dibuka
            // sampai masa berlakunya habis. Dulu langsung dicatat CLOSED → tidak diawasi lagi (bayar dobel tanpa
            // terdeteksi kalau webhook-nya hilang). Sekarang tetap ACTIVE: dibuka → notifikasi pending → closeIfStale
            // menutupnya; dibayar → tercatat ganda + refund; sweepPaidSiblings mengawasinya sampai kedaluwarsa.
            if ($closed['result'] === MidtransService::CLOSE_NOT_FOUND && $this->mayStillOpen($bill, $sessionId)) {
                continue;
            }

            $log = self::withStatus($log, $sessionId, self::CLOSED);
        }

        $bill->update(['payload_log' => $log]);

        return ['action' => self::CREATE];
    }

    /** Catat sesi baru sebagai sesi aktif tagihan. */
    public static function record(array $log, string $sessionId, string $method, float $amount, ?string $snapToken, ?string $redirectUrl, ?CarbonInterface $expiresAt): array
    {
        $log['midtrans_order_id'] = $sessionId;
        $log['midtrans_order_ids'] = array_values(array_unique(array_merge((array) ($log['midtrans_order_ids'] ?? []), [$sessionId])));
        $log['snap_token'] = $snapToken;
        $log['midtrans_sessions'] = (array) ($log['midtrans_sessions'] ?? []);
        $log['midtrans_sessions'][$sessionId] = [
            'method' => strtoupper($method),
            'amount' => round($amount, 2),
            'snap_token' => $snapToken,
            'redirect_url' => $redirectUrl,
            'expires_at' => $expiresAt?->toIso8601String(),
            'status' => self::ACTIVE,
            'created_at' => now()->toIso8601String(),
        ];

        return $log;
    }

    /** Respons "pakai lagi sesi ini" — bentuknya sama dengan hasil PaymentManager::createPayment. */
    public static function reusedPayment(array $session): array
    {
        return [
            'snap_token' => $session['snap_token'] ?? null,
            'redirect_url' => $session['redirect_url'] ?? null,
            'payment_url' => $session['redirect_url'] ?? null,
            'is_mock' => false,
            'reused' => true,
        ];
    }

    /** Sisa menit sesi yang dipakai lagi (untuk timer di halaman). */
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
        foreach ($this->openSiblings($bill) as $sessionId) {
            $closed = $this->midtrans->closeTransaction($sessionId);
            if (in_array($closed['result'], [MidtransService::CLOSE_CLOSED, MidtransService::CLOSE_NOT_FOUND], true)
                && ! ($closed['result'] === MidtransService::CLOSE_NOT_FOUND && $this->mayStillOpen($bill, $sessionId))) {
                $results[$sessionId] = self::CLOSED;
            }
        }

        $this->markSessions($paidBillId, $results);
    }

    /**
     * Rekonsiliasi berkala: sesi lain dari tagihan yang lunas dalam 48 jam terakhir. Sesi yang ternyata ikut dibayar
     * (webhook-nya hilang) dicatat sebagai pembayaran ganda + refund PENDING; yang masih menunggu ditutup.
     *
     * @return int jumlah pembayaran ganda yang baru ditemukan
     */
    public function sweepPaidSiblings(MidtransReconciliationService $reconciler): int
    {
        if (! $this->midtrans->isConfigured()) {
            return 0;
        }

        $found = 0;
        $bills = Payment::where('status', 'SUCCESS')
            ->where('payment_gateway', 'MIDTRANS')
            ->where('updated_at', '>=', now()->subHours(self::SIBLING_WATCH_HOURS))
            ->get()
            ->filter(fn (Payment $bill) => count(self::sessionsOf($bill)) > 1);

        foreach ($bills as $bill) {
            $results = [];
            foreach ($this->openSiblings($bill) as $sessionId) {
                $status = $this->midtrans->getTransactionStatus($sessionId);
                $normalized = $status === null ? null : MidtransService::normalizeStatus((string) ($status['transaction_status'] ?? ''), $status['fraud_status'] ?? null);

                if ($normalized === 'PAID' && ($status['order_id'] ?? null) === $sessionId && $bill->order) {
                    if ($reconciler->settle($bill->order, $sessionId, $status)) {
                        $results[$sessionId] = self::DUPLICATE;
                        $found++;
                    }
                } elseif (in_array($normalized, ['PENDING', 'CHALLENGE'], true)) {
                    if ($this->midtrans->closeTransaction($sessionId)['result'] === MidtransService::CLOSE_CLOSED) {
                        $results[$sessionId] = self::CLOSED;
                    }
                } elseif ($normalized === 'CANCELLED' || (($status['status_code'] ?? null) === '404' && ! $this->mayStillOpen($bill, $sessionId))) {
                    $results[$sessionId] = self::CLOSED;
                }
            }

            $this->markSessions($bill->id, $results);
        }

        return $found;
    }

    /**
     * Booking dilepas / hangus / dibatalkan: tutup semua sesi tagihan ini yang masih bisa dibayar. Best-effort, dipanggil
     * di LUAR transaksi DB. Sesi yang masa berlakunya sudah lewat tidak ditanyakan (Midtrans sudah menolaknya).
     * Dulu hanya sesi bernomor order polos yang dibatalkan — sesi bayar ulang / ganti metode tetap hidup.
     *
     * @param  array<int, string>  $skip  sesi yang sudah dibatalkan pemanggil
     */
    public function closeOpenSessions(Payment $bill, array $skip = []): void
    {
        if (! $this->midtrans->isConfigured()) {
            return;
        }

        $log = self::log($bill);
        $results = [];
        foreach (self::sessionsOf($bill) as $sessionId) {
            $meta = (array) ($log['midtrans_sessions'][$sessionId] ?? []);
            if (in_array($sessionId, $skip, true) || in_array($meta['status'] ?? null, [self::CLOSED, self::DUPLICATE], true)) {
                continue;
            }
            $expiresAt = self::expiresAt($meta);
            if ($expiresAt !== null && $expiresAt->isPast()) {
                continue;
            }

            try {
                $closed = $this->midtrans->closeTransaction($sessionId);
            } catch (\Throwable $e) {
                continue;
            }

            if ($closed['result'] === MidtransService::CLOSE_CLOSED
                || ($closed['result'] === MidtransService::CLOSE_NOT_FOUND && ! $this->mayStillOpen($bill, $sessionId))) {
                $results[$sessionId] = self::CLOSED;
            }
        }

        $this->markSessions($bill->id, $results);
    }

    /**
     * Nominal yang diminta ke Midtrans untuk sesi ini: catatan sesi → tagihan pemilik sesi (status apa pun).
     * Null = sesi ini tidak dimiliki tagihan mana pun (data lama / sesi yang tidak sempat tercatat).
     */
    public static function expectedAmount(Order $order, string $sessionId): ?float
    {
        $bill = Payment::where('order_id', $order->id)->get()
            ->first(fn (Payment $p) => PaymentOrchestratorService::ownsGatewayId($p, $sessionId));
        if (! $bill) {
            return null;
        }

        return (float) (self::log($bill)['midtrans_sessions'][$sessionId]['amount'] ?? $bill->amount);
    }

    /**
     * Nominal yang dibayar ≠ nominal yang ditagihkan untuk sesi ini → JANGAN dilunasi (booking / membership tidak aktif),
     * catat KRITIS sekali per sesi. Disamakan dengan DOKU — dulu Midtrans hanya mencatat log lalu tetap melunasi.
     *
     * Sesi yang tidak dimiliki tagihan mana pun tidak punya acuan nominal: uangnya tetap dicatat (kelebihan bayar →
     * refund PENDING; kurang bayar → order PARTIALLY_PAID, tiket / kartu / slip tidak aktif) + alert — menolaknya justru
     * membuat uang yang sudah masuk tidak tercatat sama sekali.
     *
     * @return bool true = boleh dilunasi
     */
    public static function amountMatches(Order $order, string $sessionId, float $paid, string $source): bool
    {
        $expected = self::expectedAmount($order, $sessionId);
        if ($expected === null) {
            Log::warning("[ALERT] Pembayaran Midtrans untuk sesi yang tidak dikenal tagihan mana pun [{$order->order_number}] ({$sessionId}) — dicatat, cek manual.", [
                'order_id' => $order->id,
                'dibayar' => $paid,
                'grand_total' => (float) $order->grand_total,
                'sumber' => $source,
            ]);

            return true;
        }
        if (abs($paid - $expected) <= 1) {
            return true;
        }

        if (\Illuminate\Support\Facades\Cache::add("midtrans_amount_mismatch:{$sessionId}", true, now()->addDay())) {
            Log::critical("[ALERT] Pembayaran Midtrans DITOLAK: nominal dibayar tidak sama dengan tagihan [{$order->order_number}] ({$sessionId}) — order tidak dilunasi, cek manual di dashboard Midtrans.", [
                'order_id' => $order->id,
                'dibayar' => $paid,
                'ditagihkan' => $expected,
                'sumber' => $source,
            ]);
            \App\Services\Audit\ActivityLogger::record(
                module: 'FINANCE',
                event: 'payment.amount_mismatch',
                description: 'Pembayaran Midtrans '.\App\Services\Audit\ActivityLogger::rupiah($paid).' untuk order '.$order->order_number.' DITOLAK: tagihan '.\App\Services\Audit\ActivityLogger::rupiah($expected).' — cek manual',
                subject: $order,
                meta: ['no_order' => $order->order_number, 'sesi' => $sessionId, 'dibayar' => $paid, 'ditagihkan' => $expected, 'sumber' => $source],
                severity: \App\Services\Audit\ActivityLogger::CRITICAL,
                asSystem: true,
            );
        }

        return false;
    }

    /** Notifikasi "pending" untuk sesi yang seharusnya sudah mati (popup lama di tab lain) → langsung ditutup. */
    public function closeIfStale(Order $order, string $sessionId): void
    {
        $bill = Payment::where('order_id', $order->id)->get()
            ->first(fn (Payment $p) => PaymentOrchestratorService::ownsGatewayId($p, $sessionId));
        if (! $bill) {
            return;
        }

        $log = self::log($bill);
        $latest = $log['midtrans_order_id'] ?? $bill->transaction_id;
        $stale = in_array($log['midtrans_sessions'][$sessionId]['status'] ?? null, [self::CLOSED, self::DUPLICATE], true)
            || ($bill->status === 'PENDING' && $latest !== $sessionId)
            || ($bill->status !== 'PENDING' && $bill->transaction_id !== $sessionId);

        if (! $stale) {
            return;
        }

        $closed = $this->midtrans->closeTransaction($sessionId);
        Log::warning("Sesi Midtrans lama [{$sessionId}] order [{$order->order_number}] dipakai lagi (notifikasi pending) — ditutup: {$closed['result']}.");
        if ($closed['result'] === MidtransService::CLOSE_CLOSED) {
            $this->markSessions($bill->id, [$sessionId => self::CLOSED]);
        }
    }

    private function matches(array $meta, string $method, float $amount): bool
    {
        $expiresAt = self::expiresAt($meta);

        return ($meta['status'] ?? self::ACTIVE) === self::ACTIVE
            && strtoupper((string) ($meta['method'] ?? '')) === strtoupper($method)
            && abs((float) ($meta['amount'] ?? 0) - $amount) < 1
            && ! empty($meta['snap_token'])
            && ($expiresAt === null || $expiresAt->gt(now()->addMinute()));
    }

    /** @return array{state: string, status: ?array} OPEN (masih bisa dibayar / belum dibuka), PAID, CLOSED */
    private function remoteState(string $sessionId): array
    {
        if (! $this->midtrans->isConfigured()) {
            return ['state' => 'OPEN', 'status' => null];
        }

        $status = $this->midtrans->getTransactionStatus($sessionId);
        // Tidak bisa dicek → tetap pakai sesi yang sama (tidak menambah sesi baru).
        if ($status === null || (string) ($status['status_code'] ?? '') === '404') {
            return ['state' => 'OPEN', 'status' => $status];
        }

        return match (MidtransService::normalizeStatus((string) ($status['transaction_status'] ?? ''), $status['fraud_status'] ?? null)) {
            'PAID' => ['state' => self::PAID, 'status' => $status],
            'PENDING', 'CHALLENGE' => ['state' => 'OPEN', 'status' => $status],
            default => ['state' => self::CLOSED, 'status' => $status],
        };
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
                && ! in_array($log['midtrans_sessions'][$id]['status'] ?? null, [self::CLOSED, self::DUPLICATE], true),
        ));
    }

    /** Sesi yang belum dikenal Midtrans (popup belum dibuka) masih bisa dibuka sampai masa berlakunya habis. */
    private function mayStillOpen(Payment $bill, string $sessionId): bool
    {
        $expiresAt = self::expiresAt(self::log($bill)['midtrans_sessions'][$sessionId] ?? []);

        return $expiresAt === null ? $bill->updated_at?->gt(now()->subDay()) ?? false : $expiresAt->isFuture();
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
            foreach ($statuses as $sessionId => $status) {
                $log = self::withStatus($log, $sessionId, $status);
            }
            $bill->update(['payload_log' => $log]);
        });
    }

    private static function withStatus(array $log, string $sessionId, string $status): array
    {
        $log['midtrans_sessions'] = (array) ($log['midtrans_sessions'] ?? []);
        $log['midtrans_sessions'][$sessionId] = array_merge((array) ($log['midtrans_sessions'][$sessionId] ?? []), [
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
