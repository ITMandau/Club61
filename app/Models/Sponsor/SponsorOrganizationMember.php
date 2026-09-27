<?php

namespace App\Models\Sponsor;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SponsorOrganizationMember extends Model
{
    use HasUlids;

    protected $fillable = [
        'sponsor_organization_id',
        'user_id',
        'status',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(SponsorOrganization::class, 'sponsor_organization_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(SponsorMemberVoucher::class, 'sponsor_organization_member_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    /**
     * Total sisa jam dari SEMUA voucher yang masih berlaku (belum expired) milik anggota ini.
     */
    public function totalRemainingHours(): float
    {
        return (float) $this->vouchers()
            ->where('expires_at', '>', now())
            ->get()
            ->sum(fn (SponsorMemberVoucher $v) => $v->remainingHours());
    }
}
