<?php

namespace App\Services\Pos;

use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelBookingEquipment;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Services\Membership\MembershipOnlinePaymentService;
use App\Services\Padel\PadelBookingService;
use Illuminate\Support\Facades\DB;

/**
 * Bagian bersama "Bayar Otomatis" kasir (Midtrans & DOKU): QR yang tidak dibayar → tagihan ditutup, order batal, slot
 * lapangan dilepas, kuota member / stok bola dikembalikan, kartu member batal. Dipanggil SETELAH gateway-nya dipastikan
 * belum menerima uang (PosMidtransQrisService / DokuPosQrisService).
 */
class PosPendingOrderCanceller
{
    /** @return string 'PAID' kalau tagihan ternyata sudah lunas, selain itu 'CANCELLED'. */
    public static function cancel(Payment $payment, string $closedBy): string
    {
        return DB::transaction(function () use ($payment, $closedBy) {
            $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status === 'SUCCESS') {
                return 'PAID';
            }
            if ($payment->status === 'PENDING') {
                $log = is_array($payment->payload_log) ? $payment->payload_log : [];
                $log['closed_by'] = $closedBy;
                $payment->update(['status' => 'FAILED', 'payload_log' => $log]);
            }

            if (Payment::where('order_id', $order->id)->where('status', 'SUCCESS')->exists()) {
                return 'CANCELLED';
            }

            $order->update(['payment_status' => 'CANCELLED']);
            MembershipOnlinePaymentService::cancelForCancelledOrder($order);

            $bookingService = app(PadelBookingService::class);
            PadelBooking::where('order_id', $order->id)
                ->whereIn('status', ['PENDING_PAYMENT', 'LOCKED', 'PENDING'])
                ->lockForUpdate()
                ->get()
                ->each(function (PadelBooking $booking) use ($bookingService) {
                    $bookingService->reverseBookingBenefits($booking);
                    $booking->update(['status' => 'CANCELLED']);
                });

            // Bola (habis pakai) dipotong saat checkout → kembalikan stoknya.
            PadelBookingEquipment::with('equipment')
                ->where('order_id', $order->id)
                ->whereNotNull('stock_deducted_at')
                ->get()
                ->each(function (PadelBookingEquipment $row) {
                    $row->equipment?->increment('stock_quantity', (int) $row->quantity);
                    $row->update(['stock_deducted_at' => null]);
                });

            return 'CANCELLED';
        });
    }
}
