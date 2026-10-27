<?php

namespace App\Services\Payment\Doku;

use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Services\Padel\BookingTimeService;
use App\Services\Payment\PaymentOrchestratorService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cek status tagihan DOKU langsung ke DOKU lalu lunasi lewat jalur yang SAMA dengan webhook
 * (PaymentOrchestratorService::markOrderAsPaid — idempoten). Versi DOKU dari MidtransReconciliationService; hasilnya
 * berarti sama: PAID / PENDING / NOT_PAID / ERROR (ERROR = belum pasti, JANGAN diartikan "belum bayar").
 */
class DokuReconciliationService
{
    public const PAID = 'PAID';

    public const PENDING = 'PENDING';

    public const NOT_PAID = 'NOT_PAID';

    public const ERROR = 'ERROR';

    public function __construct(
        protected DokuCheckoutService $checkout,
        protected PaymentOrchestratorService $orchestrator,
    ) {}

    /** Tagihan (status apa pun) yang pernah membuka sesi DOKU — dipakai pengarah untuk memilih gateway order. */
    public static function orderHasDokuTrace(Order $order): bool
    {
        return Payment::where('order_id', $order->id)->get()
            ->contains(fn (Payment $p) => $p->payment_gateway === 'DOKU' || DokuSessionGuard::sessionsOf($p) !== []);
    }

    public function reconcileOrder(Order $order, int $cacheSeconds = 0): string
    {
        $hasPendingGatewayPayment = $this->hasOnlinePaymentAttempt($order);

        // Tidak ada percobaan bayar DOKU yang masih terbuka: jawabannya ada di database kita.
        if (! $hasPendingGatewayPayment && in_array($order->payment_status, ['PAID', 'PARTIALLY_PAID'], true)) {
            return Payment::where('order_id', $order->id)->where('status', 'PENDING')->exists() ? self::NOT_PAID : self::PAID;
        }

        if (! $hasPendingGatewayPayment || ! $this->checkout->isConfigured()) {
            return self::NOT_PAID;
        }

        if ($cacheSeconds > 0 && ($cached = Cache::get($this->cacheKey($order))) !== null) {
            return $cached;
        }

        $result = $this->check($order);

        if ($cacheSeconds > 0) {
            Cache::put($this->cacheKey($order), $result, $cacheSeconds);
        }

        return $result;
    }

    /** Ada tagihan PENDING yang pernah dicoba dibayar lewat DOKU? Wajib dicek sebelum kasir menerima uang. */
    public function hasOnlinePaymentAttempt(Order $order): bool
    {
        return Payment::where('order_id', $order->id)
            ->where('status', 'PENDING')
            ->get()
            ->contains(fn (Payment $p) => $p->payment_gateway === 'DOKU' || DokuSessionGuard::sessionsOf($p) !== []);
    }

