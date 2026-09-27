<?php

namespace App\Models\Sponsor;

use App\Models\Membership\UserMembership;
use App\Models\Membership\UserMembershipBalance;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SponsorOrganization extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'name',
        'user_membership_id',
        'sponsor_admin_user_id',
        'status',
    ];

    public function userMembership(): BelongsTo
    {
        return $this->belongsTo(UserMembership::class, 'user_membership_id');
    }

    public function sponsorAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sponsor_admin_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(SponsorOrganizationMember::class, 'sponsor_organization_id');
    }

    public function accessSchedules(): HasMany
    {
        return $this->hasMany(SponsorAccessSchedule::class, 'sponsor_organization_id');
    }

    /**
     * Total jam yang SUDAH PERNAH dirilis (lifetime) ke seluruh anggota organisasi ini — termasuk
     * voucher yang sudah expired atau anggotanya sudah di-revoke. Ini murni informasional (jumlah
     * dari tabel voucher), independen dari ada/tidaknya kuota kontrak di bawah.
     */
    public function totalHoursReleased(): float
    {
        return (float) SponsorMemberVoucher::whereHas('member', fn ($q) => $q->where('sponsor_organization_id', $this->id))
            ->sum('hours_granted');
    }

    /**
     * Saldo kuota jam bermain PADEL milik organisasi ini — BUKAN field terpisah, tapi diambil
     * langsung dari benefit paket membership yang dibeli (dikonfigurasi staf lewat Master Data >
     * Paket Membership > Matriks Entitlement Fasilitas, facility=PADEL, quota_type=HOURS), lalu
     * disalin jadi UserMembershipBalance saat membership aktif. Null kalau paket ini tidak
     * dikonfigurasi kuota jam PADEL sama sekali (facility PADEL tidak ada atau quota_type-nya
     * bukan HOURS) — artinya organisasi ini tidak dibatasi kuota.
     */
    public function padelHourBalance(): ?UserMembershipBalance
    {
        $balance = $this->userMembership?->balanceFor('PADEL');

        return ($balance && $balance->quota_type === 'HOURS') ? $balance : null;
    }

    /**
     * Total kuota kontrak (mis. "200 jam" dari paket) — null kalau tidak dibatasi.
     */
    public function totalQuota(): ?float
    {
        return $this->padelHourBalance()?->initial_quota !== null
            ? (float) $this->padelHourBalance()->initial_quota
            : null;
    }

    /**
     * Sisa kuota kontrak yang masih boleh dirilis staf/PIC ke tim — berkurang otomatis tiap kali
     * voucher dirilis (lihat SponsorOrganizationService), lewat ledger MembershipBalanceService
     * yang sama dengan yang dipakai fasilitas lain (audit trail lengkap di MembershipUsageLog).
     * Null berarti organisasi ini tidak dibatasi kuota.
     */
    public function remainingQuota(): ?float
    {
        $balance = $this->padelHourBalance();

        return $balance !== null ? (float) $balance->remaining_quota : null;
    }
}
