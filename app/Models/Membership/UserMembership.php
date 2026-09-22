<?php

namespace App\Models\Membership;

use App\Models\Pos\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserMembership extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'membership_code',
        'owner_type',
        'user_id',
        'plan_id',
        'order_id',
        'renewal_of_id',
        'start_date',
        'end_date',
        'status',
        'qr_pass_hash',
        'purchase_price_snapshot',
        'manual_discount_percent',
        'manual_discount_reason',
        'sold_by_admin_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'purchase_price_snapshot' => 'decimal:2',
        'manual_discount_percent' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'plan_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function parentMembership(): BelongsTo
    {
        return $this->belongsTo(UserMembership::class, 'renewal_of_id');
    }

    public function renewalMemberships(): HasMany
    {
        return $this->hasMany(UserMembership::class, 'renewal_of_id');
    }

    public function balances(): HasMany
    {
        return $this->hasMany(UserMembershipBalance::class, 'user_membership_id');
    }

    public function soldByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_by_admin_id');
    }

    public function isActive(): bool
    {
        if ($this->status !== 'ACTIVE') {
            return false;
        }

        if ($this->end_date === null) {
            return true;
        }

        return Carbon::parse($this->end_date)->isFuture() || Carbon::parse($this->end_date)->isToday();
    }

    public function balanceFor(string $facility): ?UserMembershipBalance
    {
        return $this->balances->firstWhere('facility', strtoupper($facility));
    }
}
