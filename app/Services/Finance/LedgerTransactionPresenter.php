<?php

namespace App\Services\Finance;

use App\Filament\Pages\BookOfflineCourt;
use App\Models\Audit\ActivityLog;
use App\Models\Finance\LedgerEntry;
use App\Models\Padel\PadelBooking;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\Pos\PosCashierShift;
use App\Models\Pos\Refund;
use App\Services\Padel\PadelBookingService;
use App\Services\Payment\PaymentOrchestratorService;
use Illuminate\Support\Collection;

/**
 * Data untuk panel detail transaksi & invoice salinan admin di Buku Transaksi (Modul 17 FR-04).
 * Bukti bayar diambil per kolom yang dikenal — payload_log mentah gateway tidak pernah ditampilkan utuh.
 */
class LedgerTransactionPresenter
{
    /** Semua baris buku satu transaksi (pembayaran / refund) dari salah satu barisnya. */
    public function rowsOf(LedgerEntry $row): Collection
    {
        return LedgerEntry::query()
            ->where('entry_type', $row->entry_type)
            ->when($row->refund_id, fn ($q) => $q->where('refund_id', $row->refund_id), fn ($q) => $q->whereNull('refund_id')->where('payment_id', $row->payment_id))
            ->orderByDesc('net_amount')
            ->get();
    }

    public function detail(LedgerEntry $row, bool $withActivity): array
    {
        $rows = $this->rowsOf($row);
        $head = $rows->first() ?? $row;
        $order = Order::withTrashed()->with(['items', 'user'])->find($head->order_id);
        $payment = $head->payment_id ? Payment::find($head->payment_id) : null;

        return [
            'head' => $head,
            'rows' => $rows,
            'totals' => collect(LedgerReport::MONEY_COLUMNS)->mapWithKeys(fn ($c) => [$c => (float) $rows->sum($c)])->all(),
            'order' => $order,
            'items' => $order?->items ?? collect(),
            'shift_number' => $head->pos_shift_id ? PosCashierShift::whereKey($head->pos_shift_id)->value('shift_number') : null,
            'proof' => $payment ? $this->proof($payment) : [],
            'refund' => $head->refund_id ? Refund::with('processedBy:id,name')->find($head->refund_id) : null,
            'payments' => $order ? $this->payments($order) : [],
            'refunds' => $order ? Refund::with('processedBy:id,name')->where('order_id', $order->id)->orderBy('created_at')->get() : collect(),
            'bookings' => $order ? PadelBooking::with('court:id,name')->where('order_id', $order->id)->orderBy('start_time')->get() : collect(),
            'activity' => $withActivity && $order
                ? ActivityLog::query()->where('subject_type', 'Order')->where('subject_id', $order->id)->orderByDesc('created_at')->limit(10)->get()
                : null,
        ];
    }

    /** Semua tagihan / pembayaran order berurutan. */
    public function payments(Order $order): array
    {
        return Payment::where('order_id', $order->id)->orderBy('created_at')->orderBy('id')->get()
            ->map(function (Payment $p) {
                $log = is_array($p->payload_log) ? $p->payload_log : [];

                return [
                    'transaction_id' => $p->transaction_id,
                    'kind' => match (true) {
                        ($log['type'] ?? null) === 'RESCHEDULE_PRICE_DELTA' => 'Pelunasan selisih reschedule',
                        $p->status === PaymentOrchestratorService::DUPLICATE_STATUS => 'Pembayaran ganda',
                        ! empty($log['received_after_bill_closed']) => 'Uang masuk setelah tagihan ditutup',
                        default => 'Pembayaran',
                    },
                    'status' => $p->status,
                    'amount' => (float) $p->amount,
                    'method' => $this->methodLabel($p),
                    'paid_at' => $p->paid_at,
                    'created_at' => $p->created_at,
                    'proof' => $this->proof($p),
                ];
            })->all();
    }

