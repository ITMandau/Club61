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
        $order = Order::where('order_number', $bookingId)
            ->orWhere('id', $bookingId)
            ->first();
        $orderId = $order?->id;

        $booking = PadelBooking::with(['court', 'coach', 'equipments.equipment'])
            ->where(function ($q) use ($bookingId, $orderId) {
                $q->where('id', $bookingId)
                  ->orWhere('booking_code', $bookingId)
                  ->orWhere('order_id', $bookingId);
                if ($orderId) {
                    $q->orWhere('order_id', $orderId);
                }
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
            $totalMemberDiscount = $orderBookings->sum('member_discount_court');
            $totalSponsorDiscount = $orderBookings->sum('sponsor_discount_court');
            $totalSponsorHours = $orderBookings->sum('sponsor_hours_consumed');

            $booking->setAttribute('order_bookings', $orderBookings);
            $booking->setAttribute('order_equipments', $orderEquipments);
            $booking->setAttribute('order_court_fee', (float) $totalCourtFee);
            $booking->setAttribute('order_equipment_fee', (float) $totalEquipmentFee);
            $booking->setAttribute('order_grand_total', (float) ($totalCourtFee + $totalEquipmentFee));
            $booking->setAttribute('order_member_discount_court', (float) $totalMemberDiscount);
            $booking->setAttribute('order_sponsor_discount_court', (float) $totalSponsorDiscount);
            $booking->setAttribute('order_sponsor_hours_consumed', (float) $totalSponsorHours);

            // Load order dan data transaksi pembayaran riil
            $order = Order::with(['payments' => function ($q) {
                $q->latest();
            }])->where('order_number', $booking->order_id)
               ->orWhere('id', $booking->order_id)
               ->first();

            if ($order) {
                $booking->setAttribute('order_grand_total', (float) $order->grand_total);
                // Label metode bayar dari pembayaran yang SUDAH lunas — tagihan selisih yang masih menunggu
                // tidak boleh membuat invoice menampilkan "MENUNGGU_PEMBAYARAN" sebagai metode bayar.
                $latestPayment = $order->payments->firstWhere('status', 'SUCCESS') ?? $order->payments->first();

                // Rincian selisih reschedule untuk invoice (lunas / menunggu) + selisih yang hangus.
                $booking->setAttribute('reschedule_charges', $order->payments
                    ->filter(fn ($p) => (($p->payload_log['type'] ?? null) === 'RESCHEDULE_PRICE_DELTA') && in_array($p->status, ['SUCCESS', 'PENDING'], true))
                    ->sortBy('created_at')
                    ->map(fn ($p) => [
                        'amount' => (float) $p->amount,
                        'status' => $p->status,
                        'method_label' => $p->status === 'SUCCESS' ? $this->formatPaymentMethodLabel($p->payment_method, $p->payload_log) : null,
                        'schedule_before' => $p->payload_log['schedule_before'] ?? null,
                        'date' => $p->created_at?->toIso8601String(),
                    ])->values());
                $booking->setAttribute('order_reschedule_forfeited', (float) $orderBookings->sum('reschedule_forfeited_amount'));
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
