<?php

namespace App\Models\Membership;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipPlanBenefit extends Model
{
    use HasUlids;

    protected $fillable = [
        'plan_id',
        'facility',
        'quota_type',
        'quota_value',
        'discount_percent',
        'booking_priority_days',
        'time_window_start',
        'time_window_end',
        'extra_benefits',
    ];

    protected $casts = [
        'quota_value' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'booking_priority_days' => 'integer',
        'extra_benefits' => 'array',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'plan_id');
    }
}