    /** @return array<string, string> label → nilai */
    public function proof(Payment $payment): array
    {
        $log = is_array($payment->payload_log) ? $payment->payload_log : [];
        $edc = is_array($log['edc_details'] ?? null) ? $log['edc_details'] : [];
        $qris = is_array($log['qris_details'] ?? null) ? $log['qris_details'] : [];

        return array_filter([
            'ID Transaksi' => $payment->transaction_id,
            'Gateway' => $payment->payment_gateway,
            'Penyedia QRIS' => $qris['provider'] ?? $qris['qris_provider'] ?? null,
            'RRN QRIS' => $qris['rrn'] ?? $qris['qris_rrn'] ?? null,
            'Mesin EDC' => $edc['terminal'] ?? null,
            'Kartu' => ! empty($edc['card_last_4']) ? trim(($edc['card_type'] ?? '').' '.($edc['card_issuer'] ?? '').' •••'.$edc['card_last_4']) : null,
            'Approval Code' => $edc['approval_code'] ?? null,
            'Trace Number' => $edc['trace_number'] ?? null,
            'Nomor VA' => $log['va_numbers'][0]['va_number'] ?? $log['permata_va_number'] ?? null,
            'ID Midtrans' => is_string($log['transaction_id'] ?? null) ? $log['transaction_id'] : null,
            'Tipe Midtrans' => is_string($log['payment_type'] ?? null) ? $log['payment_type'] : null,
            'Dilunasi oleh' => $log['settled_by_name'] ?? $log['cashier_name'] ?? null,
        ], fn ($v) => filled($v) && is_scalar($v));
    }

    public function methodLabel(Payment $payment): string
    {
        try {
            return app(PadelBookingService::class)->formatPaymentMethodLabel($payment->payment_method, is_array($payment->payload_log) ? $payment->payload_log : []);
        } catch (\Throwable) {
            return (string) $payment->payment_method;
        }
    }

    /** Pembayaran di kasir padel (walk-in / pelunasan / booking online yang dilunasi di frontdesk) memakai struk POS. */
    public function usesWalkInReceipt(LedgerEntry $head, ?Payment $payment): bool
    {
        return $payment !== null
            && strtoupper((string) $payment->payment_gateway) === 'CASHIER_POS'
            && in_array($head->source, ['POS_WALKIN_PADEL', 'RESCHEDULE_DELTA_POS', 'ONLINE_PADEL'], true)
            && PadelBooking::where('order_id', $head->order_id)->exists();
    }

    /**
     * Invoice salinan admin. Struk POS Walk-In dipakai apa adanya untuk pembayaran kasir padel; sumber lain memakai
     * invoice ringkas dari buku + isi order (struk F&B & membership masih menyatu di halaman POS masing-masing).
     *
     * @return array{view: string, data: array}
     */
    public function invoice(LedgerEntry $row): array
    {
        $rows = $this->rowsOf($row);
        $head = $rows->first() ?? $row;
        $payment = $head->payment_id ? Payment::find($head->payment_id) : null;
        $copyAt = now(LedgerReport::TIMEZONE)->format('d/m/Y H:i');

        if ($head->entry_type !== LedgerEntry::TYPE_REFUND && $this->usesWalkInReceipt($head, $payment)) {
            $receipt = (new BookOfflineCourt)->buildWalkInReceipt($payment);
            $receipt['admin_copy_at'] = $copyAt;

            return ['view' => 'filament.partials.walkin-receipt', 'data' => ['receipt' => $receipt]];
        }

        $order = Order::withTrashed()->with(['items', 'user'])->find($head->order_id);
        $log = is_array($payment?->payload_log) ? $payment->payload_log : [];

        return ['view' => 'filament.pages.partials.ledger-invoice', 'data' => [
            'head' => $head,
            'rows' => $rows,
            'totals' => collect(LedgerReport::MONEY_COLUMNS)->mapWithKeys(fn ($c) => [$c => (float) $rows->sum($c)])->all(),
            'order' => $order,
            // Pelunasan selisih tidak punya item sendiri — item order (lapangan awal) tidak ikut ditampilkan.
            'items' => ($log['type'] ?? null) === 'RESCHEDULE_PRICE_DELTA' ? collect() : ($order?->items ?? collect()),
            'schedule_before' => $log['schedule_before'] ?? null,
            'proof' => $payment ? $this->proof($payment) : [],
            'refund' => $head->refund_id ? Refund::find($head->refund_id) : null,
            'copy_at' => $copyAt,
        ]];
    }
}
