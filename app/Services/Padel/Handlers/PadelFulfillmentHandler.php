<?php

namespace App\Services\Padel\Handlers;

use App\Models\Pos\Order;
use App\Services\Payment\Contracts\DomainFulfillmentHandlerInterface;
use Illuminate\Support\Collection;

class PadelFulfillmentHandler implements DomainFulfillmentHandlerInterface
{
    /**
     * Eksekusi pemenuhan tiket dan status booking lapangan Padel pasca pembayaran.
     */
    public function fulfill(Order $order, Collection $items): void
    {
        // Tarik seluruh booking yang terhubung ke order ini
        $bookings = $order->padelBookings;

        if ($bookings->isEmpty()) {
            $bookings = \App\Models\Padel\PadelBooking::where('order_id', $order->id)
                ->orWhere('order_id', $order->order_number)
                ->get();
        }

        foreach ($bookings as $booking) {
            // Guard: Dilarang menimpa status yang sudah lebih maju (CHECKED_IN, COMPLETED, CANCELLED)
            if (in_array($booking->status, ['CHECKED_IN', 'COMPLETED', 'CANCELLED'], true)) {
                continue;
            }

            $updateData = [];

            // Transisi ke PAID hanya untuk booking yang belum lunas
            if (in_array($booking->status, ['LOCKED', 'PENDING_PAYMENT', 'PENDING'], true)) {
                $updateData['status'] = 'PAID';
            }

            // Rilis atau aktifkan hash tiket QR jika belum ada atau sempat ditahan karena delta tagihan
            if (empty($booking->qr_code_hash)) {
                $startTimeStr = $booking->start_time ? $booking->start_time->toISOString() : '';
                $updateData['qr_code_hash'] = hash_hmac(
                    'sha256',
                    $booking->booking_code . $booking->user_id . $booking->court_id . $startTimeStr,
                    config('app.key')
                );
            }

            if (! empty($updateData)) {
                // Atomic conditional update di level database untuk mencegah race condition dengan pembatalan/check-in konkuren
                \App\Models\Padel\PadelBooking::where('id', $booking->id)
                    ->whereIn('status', ['LOCKED', 'PENDING_PAYMENT', 'PENDING'])
                    ->update($updateData);
            }
        }
    }
}
