<?php

namespace App\Services\Membership;

use App\Models\Membership\FacilityCheckin;
use App\Models\Membership\MembershipPlan;
use App\Models\Membership\MembershipUsageLog;
use App\Models\Membership\UserMembership;
use App\Models\Membership\UserMembershipBalance;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MembershipBalanceService
{
    /**
     * Generate unique membership code atomically using sequence counter.
     * Format: MBR-YYYY-NNNNN
     */
    public function generateMembershipCode(): string
    {
        $year = (int) Carbon::now()->format('Y');

        return DB::transaction(function () use ($year) {
            $seq = DB::table('membership_code_sequences')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $seq) {
                DB::table('membership_code_sequences')->insert([
                    'year' => $year,
                    'last_number' => 1,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
                $next = 1;
            } else {
                $next = $seq->last_number + 1;
                DB::table('membership_code_sequences')
                    ->where('year', $year)
                    ->update([
                        'last_number' => $next,
                        'updated_at' => Carbon::now(),
                    ]);
            }

            return sprintf('MBR-%d-%05d', $year, $next);
        });
    }

    /**
     * Purchase a membership plan for user with snapshot of benefits.
     * Initial remaining_quota starts at 0.00 until fulfillment / activation.
     */
    public function purchasePlan(User $user, MembershipPlan $plan, array $options = []): UserMembership
    {
        return DB::transaction(function () use ($user, $plan, $options) {
            $discountPercent = (float) ($options['manual_discount_percent'] ?? 0);
            $discountReason = $options['manual_discount_reason'] ?? null;
            $soldByAdminId = $options['sold_by_admin_id'] ?? null;
            $orderId = $options['order_id'] ?? null;
            $status = $options['status'] ?? 'PENDING_PAYMENT';
            $renewalOfId = $options['renewal_of_id'] ?? null;
            $ownerType = $options['owner_type'] ?? $plan->ownership_type;

            $priceSnapshot = round((float) $plan->price * (1 - ($discountPercent / 100)), 2);
            $membershipCode = $this->generateMembershipCode();

            $membership = UserMembership::create([
                'membership_code' => $membershipCode,
                'owner_type' => $ownerType,
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'order_id' => $orderId,
                'renewal_of_id' => $renewalOfId,
                'start_date' => null,
                'end_date' => null,
                'status' => $status,
                'purchase_price_snapshot' => $priceSnapshot,
                'manual_discount_percent' => $discountPercent,
                'manual_discount_reason' => $discountReason,
                'sold_by_admin_id' => $soldByAdminId,
            ]);

            $membership->qr_pass_hash = hash(
                'sha256',
                $membership->id . '|' . $membership->membership_code . '|' . config('app.key')
            );
            $membership->save();

            // Snapshot all plan benefits into user_membership_balances
            foreach ($plan->benefits as $benefit) {
                UserMembershipBalance::create([
                    'user_membership_id' => $membership->id,
                    'facility' => $benefit->facility,
                    'quota_type' => $benefit->quota_type,
                    'initial_quota' => $benefit->quota_value,
                    'remaining_quota' => 0.00, // CRITICAL: starts at zero, topped up on activation
                    'discount_percent' => $benefit->discount_percent,
                    'booking_priority_days' => $benefit->booking_priority_days,
                    'time_window_start' => $benefit->time_window_start,
                    'time_window_end' => $benefit->time_window_end,
                    'extra_benefits' => $benefit->extra_benefits,
                ]);
            }

            return $membership->fresh(['balances', 'plan']);
        });
    }

    /**
     * Activate a membership and top up initial quotas.
     */
    public function activateMembership(UserMembership $membership, ?Carbon $startDate = null): UserMembership
    {
        return DB::transaction(function () use ($membership, $startDate) {
            $lockedMembership = UserMembership::where('id', $membership->id)
                ->lockForUpdate()
                ->firstOrFail();

            $start = $startDate ?? Carbon::today();
            $end = $start->copy()->addDays($lockedMembership->plan->duration_days);

            $lockedMembership->start_date = $start->toDateString();
            $lockedMembership->end_date = $end->toDateString();
            $lockedMembership->status = 'ACTIVE';
            $lockedMembership->save();

            // Top-up quotas from initial_quota
            foreach ($lockedMembership->balances as $balance) {
                if ($balance->initial_quota !== null && (float) $balance->initial_quota > 0) {
                    $this->adjustQuota(
                        balanceId: $balance->id,
                        changeType: 'TOPUP',
                        quantity: (float) $balance->initial_quota,
                        notes: 'Aktivasi membership paket ' . $lockedMembership->plan->code,
                        relatedType: UserMembership::class,
                        relatedId: $lockedMembership->id
                    );
                }
            }

            return $lockedMembership->fresh(['balances', 'plan']);
        });
    }

    /**
     * Renew a parent membership by creating a renewal pending payment order.
     */
    public function renewMembership(UserMembership $parentMembership, MembershipPlan $plan, array $options = []): UserMembership
    {
        $options['renewal_of_id'] = $parentMembership->id;
        $options['owner_type'] = $parentMembership->owner_type;

        return $this->purchasePlan($parentMembership->user, $plan, $options);
    }

    /**
     * Fulfill a paid renewal membership: extend parent duration and top up parent balances.
     */
    public function fulfillRenewal(UserMembership $renewalMembership): UserMembership
    {
        return DB::transaction(function () use ($renewalMembership) {
            $parent = UserMembership::where('id', $renewalMembership->renewal_of_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Renewal extension starts from max(today, old end_date)
            $baseDate = Carbon::today();
            if ($parent->end_date) {
                $oldEndDate = Carbon::parse($parent->end_date);
                if ($oldEndDate->isFuture()) {
                    $baseDate = $oldEndDate;
                }
            }

            $parent->end_date = $baseDate->copy()->addDays($renewalMembership->plan->duration_days)->toDateString();
            $parent->status = 'ACTIVE';
            $parent->save();

            // Top up parent balances based on renewal plan benefits
            foreach ($renewalMembership->balances as $renewalBalance) {
                if ($renewalBalance->initial_quota !== null && (float) $renewalBalance->initial_quota > 0) {
                    $parentBalance = UserMembershipBalance::where('user_membership_id', $parent->id)
                        ->where('facility', $renewalBalance->facility)
                        ->lockForUpdate()
                        ->first();

                    if (! $parentBalance) {
                        $parentBalance = UserMembershipBalance::create([
                            'user_membership_id' => $parent->id,
                            'facility' => $renewalBalance->facility,
                            'quota_type' => $renewalBalance->quota_type,
                            'initial_quota' => $renewalBalance->initial_quota,
                            'remaining_quota' => 0.00,
                            'discount_percent' => $renewalBalance->discount_percent,
                            'booking_priority_days' => $renewalBalance->booking_priority_days,
                            'time_window_start' => $renewalBalance->time_window_start,
                            'time_window_end' => $renewalBalance->time_window_end,
                            'extra_benefits' => $renewalBalance->extra_benefits,
                        ]);
                    }

                    $this->adjustQuota(
                        balanceId: $parentBalance->id,
                        changeType: 'TOPUP',
                        quantity: (float) $renewalBalance->initial_quota,
                        notes: 'Renewal akumulasi paket ' . $renewalMembership->plan->code,
                        relatedType: UserMembership::class,
                        relatedId: $renewalMembership->id
                    );
                }
            }

            // Mark renewal record as MERGED
            $renewalMembership->status = 'MERGED';
            $renewalMembership->save();

            return $parent->fresh(['balances', 'plan']);
        });
    }

    /**
     * Upgrade old membership to a new plan.
     * Rollover old remaining quota via ROLLOVER_OUT, then topup into new card via ROLLOVER_IN.
     */
    public function upgradeMembership(UserMembership $oldMembership, MembershipPlan $newPlan, array $options = []): UserMembership
    {
        return DB::transaction(function () use ($oldMembership, $newPlan, $options) {
            $lockedOld = UserMembership::where('id', $oldMembership->id)
                ->lockForUpdate()
                ->firstOrFail();

            $rolledOverQuotas = [];

            // Drain remaining quota from old card with ROLLOVER_OUT
            foreach ($lockedOld->balances as $balance) {
                $rem = (float) $balance->remaining_quota;
                if ($rem > 0) {
                    $this->adjustQuota(
                        balanceId: $balance->id,
                        changeType: 'ROLLOVER_OUT',
                        quantity: $rem,
                        notes: 'Rollover keluar untuk upgrade ke paket ' . $newPlan->code,
                        relatedType: MembershipPlan::class,
                        relatedId: $newPlan->id,
                        performedBy: $options['sold_by_admin_id'] ?? null
                    );
                    $rolledOverQuotas[$balance->facility] = $rem;
                }
            }

            $lockedOld->status = 'UPGRADED';
            $lockedOld->save();

            // Create new membership
            $newMembership = $this->purchasePlan($lockedOld->user, $newPlan, $options);

            // If activating immediately (e.g. POS cashier checkout)
            if (($options['activate_now'] ?? false) === true) {
                $newMembership = $this->activateMembership($newMembership);

                // Transfer rolled-over quotas with ROLLOVER_IN
                foreach ($rolledOverQuotas as $facility => $rolloverAmount) {
                    $newBalance = $newMembership->balances->firstWhere('facility', $facility);
                    if ($newBalance && $rolloverAmount > 0) {
                        $this->adjustQuota(
                            balanceId: $newBalance->id,
                            changeType: 'ROLLOVER_IN',
                            quantity: $rolloverAmount,
                            notes: 'Rollover masuk dari upgrade kartu lama ' . $lockedOld->membership_code,
                            relatedType: UserMembership::class,
                            relatedId: $lockedOld->id,
                            performedBy: $options['sold_by_admin_id'] ?? null
                        );
                    }
                }
            }

            return $newMembership->fresh(['balances', 'plan']);
        });
    }

    /**
     * Mutate quota balance atomically with strict ledger logging.
     */
    public function adjustQuota(
        string $balanceId,
        string $changeType,
        float $quantity,
        ?string $notes = null,
        ?string $relatedType = null,
        ?string $relatedId = null,
        ?string $performedBy = null
    ): UserMembershipBalance {
        $allowedTypes = ['DECREMENT', 'REVERSAL', 'MANUAL_ADJUSTMENT', 'TOPUP', 'ROLLOVER_IN', 'ROLLOVER_OUT'];
        if (! in_array($changeType, $allowedTypes, true)) {
            throw new InvalidArgumentException("Tipe mutasi kuota tidak valid: {$changeType}");
        }

        if ($quantity <= 0 && $changeType !== 'MANUAL_ADJUSTMENT') {
            throw new InvalidArgumentException("Kuantitas mutasi harus lebih besar dari 0.");
        }

        return DB::transaction(function () use ($balanceId, $changeType, $quantity, $notes, $relatedType, $relatedId, $performedBy) {
            $balance = UserMembershipBalance::where('id', $balanceId)
                ->lockForUpdate()
                ->firstOrFail();

            $currentQuota = (float) $balance->remaining_quota;

            switch ($changeType) {
                case 'TOPUP':
                case 'REVERSAL':
                case 'ROLLOVER_IN':
                    $newQuota = $currentQuota + $quantity;
                    break;

                case 'DECREMENT':
                case 'ROLLOVER_OUT':
                    if ($currentQuota < $quantity) {
                        throw new DomainException(
                            "Saldo kuota {$balance->facility} tidak mencukupi (tersedia: {$currentQuota}, dibutuhkan: {$quantity})."
                        );
                    }
                    $newQuota = $currentQuota - $quantity;
                    break;

                case 'MANUAL_ADJUSTMENT':
                    $newQuota = $currentQuota + $quantity;
                    if ($newQuota < 0) {
                        throw new DomainException(
                            "Penyesuaian manual menyebabkan saldo kuota minus (tersedia: {$currentQuota}, penyesuaian: {$quantity})."
                        );
                    }
                    break;
            }

            $balance->remaining_quota = round($newQuota, 2);
            $balance->save();

            // Insert immutable audit log
            MembershipUsageLog::create([
                'balance_id' => $balance->id,
                'change_type' => $changeType,
                'quantity' => $quantity,
                'related_type' => $relatedType,
                'related_id' => $relatedId,
                'performed_by' => $performedBy,
                'notes' => $notes,
            ]);

            return $balance;
        });
    }

    /**
     * Record Gym facility check-in with validation and quota decrement.
     */
    public function recordCheckin(string $balanceId, string $userId, ?string $staffId = null): FacilityCheckin
    {
        return DB::transaction(function () use ($balanceId, $userId, $staffId) {
            $balance = UserMembershipBalance::where('id', $balanceId)
                ->lockForUpdate()
                ->firstOrFail();

            $membership = $balance->membership;

            if (! $membership->isActive()) {
                throw new DomainException("Membership tidak aktif atau sudah kadaluarsa.");
            }

            if ($balance->facility !== 'GYM') {
                throw new DomainException("Fasilitas {$balance->facility} tidak mendukung check-in langsung.");
            }

            // Time window restriction check
            if ($balance->time_window_start && $balance->time_window_end) {
                $nowTime = Carbon::now()->format('H:i:s');
                if ($nowTime < $balance->time_window_start || $nowTime > $balance->time_window_end) {
                    throw new DomainException(
                        "Check-in ditolak: di luar jam akses paket ({$balance->time_window_start} - {$balance->time_window_end})."
                    );
                }
            }

            // Decrement visit quota if VISITS
            if ($balance->quota_type === 'VISITS') {
                $this->adjustQuota(
                    balanceId: $balance->id,
                    changeType: 'DECREMENT',
                    quantity: 1.00,
                    notes: 'Check-in fasilitas Gym',
                    relatedType: FacilityCheckin::class,
                    performedBy: $staffId
                );
            }

            return FacilityCheckin::create([
                'facility' => 'GYM',
                'balance_id' => $balance->id,
                'user_id' => $userId,
                'staff_id' => $staffId,
                'checkin_at' => Carbon::now(),
            ]);
        });
    }
}
