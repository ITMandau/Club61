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
        if ($order->payment_status === 'PAID') {
            return self::PAID;
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

    /** Semua order_id yang pernah dikirim ke Midtrans untuk order ini (checkout awal + bayar ulang). */
    public function gatewayOrderIds(Order $order): array
    {
        $ids = [$order->order_number];

        $payments = Payment::where('order_id', $order->id)
            ->where('payment_gateway', 'MIDTRANS')
            ->get();

        foreach ($payments as $payment) {
            $ids[] = $payment->transaction_id;
            $log = is_array($payment->payload_log) ? $payment->payload_log : [];
            $ids[] = $log['midtrans_order_id'] ?? null;
            foreach ((array) ($log['midtrans_order_ids'] ?? []) as $id) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique(array_filter($ids, fn ($id) => is_string($id) && $id !== '')));
    }

    private function check(Order $order): string
    {
        $hadError = false;
        $sawPending = false;
        $sawFinalNotPaid = false;

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
            } elseif ($normalized === 'CANCELLED') {
                $sawFinalNotPaid = true;
            }
        }

        if ($sawPending) {
            return self::PENDING;
        }

        if ($hadError) {
            return self::ERROR;
        }

        $this->closeAbandonedPayments($order, $sawFinalNotPaid);

        return self::NOT_PAID;
    }

    /**
     * Tandai FAILED supaya rekonsiliasi berkala tidak terus menanyakan order yang sudah pasti
     * tidak dibayar (keranjang ditinggal) tiap 5 menit selama 24 jam.
     *   - Midtrans bilang expire/cancel/deny → final, tutup sekarang.
     *   - Midtrans tidak kenal transaksinya (404) → customer belum memilih metode bayar; belum
     *     tentu final, tutup setelah 30 menit (sesi Snap 15 menit pasti sudah habis).
     */
    private function closeAbandonedPayments(Order $order, bool $gatewaySaysFinal): void
    {
        $query = Payment::where('order_id', $order->id)
            ->where('payment_gateway', 'MIDTRANS')
            ->where('status', 'PENDING');

        if (! $gatewaySaysFinal) {
            $query->where('created_at', '<', now()->subMinutes(30));
        }

        foreach ($query->get() as $payment) {
            $log = is_array($payment->payload_log) ? $payment->payload_log : [];
            $log['closed_by'] = $gatewaySaysFinal ? 'MIDTRANS_STATUS_FINAL' : 'MIDTRANS_NOT_FOUND_AFTER_30_MIN';
            $payment->update(['status' => 'FAILED', 'payload_log' => $log]);
        }
    }

    private function settle(Order $order, string $gatewayOrderId, array $status): void
    {
        $grossAmount = (float) ($status['gross_amount'] ?? 0);

        if ($order->grand_total !== null && abs($grossAmount - (float) $order->grand_total) > 1) {
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
            'payload_log' => array_merge($status, ['reconciled_via' => 'MIDTRANS_STATUS_API']),
        ]);

        Cache::forget($this->cacheKey($order));
    }

    private function cacheKey(Order $order): string
    {
        return "midtrans_reconcile:{$order->id}";
    }
}
