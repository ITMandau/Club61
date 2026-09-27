<?php

namespace App\Services\Wellness;

use App\Models\Membership\UserMembership;
use App\Models\Membership\UserMembershipBalance;
use App\Models\User;
use App\Models\Wellness\WellnessBooking;
use App\Models\Wellness\WellnessFacility;
use App\Models\Wellness\WellnessSlot;
use App\Services\Membership\MembershipBalanceService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WellnessBookingService
{
    public function __construct(
        protected MembershipBalanceService $membershipBalanceService
    ) {}

    /**
     * Book a wellness slot with optional membership benefit application.
     */
    public function bookSlot(User $user, string $slotId, int $numPersons = 1, ?string $membershipBalanceId = null): WellnessBooking
    {
        if ($numPersons < 1) {
            throw new DomainException("Jumlah orang minimal 1.");
        }

        return DB::transaction(function () use ($user, $slotId, $numPersons, $membershipBalanceId) {
            $slot = WellnessSlot::where('id', $slotId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($slot->booked_count + $numPersons > $slot->max_capacity) {
                throw new DomainException("Kapasitas slot tidak mencukupi (sisa: " . ($slot->max_capacity - $slot->booked_count) . ").");
            }

            $facility = $slot->facility;
            $pricePerPerson = (float) $facility->price_per_person;
            $baseTotal = $pricePerPerson * $numPersons;

            $sessionsConsumed = 0.0;
            $discountAmount = 0.0;
            $appliedBalanceId = null;

            if ($membershipBalanceId) {
                $balance = UserMembershipBalance::where('id', $membershipBalanceId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $membership = $balance->membership;

                if ($membership->user_id !== $user->id) {
                    throw new DomainException("Membership tidak valid untuk user ini.");
                }

                if (! $membership->isActive()) {
                    throw new DomainException("Membership tidak aktif atau sudah kadaluarsa.");
                }

                if ($balance->facility !== 'SAUNA') {
                    throw new DomainException("Benefit bukan untuk fasilitas Sauna/Wellness.");
                }

                // Time window check
                if ($balance->time_window_start && $balance->time_window_end) {
                    $slotTime = Carbon::parse($slot->start_time)->format('H:i:s');
                    if ($slotTime < $balance->time_window_start || $slotTime > $balance->time_window_end) {
                        throw new DomainException(
                            "Booking di luar jam akses membership ({$balance->time_window_start} - {$balance->time_window_end})."
                        );
                    }
                }

                $appliedBalanceId = $balance->id;

                if ($balance->quota_type === 'VISITS' && (float) $balance->remaining_quota >= 1.0) {
                    // 1 visit covers 1 person completely
                    $sessionsConsumed = 1.0;
                    $discountAmount = $pricePerPerson;

                    $this->membershipBalanceService->adjustQuota(
                        balanceId: $balance->id,
                        changeType: 'DECREMENT',
                        quantity: 1.0,
                        notes: 'Pemakaian sesi Sauna slot ' . $slot->id,
                        relatedType: WellnessBooking::class
                    );
                } elseif ((float) $balance->discount_percent > 0) {
                    // Flat discount applies only to 1 person (the member)
                    $discountAmount = round($pricePerPerson * ((float) $balance->discount_percent / 100), 2);
                }
            }

            $finalAmount = max(0, $baseTotal - $discountAmount);

            $slot->booked_count += $numPersons;
            if ($slot->booked_count >= $slot->max_capacity) {
                $slot->status = 'FULL';
            }
            $slot->save();

            $bookingCode = 'WLN-' . date('Ymd') . '-' . strtoupper(Str::random(6));

            $booking = WellnessBooking::create([
                'booking_code' => $bookingCode,
                'user_id' => $user->id,
                'slot_id' => $slot->id,
                'num_persons' => $numPersons,
                'total_amount' => $finalAmount,
                'status' => $finalAmount == 0 ? 'CONFIRMED' : 'PENDING',
                'membership_balance_id' => $appliedBalanceId,
                'member_discount_amount' => $discountAmount,
                'member_sessions_consumed' => $sessionsConsumed,
            ]);

            $booking->qr_code_hash = hash(
                'sha256',
                $booking->id . '|' . $booking->booking_code . '|' . config('app.key')
            );
            $booking->save();

            // Link related_id in usage log if quota was consumed
            if ($sessionsConsumed > 0 && $appliedBalanceId) {
                \App\Models\Membership\MembershipUsageLog::where('balance_id', $appliedBalanceId)
                    ->where('related_type', WellnessBooking::class)
                    ->whereNull('related_id')
                    ->latest('created_at')
                    ->first()
                    ?->update(['related_id' => $booking->id]);
            }

            return $booking;
        });
    }

    /**
     * Cancel wellness booking and reverse consumed session quota if any.
     */
    public function cancelBooking(WellnessBooking $booking, ?string $reason = null): WellnessBooking
    {
        return DB::transaction(function () use ($booking, $reason) {
            $locked = WellnessBooking::where('id', $booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === 'CANCELLED') {
                return $locked;
            }

            // Restore slot capacity
            $slot = WellnessSlot::where('id', $locked->slot_id)->lockForUpdate()->first();
            if ($slot) {
                $slot->booked_count = max(0, $slot->booked_count - $locked->num_persons);
                if ($slot->status === 'FULL' && $slot->booked_count < $slot->max_capacity) {
                    $slot->status = 'AVAILABLE';
                }
                $slot->save();
            }

            // Reverse quota if consumed
            if ($locked->membership_balance_id && (float) $locked->member_sessions_consumed > 0) {
                $this->membershipBalanceService->adjustQuota(
                    balanceId: $locked->membership_balance_id,
                    changeType: 'REVERSAL',
                    quantity: (float) $locked->member_sessions_consumed,
                    notes: 'Reversal pembatalan sesi Sauna: ' . ($reason ?? 'User cancel'),
                    relatedType: WellnessBooking::class,
                    relatedId: $locked->id
                );
            }

            $locked->status = 'CANCELLED';
            $locked->save();

            return $locked;
        });
    }
}
