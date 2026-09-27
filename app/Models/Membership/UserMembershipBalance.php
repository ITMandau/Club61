<?php

namespace App\Models\Membership;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserMembershipBalance extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_membership_id',
        'facility',
        'quota_type',
        'initial_quota',
        'remaining_quota',
        'discount_percent',
        'booking_priority_days',
        'time_window_start',
        'time_window_end',
        'extra_benefits',
    ];

    protected $casts = [
        'initial_quota' => 'decimal:2',
        'remaining_quota' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'booking_priority_days' => 'integer',
        'extra_benefits' => 'array',
    ];

    public function membership(): BelongsTo
    {
        return $this->belongsTo(UserMembership::class, 'user_membership_id');
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(MembershipUsageLog::class, 'balance_id');
    }

    public function facilityCheckins(): HasMany
    {
        return $this->hasMany(FacilityCheckin::class, 'balance_id');
    }
}
