<?php

namespace App\Models\Membership;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MembershipPlan extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'ownership_type',
        'duration_days',
        'price',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function benefits(): HasMany
    {
        return $this->hasMany(MembershipPlanBenefit::class, 'plan_id');
    }

    public function userMemberships(): HasMany
    {
        return $this->hasMany(UserMembership::class, 'plan_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function benefitFor(string $facility): ?MembershipPlanBenefit
    {
        return $this->benefits->firstWhere('facility', strtoupper($facility));
    }
}
