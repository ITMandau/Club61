<?php

namespace App\Services\Payment;

use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Cadangan webhook: tanya status order langsung ke Midtrans lalu lunasi lewat jalur yang SAMA
 * dengan webhook (PaymentOrchestratorService::markOrderAsPaid — idempoten, jadi aman kalau
 * webhook asli ternyata datang juga belakangan).
 *
 * Hasil:
 *   PAID      — Midtrans bilang lunas (order sudah/berhasil dilunasi di sistem)
 *   PENDING   — Midtrans masih menunggu pembayaran customer
 *   NOT_PAID  — Midtrans bilang expire/cancel/deny, atau tidak kenal order ini sama sekali
 *   ERROR     — Midtrans tidak bisa dihubungi; JANGAN diartikan "belum bayar"
 */
class MidtransReconciliationService
{
    public const PAID = 'PAID';

    public const PENDING = 'PENDING';

    public const NOT_PAID = 'NOT_PAID';

    public const ERROR = 'ERROR';

    public function __construct(
        protected MidtransService $midtrans,
        protected PaymentOrchestratorService $orchestrator,
    ) {}

    /**
     * @param  int  $cacheSeconds  >0 = pakai hasil cek sebelumnya selama N detik (hemat panggilan
     *                             API untuk jalur yang sering dipanggil: polling invoice, halaman publik).
     */
    public function reconcileOrder(Order $order, int $cacheSeconds = 0): string
    {
        // Order PAID yang masih punya pembayaran Midtrans PENDING = tagihan selisih reschedule yang
        // sedang dibayar customer — tetap harus dicek, jangan dianggap selesai.
        $hasPendingGatewayPayment = $this->hasOnlinePaymentAttempt($order);

        // Tidak ada percobaan bayar online sama sekali: jawabannya ada di database kita, Midtrans tidak perlu
        // ditanya. Tagihan kasir yang masih terbuka = BELUM lunas (dulu dijawab PAID karena order-nya tetap PAID,
        // sehingga "Cek Midtrans" bilang "tiket sudah aktif" padahal selisihnya belum dibayar).
        if (! $hasPendingGatewayPayment && in_array($order->payment_status, ['PAID', 'PARTIALLY_PAID'], true)) {
            return Payment::where('order_id', $order->id)->where('status', 'PENDING')->exists() ? self::NOT_PAID : self::PAID;
        }

        if (! $this->midtrans->isConfigured()) {
            return self::NOT_PAID;
        }

        if ($cacheSeconds > 0) {
            $cached = Cache::get($this->cacheKey($order));
            if ($cached !== null) {
                return $cached;
            }
        }

        $result = $this->check($order);

        if ($cacheSeconds > 0) {
            Cache::put($this->cacheKey($order), $result, $cacheSeconds);
        }

        return $result;
    }

    /**
     * Ada tagihan PENDING yang pernah dicoba dibayar lewat Midtrans (gateway MIDTRANS, atau sudah punya
     * order_id Midtrans walau gateway-nya dikembalikan ke kasir setelah sesi Snap ditinggal)?
     * Dipakai juga oleh pelunasan kasir: WAJIB cek Midtrans dulu supaya customer tidak ditagih dua kali.
     */
    public function hasOnlinePaymentAttempt(Order $order): bool
    {
        return Payment::where('order_id', $order->id)
            ->where('status', 'PENDING')
            ->get()
            ->contains(fn (Payment $p) => $p->payment_gateway === 'MIDTRANS'
                || ! empty((is_array($p->payload_log) ? $p->payload_log : [])['midtrans_order_ids'] ?? null));
    }

