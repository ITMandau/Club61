<?php

namespace App\Services\Padel\Concerns;

use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelRecurringOccurrence;
use App\Models\Padel\PadelRecurringSchedule;
use App\Models\Pos\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Jadwal Tetap Mingguan — membuat SATU booking (satu lapangan, satu tanggal) atas nama pemilik membership, lunas
 * 100% oleh kuota jam PADEL membership jadwal itu. Jalurnya sama dengan checkout yang ditanggung penuh kuota:
 * holdBatchSlots (cek bentrok + jam operasional + kunci slot) → applyMembershipBenefitToCourtBookings (kartu dipilih
 * eksplisit: masa berlaku & jam akses paket dicek, kuota dipotong lewat ledger) → order Rp0 → markOrderAsPaid.
 * Tidak ada uang masuk, jadi tidak butuh shift kasir. Kuota kurang = gagal (tidak pernah jadi tagihan).
 */
trait ManagesRecurringBookings
{
    public function createRecurringBooking(PadelRecurringSchedule $schedule, string $courtId, string $date, User $actor): PadelBooking
    {
        $owner = $schedule->membership?->user;
        $balance = $schedule->padelBalance();
        if (! $owner || ! $balance) {
            throw new HttpException(422, 'Membership jadwal ini tidak punya kuota jam PADEL.');
        }

        $slots = [['court_id' => $courtId, 'start_time' => $schedule->start_time, 'end_time' => $schedule->end_time]];
        $hold = $this->holdBatchSlots($slots, $date, $owner);
        $bookingIds = collect($hold['bookings'])->pluck('id')->all();

        try {
            return DB::transaction(function () use ($schedule, $courtId, $date, $actor, $owner, $balance, $bookingIds) {
                // Kunci catatan minggu ini: proses lain (tombol admin & perintah terjadwal bersamaan) yang sudah
                // membuat booking-nya → batalkan hold ini.
                $occurrence = PadelRecurringOccurrence::where('recurring_schedule_id', $schedule->id)
                    ->where('court_id', $courtId)
                    ->whereDate('occurrence_date', $date)
                    ->lockForUpdate()
                    ->first();
                if (! $occurrence || ! in_array($occurrence->status, [PadelRecurringOccurrence::STATUS_PENDING, PadelRecurringOccurrence::STATUS_FAILED], true)) {
                    throw new HttpException(409, 'Jadwal minggu ini sudah diproses.');
                }

                $bookings = PadelBooking::with('court')->whereIn('id', $bookingIds)->where('user_id', $owner->id)
                    ->where('status', 'LOCKED')->lockForUpdate()->get();
                if ($bookings->count() !== count($bookingIds)) {
                    throw new HttpException(422, 'Slot yang ditahan sudah tidak berlaku.');
                }

                $benefit = $this->applyMembershipBenefitToCourtBookings($bookings, $owner, $balance->id);
                if ($benefit['benefit_type'] !== 'HOURS' || (float) $bookings->sum('court_fee') > 0) {
                    throw new HttpException(422, 'Sisa kuota jam tidak cukup (sisa '.rtrim(rtrim(number_format((float) $balance->fresh()->remaining_quota, 2, '.', ''), '0'), '.').' jam).');
                }

                $orderNumber = 'ORD-PAD-'.strtoupper(Str::random(10));
                $order = Order::create([
                    'order_number' => $orderNumber,
                    'user_id' => $owner->id,
                    'cashier_id' => $actor->id,
                    'order_type' => 'WALK_IN',
                    'subtotal' => 0,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                    'service_charge' => 0,
                    'grand_total' => 0,
                    'payment_status' => 'UNPAID',
                ]);

                foreach ($bookings as $b) {
                    $order->items()->create([
                        'item_type' => 'PADEL',
                        'reference_id' => $b->id,
                        'item_name' => 'Sewa '.($b->court ? $b->court->name : 'Court Padel').' · Jadwal Tetap',
                        'quantity' => 1,
                        'unit_price' => $b->court_fee,
                        'subtotal' => $b->court_fee,
                    ]);

                    $b->forceFill([
                        'order_id' => $order->id,
                        'status' => 'PAID',
                        'expires_at' => null,
                        'qr_code_hash' => 'VNT-TICKET-'.strtoupper(bin2hex(random_bytes(16))),
                        'recurring_schedule_id' => $schedule->id,
                    ])->save();
                }

                app(\App\Services\Payment\PaymentOrchestratorService::class)->markOrderAsPaid($order, [
                    'payment_gateway' => 'MEMBERSHIP_QUOTA',
                    'transaction_id' => $orderNumber,
                    'payment_method' => 'MEMBERSHIP_QUOTA',
                    'amount' => 0.0,
                    'payload_log' => [
                        'covered_by' => 'MEMBERSHIP_QUOTA',
                        'source' => 'RECURRING_SCHEDULE',
                        'recurring_schedule_id' => $schedule->id,
                        'created_by' => $actor->name,
                    ],
                ]);

                $booking = $bookings->first();
                $occurrence->update([
                    'status' => PadelRecurringOccurrence::STATUS_BOOKED,
                    'booking_id' => $booking->id,
                    'note' => null,
                ]);

                return $booking->fresh(['court']);
            });
        } catch (\Throwable $e) {
            try {
                $this->releaseSlots($bookingIds, $owner, onlyLocked: true);
            } catch (\Throwable $releaseError) {
                report($releaseError); // jangan menutupi error aslinya
            }

            throw $e;
        }
    }
}
