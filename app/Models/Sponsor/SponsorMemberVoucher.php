<?php

namespace App\Models\Sponsor;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SponsorMemberVoucher extends Model
{
    use HasUlids;

    protected $fillable = [
        'sponsor_organization_member_id',
        'hours_granted',
        'hours_used',
        'issued_at',
        'expires_at',
        'source',
        'period',
        'issued_by_user_id',
        'acknowledged_at',
    ];

    protected $casts = [
        'hours_granted' => 'decimal:2',
        'hours_used' => 'decimal:2',
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(SponsorOrganizationMember::class, 'sponsor_organization_member_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function remainingHours(): float
    {
        return max(0, (float) $this->hours_granted - (float) $this->hours_used);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return ! $this->isExpired() && $this->remainingHours() > 0;
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledged_at !== null;
    }
}
