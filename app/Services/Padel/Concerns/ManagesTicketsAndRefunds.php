<?php

namespace App\Services\Padel\Concerns;

use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelBookingEquipment;
use App\Models\Pos\Order;
use App\Models\User;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait ManagesTicketsAndRefunds
{
    /**
     * Mengajukan pembatalan dengan jalur refund resmi (H-24).
     */
    public function requestRefund(string $bookingId, string $reason, User $user): PadelBooking
    {
        $booking = PadelBooking::where('id', $bookingId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($booking->status !== 'PAID') {
            throw new HttpException(400, 'Hanya booking lunas (PAID) yang dapat diajukan refund.');
        }

        // Syarat H-24
        if ($booking->start_time->diffInHours(now(), false) > -24) {
            throw new HttpException(422, 'Pembatalan dengan refund hanya dapat diajukan minimal 24 jam sebelum jadwal bertanding.');
        }

        $booking->update([
            'status' => 'REFUND_PENDING',
            'cancel_reason' => $reason,
        ]);

        return $booking;
    }

    /**
     * Mengambil riwayat booking user terfilter.
     */
    public function myBookings(User $user, ?string $status = null): Collection
    {
        $query = PadelBooking::with(['court', 'coach', 'equipments.equipment'])
            ->where('user_id', $user->id)
            ->latest('start_time');

        if ($status) {
            $statusUpper = strtoupper($status);
            if ($statusUpper === 'UPCOMING') {
                $query->whereIn('status', ['LOCKED', 'PAID'])->where('start_time', '>=', now());
            } elseif ($statusUpper === 'COMPLETED') {
                $query->whereIn('status', ['CHECKED_IN', 'COMPLETED']);
            } elseif ($statusUpper === 'CANCELLED') {
                $query->whereIn('status', ['CANCELLED', 'EXPIRED', 'REFUND_PENDING', 'REFUNDED']);
            }
        }

        return $query->get();
    }

    /**
     * Mengambil detail satu tiket booking beserta konteks order terpadu.
     */
    public function getTicket(string $bookingId, User $user): PadelBooking
    {
        $booking = PadelBooking::with(['court', 'coach', 'equipments.equipment'])
            ->where(function ($q) use ($bookingId) {
                $q->where('id', $bookingId)
                  ->orWhere('booking_code', $bookingId)
                  ->orWhere('order_id', $bookingId);
            })
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Jika booking ini terikat dengan order_id, sertakan seluruh sesi se-order dan alat sewa
        if ($booking->order_id) {
            $orderBookings = PadelBooking::with('court')
                ->where('order_id', $booking->order_id)
                ->where('user_id', $user->id)
                ->orderBy('start_time')
                ->get();

            $orderEquipments = PadelBookingEquipment::with('equipment')
                ->where('order_id', $booking->order_id)
                ->get();

            $totalEquipmentFee = $orderEquipments->sum('subtotal');
            $totalCourtFee = $orderBookings->sum('court_fee');

            $booking->setAttribute('order_bookings', $orderBookings);
            $booking->setAttribute('order_equipments', $orderEquipments);
            $booking->setAttribute('order_court_fee', (float) $totalCourtFee);
            $booking->setAttribute('order_equipment_fee', (float) $totalEquipmentFee);
            $booking->setAttribute('order_grand_total', (float) ($totalCourtFee + $totalEquipmentFee));

            // Load order dan data transaksi pembayaran riil
            $order = Order::with(['payments' => function ($q) {
                $q->latest();
            }])->where('order_number', $booking->order_id)
               ->orWhere('id', $booking->order_id)
               ->first();

            if ($order) {
                $latestPayment = $order->payments->first();
                $rawMethod = $latestPayment?->payment_method;
                $methodLabel = $this->formatPaymentMethodLabel($rawMethod, $latestPayment?->payload_log);

                $totalPaid = (float) $order->payments->where('status', 'SUCCESS')->sum('amount');
                $pendingSupplementalPayment = $order->payments->where('status', 'PENDING')->first();
                $unpaidDelta = $pendingSupplementalPayment ? (float) $pendingSupplementalPayment->amount : max(0, (float) $order->grand_total - $totalPaid);

                $booking->setAttribute('order', $order);
                $booking->setAttribute('payment_method', $rawMethod);
                $booking->setAttribute('payment_method_label', $methodLabel);
                $booking->setAttribute('total_paid', $totalPaid);
                $booking->setAttribute('unpaid_delta', $unpaidDelta);
                $booking->setAttribute('has_pending_delta', $unpaidDelta > 0 && $totalPaid > 0);
                $booking->setAttribute('pending_supplemental_id', $pendingSupplementalPayment?->id);
            }
        }

        return $booking;
    }
}
