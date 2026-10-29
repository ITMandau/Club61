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
        'description',
        'perks',
        'ownership_type',
        'duration_days',
        'price',
        'is_active',
        'sales_channel',
        'created_by',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration_days' => 'integer',
        'is_active' => 'boolean',
        'perks' => 'array',
    ];

    /** Status kartu yang masih bergantung pada paket (nama, durasi, benefit dibaca dari paket). */
    public const IN_USE_STATUSES = ['ACTIVE', 'PENDING_PAYMENT', 'PENDING'];

    /** Saluran penjualan: online (web & aplikasi) dan front desk — perilaku lama. */
    public const SALES_ALL = 'ALL';

    /** Saluran penjualan: khusus front desk (POS Jual Membership) / diberikan admin, mis. paket kontrak corporate. */
    public const SALES_POS_ONLY = 'POS_ONLY';

    public const SALES_CHANNELS = [
        self::SALES_ALL => 'Online (Web & Aplikasi) dan Front Desk',
        self::SALES_POS_ONLY => 'Khusus Front Desk / Admin',
    ];

    /** Paket yang boleh tampil & dibeli customer sendiri (halaman Membership, aplikasi, halaman depan). */
    public function scopeSoldOnline($query)
    {
        return $query->where('sales_channel', self::SALES_ALL);
    }

    public function isSoldOnline(): bool
    {
        return ($this->sales_channel ?? self::SALES_ALL) === self::SALES_ALL;
    }

    protected static function booted(): void
    {
        // Paket yang masih dipakai kartu aktif/menunggu bayar tidak boleh dihapus — dulu bisa, lalu Home member 500,
        // kasir walk-in error, dan pembelian yang dibayar setelahnya gagal aktif. Nonaktifkan saja (is_active = false).
        // Pengaman terakhir untuk semua jalur; tombol hapus di admin sudah menolak lebih dulu dengan pesan jelas.
        static::deleting(fn (self $plan) => $plan->isForceDeleting() || ! $plan->isInUse());
    }

    public function isInUse(): bool
    {
        return $this->userMemberships()->whereIn('status', self::IN_USE_STATUSES)->exists();
    }

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

    /**
     * Deskripsi admin per baris = poin detail paket (halaman membership menampilkan daftar ✓). Poin yang ditulis dalam
     * satu baris dengan pemisah " * " / " • " juga dipecah. Tanda poin di awal baris (-, •, *, ✓, angka) dibuang supaya
     * tidak dobel.
     *
     * @return list<string>
     */
    public function descriptionLines(): array
    {
        $lines = preg_split('/\R|\s+[*•]\s+/u', (string) $this->description) ?: [];

        return array_values(array_filter(
            array_map(fn (string $line) => trim((string) preg_replace('/^\s*(?:[-•*✓✔]|\d+[.)])\s*/u', '', $line)), $lines),
            fn (string $line) => $line !== '',
        ));
    }
}