    private function check(Order $order): string
    {
        $hadError = false;
        $sawPending = false;
        $finalIds = [];
        $settled = Payment::where('order_id', $order->id)
            ->whereIn('status', ['SUCCESS', PaymentOrchestratorService::DUPLICATE_STATUS])
            ->pluck('transaction_id')->filter()->all();

        foreach (Payment::where('order_id', $order->id)->where('status', 'PENDING')->get() as $bill) {
            // Yang terbaru dulu — sesi setelah ganti metode paling mungkin yang benar-benar dibayar.
            foreach (array_reverse(DokuSessionGuard::sessionsOf($bill)) as $invoice) {
                if (in_array($invoice, $settled, true)) {
                    continue;
                }

                $meta = DokuSessionGuard::session($bill, $invoice) ?? [];
                // Sudah terbukti mati (dibatalkan & dicek status, atau notifikasi EXPIRED bertanda tangan) → final.
                if (in_array($meta['status'] ?? null, [DokuSessionGuard::CLOSED, DokuSessionGuard::EXPIRED], true)) {
                    $finalIds[] = $invoice;

                    continue;
                }

                $state = $this->checkout->state($invoice, DokuSessionGuard::expiresAt($meta));

                switch ($state['state']) {
                    case DokuCheckoutService::PAID:
                        if (! $this->amountMatches($state['data'], $meta, $invoice)) {
                            $hadError = true;
                            break;
                        }
                        $this->settle($order, $invoice, $state['data'] ?? [], 'DOKU_STATUS_API');

                        return self::PAID;
                    case DokuCheckoutService::PENDING:
                        $sawPending = true;
                        break;
                    case DokuCheckoutService::CLOSED:
                        $finalIds[] = $invoice;
                        break;
                    case DokuCheckoutService::REFUNDED:
                        DokuClient::alert("DOKU melaporkan REFUNDED untuk [{$invoice}] order [{$order->order_number}] — cek & tindak lanjuti manual.");
                        $finalIds[] = $invoice;
                        break;
                    default:
                        // ERROR / NOT_FOUND: sesi yang tercatat punya URL dari DOKU, jadi "tidak ditemukan" = janggal.
                        $hadError = true;
                }
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
     * Nominal dari DOKU harus sama dengan nominal sesi yang kita buat. Beda = [ALERT] dan TIDAK dilunasi otomatis
     * (dicek manual) — rekonsiliasi berikutnya tetap melaporkan ERROR supaya slot tidak dilepas diam-diam.
     */
    public function amountMatches(?array $data, array $session, string $invoice): bool
    {
        $paid = (float) ($data['order']['amount'] ?? -1);
        $expected = (float) ($session['amount'] ?? -1);

        if ($expected < 0 || abs($paid - $expected) > 1) {
            DokuClient::alert("DOKU: nominal dibayar tidak cocok dengan tagihan [{$invoice}] — TIDAK dilunasi otomatis, cek manual.", [
                'nominal_doku' => $paid,
                'nominal_tagihan' => $expected,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Tutup tagihan DOKU yang pasti tidak dibayar (semua sesinya final di DOKU, atau batas bayar + jeda lewat). Tagihan
     * selisih reschedule tidak pernah ditutup: dikembalikan jadi tagihan terbuka (bisa dibayar ulang / di kasir).
     */
    private function closeAbandonedPayments(Order $order, array $finalIds): void
    {
        $openMinutes = max(30, app(BookingTimeService::class)->paymentWindowMinutes() + BookingTimeService::GRACE_MINUTES);

        foreach (Payment::where('order_id', $order->id)->where('payment_gateway', 'DOKU')->where('status', 'PENDING')->get() as $payment) {
            $log = is_array($payment->payload_log) ? $payment->payload_log : [];
            $sessions = DokuSessionGuard::sessionsOf($payment);
            $openSessions = array_filter($sessions, fn ($id) => ! in_array($log['doku_sessions'][$id]['status'] ?? null, [DokuSessionGuard::CLOSED, DokuSessionGuard::EXPIRED], true));
            $gatewaySaysFinal = $sessions !== [] && array_diff($openSessions, $finalIds) === [];
            $lastTouched = $payment->updated_at ?? $payment->created_at;

            if (! $gatewaySaysFinal && $lastTouched?->gte(now()->subMinutes($openMinutes))) {
                continue;
            }

            DB::transaction(function () use ($payment, $gatewaySaysFinal) {
                $fresh = Payment::whereKey($payment->id)->lockForUpdate()->first();
                if (! $fresh || $fresh->status !== 'PENDING' || $fresh->payment_gateway !== 'DOKU') {
                    return;
                }
                $log = is_array($fresh->payload_log) ? $fresh->payload_log : [];
                $reason = $gatewaySaysFinal ? 'DOKU_STATUS_FINAL' : 'DOKU_NOT_PAID_AFTER_PAYMENT_WINDOW';

                if (($log['type'] ?? null) === 'RESCHEDULE_PRICE_DELTA') {
                    $log['doku_session_closed_by'] = $reason;
                    $fresh->update(['payment_gateway' => 'CASHIER_POS', 'payload_log' => $log]);

                    return;
                }

                $log['closed_by'] = $reason;
                $fresh->update(['status' => 'FAILED', 'payload_log' => $log]);
            });
        }
    }

    /**
     * Lunasi satu sesi DOKU dari data status / notifikasi yang sudah dikonfirmasi — jalur yang sama dengan webhook
     * (idempoten). Yang disimpan hanya ringkasan pembayaran (tanpa data pribadi customer dari DOKU).
     */
    public function settle(Order $order, string $invoice, array $data, string $via): void
    {
        $bill = DokuSessionGuard::findBill($invoice);
        $session = $bill ? (DokuSessionGuard::session($bill, $invoice) ?? []) : [];
        $amount = (float) ($data['order']['amount'] ?? $session['amount'] ?? 0);

        // Sesi ini sudah lunas (mis. polling duluan, notifikasi menyusul): markOrderAsPaid mengabaikannya — tanpa catatan
        // pembayaran / email baru. Log dibedakan supaya tidak terbaca seperti pelunasan kedua.
        $alreadyPaid = Payment::where('transaction_id', $invoice)->where('status', 'SUCCESS')->exists();
        Log::channel('doku')->info($alreadyPaid
            ? "DOKU: order {$order->order_number} sudah LUNAS lewat {$invoice} sebelumnya — {$via} tidak mengubah apa pun."
            : "DOKU: order {$order->order_number} LUNAS lewat {$invoice} ({$via}).");

        $this->orchestrator->markOrderAsPaid($order, [
            'payment_gateway' => 'DOKU',
            'transaction_id' => $invoice,
            // Kode metode kita (mis. BCA_VA) → label riwayat & struk dari pengaturan Metode Pembayaran Online.
            'payment_method' => strtoupper((string) ($session['method'] ?? $data['channel']['id'] ?? 'DOKU')),
            'amount' => $amount ?: (float) $order->grand_total,
            'payload_log' => array_filter([
                'doku_payment' => self::summary($data),
                // Hanya pelunasan lewat cek status (bukan webhook) yang ditandai "rekonsiliasi" di Log Aktivitas.
                'reconciled_via' => $via === 'DOKU_STATUS_API' ? $via : null,
                'confirmed_via' => $via,
            ], fn ($v) => $v !== null),
        ]);

        Cache::forget($this->cacheKey($order));
    }

    /** Ringkasan pembayaran dari DOKU untuk struk / Buku Transaksi (tanpa data pribadi customer). */
    public static function summary(array $data): array
    {
        return array_filter([
            'invoice_number' => $data['order']['invoice_number'] ?? null,
            'amount' => $data['order']['amount'] ?? null,
            'status' => $data['transaction']['status'] ?? null,
            'date' => $data['transaction']['date'] ?? null,
            'service' => $data['service']['id'] ?? null,
            'acquirer' => $data['acquirer']['id'] ?? null,
            'channel' => $data['channel']['id'] ?? null,
            'va_number' => $data['virtual_account_info']['virtual_account_number'] ?? null,
            'approval_code' => $data['emoney_payment']['approval_code'] ?? null,
        ], fn ($v) => is_scalar($v) && $v !== '');
    }

    private function cacheKey(Order $order): string
    {
        return "doku_reconcile:{$order->id}";
    }
}