    /** Semua order_id yang pernah dikirim ke Midtrans untuk order ini (checkout awal + bayar ulang). */
    public function gatewayOrderIds(Order $order): array
    {
        $ids = [$order->order_number];

        $payments = Payment::where('order_id', $order->id)->get();

        // order_id Midtrans yang SUDAH tercatat lunas tidak perlu ditanyakan lagi. Tanpa ini, order yang
        // punya tagihan selisih reschedule akan "menemukan" pembayaran awalnya (settlement) dan
        // melaporkan PAID, padahal tagihan selisihnya belum dibayar.
        // Pembayaran ganda (DUPLICATE) juga sudah tercatat — jangan ditanyakan & dilunasi ulang.
        $recorded = ['SUCCESS', PaymentOrchestratorService::DUPLICATE_STATUS];
        $alreadySettled = $payments->whereIn('status', $recorded)->pluck('transaction_id')->filter()->all();

        foreach ($payments->whereNotIn('status', $recorded) as $payment) {
            // Id tagihan kasir (SUPP-…) bukan order_id Midtrans — dulu ditanyakan tiap 5 menit dan selalu 404.
            if ($payment->payment_gateway === 'MIDTRANS' && ! str_starts_with((string) $payment->transaction_id, 'SUPP-')) {
                $ids[] = $payment->transaction_id;
            }
            $log = is_array($payment->payload_log) ? $payment->payload_log : [];
            $ids[] = $log['midtrans_order_id'] ?? null;
            foreach ((array) ($log['midtrans_order_ids'] ?? []) as $id) {
                $ids[] = $id;
            }
        }

        return array_values(array_diff(
            array_unique(array_filter($ids, fn ($id) => is_string($id) && $id !== '')),
            $alreadySettled
        ));
    }

    private function check(Order $order): string
    {
        $hadError = false;
        $sawPending = false;
        $finalIds = [];

        // Yang terbaru dulu — bayar ulang ("_<timestamp>") paling mungkin yang benar-benar dibayar.
        foreach (array_reverse($this->gatewayOrderIds($order)) as $gatewayOrderId) {
            $status = $this->midtrans->getTransactionStatus($gatewayOrderId);

            if ($status === null) {
                $hadError = true;

                continue;
            }

            if (($status['status_code'] ?? null) === '404') {
                continue;
            }

            if (($status['order_id'] ?? null) !== $gatewayOrderId) {
                Log::warning("[ALERT] Midtrans status check: order_id respons tidak cocok [{$gatewayOrderId}]", ['response' => $status]);
                $hadError = true;

                continue;
            }

            if (! empty($status['signature_key']) && ! MidtransService::verifySignature(
                $gatewayOrderId,
                (string) ($status['status_code'] ?? ''),
                (string) ($status['gross_amount'] ?? ''),
                (string) $status['signature_key'],
            )) {
                Log::warning("[ALERT] Midtrans status check: signature tidak valid [{$gatewayOrderId}]");
                $hadError = true;

                continue;
            }

            $normalized = MidtransService::normalizeStatus(
                (string) ($status['transaction_status'] ?? ''),
                $status['fraud_status'] ?? null
            );

            if ($normalized === 'PAID') {
                $this->settle($order, $gatewayOrderId, $status);

                return self::PAID;
            }

            if (in_array($normalized, ['PENDING', 'CHALLENGE'], true)) {
                $sawPending = true;
            } elseif ($normalized === 'CANCELLED' && ($status['transaction_status'] ?? null) !== 'deny') {
                // deny bukan akhir: customer boleh mencoba kartu lagi dengan order_id yang sama.
                $finalIds[] = $gatewayOrderId;
            }
        }

        if ($sawPending) {
            return self::PENDING;
        }

        if ($hadError) {
            return self::ERROR;
        }

        $this->closeAbandonedPayments($order, $finalIds);

        return self::NOT_PAID;
    }

    /**
     * Tandai FAILED supaya rekonsiliasi berkala tidak terus menanyakan order yang sudah pasti
     * tidak dibayar (keranjang ditinggal) tiap 5 menit selama 24 jam.
     *   - Midtrans bilang expire/cancel untuk SEMUA sesi tagihan itu → final, tutup sekarang. (Dulu cukup sesi
     *     terakhir — padahal VA dari sesi sebelumnya bisa masih berlaku & dibayar setelah customer ganti metode.)
     *   - Selain itu (404 = customer belum memilih metode, atau masih ada sesi yang belum final) → tutup setelah
     *     batas bayar dari pengaturan + jeda (minimal 30 menit) sejak tagihan terakhir diperbarui. Dulu tetap
     *     30 menit walau batas bayar bisa diatur sampai 60 menit.
     * Finalitas dinilai PER TAGIHAN dari sesi terakhirnya — dulu satu sesi lama yang expire membuat semua
     * tagihan order ditutup, termasuk yang sesi barunya masih dibuka customer.
     */
    private function closeAbandonedPayments(Order $order, array $finalIds): void
    {
        $query = Payment::where('order_id', $order->id)
            ->where('payment_gateway', 'MIDTRANS')
            ->where('status', 'PENDING');

        $openMinutes = max(30, app(\App\Services\Padel\BookingTimeService::class)->paymentWindowMinutes() + \App\Services\Padel\BookingTimeService::GRACE_MINUTES);

        foreach ($query->get() as $payment) {
            $log = is_array($payment->payload_log) ? $payment->payload_log : [];
            $sessions = array_values(array_unique(array_filter(array_merge(
                [str_starts_with((string) $payment->transaction_id, 'SUPP-') ? null : $payment->transaction_id, $log['midtrans_order_id'] ?? null],
                (array) ($log['midtrans_order_ids'] ?? [])
            ))));
            $gatewaySaysFinal = $sessions !== [] && array_diff($sessions, $finalIds) === [];
            $lastTouched = $payment->updated_at ?? $payment->created_at;

            if (! $gatewaySaysFinal && $lastTouched?->gte(now()->subMinutes($openMinutes))) {
                continue;
            }

            // Tagihan selisih reschedule BUKAN keranjang yang boleh ditutup: customer tetap berutang dan
            // booking-nya sudah dibayar sebagian. Sesi Snap yang tidak dibayar cukup dikembalikan jadi
            // tagihan terbuka (bisa dibayar ulang via invoice / di kasir), rekonsiliasi berhenti menanyakannya.
            if (($log['type'] ?? null) === 'RESCHEDULE_PRICE_DELTA') {
                $log['snap_session_closed_by'] = $gatewaySaysFinal ? 'MIDTRANS_STATUS_FINAL' : 'MIDTRANS_NOT_PAID_AFTER_PAYMENT_WINDOW';
                $payment->update(['payment_gateway' => 'CASHIER_POS', 'payload_log' => $log]);

                continue;
            }

            $log['closed_by'] = $gatewaySaysFinal ? 'MIDTRANS_STATUS_FINAL' : 'MIDTRANS_NOT_PAID_AFTER_PAYMENT_WINDOW';
            $payment->update(['status' => 'FAILED', 'payload_log' => $log]);
        }
    }

    private function settle(Order $order, string $gatewayOrderId, array $status): void
    {
        $grossAmount = (float) ($status['gross_amount'] ?? 0);

        // Dibandingkan dengan nominal tagihan PEMILIK sesi ini (bukan "PENDING terbaru" / grand_total order).
        $bill = Payment::where('order_id', $order->id)->where('status', 'PENDING')->get()
            ->first(fn (Payment $p) => PaymentOrchestratorService::ownsGatewayId($p, $gatewayOrderId));
        $expected = $bill !== null ? (float) $bill->amount : (float) $order->grand_total;

        if ($order->grand_total !== null && abs($grossAmount - $expected) > 1) {
            Log::warning("[ALERT] Midtrans status check: gross_amount tidak cocok dengan grand_total order [{$order->order_number}]", [
                'gross_amount_from_midtrans' => $grossAmount,
                'grand_total_in_db' => (float) $order->grand_total,
            ]);
        }

        Log::info("Midtrans status check: order {$order->order_number} ternyata LUNAS di Midtrans ({$gatewayOrderId}) — dilunasi tanpa webhook.");

        $this->orchestrator->markOrderAsPaid($order, [
            'payment_gateway' => 'MIDTRANS',
            'transaction_id' => $gatewayOrderId,
            'payment_method' => strtoupper((string) ($status['payment_type'] ?? 'MIDTRANS')),
            'amount' => $grossAmount ?: (float) $order->grand_total,
            'payload_log' => array_merge(\Illuminate\Support\Arr::except($status, ['signature_key']), ['reconciled_via' => 'MIDTRANS_STATUS_API']),
        ]);

        Cache::forget($this->cacheKey($order));
    }

    private function cacheKey(Order $order): string
    {
        return "midtrans_reconcile:{$order->id}";
    }
}
