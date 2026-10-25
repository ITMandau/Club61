<?php

namespace App\Models\Pos;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Dua jenis voucher:
 *  - promo (PERCENT / FIXED): dibuat tim marketing di halaman Daftar Voucher (Modul 22). `quota` = SISA kuota (berkurang
 *    saat order lunas), `used_count` = sudah dipakai; kuota awal = quota + used_count. Cara sebar B: baris kampanye +
 *    satu baris per kode (campaign_id, kuota 1). Cara sebar C: `quota` = sisa KLAIM (berkurang saat diklaim).
 *  - saldo (CREDIT): milik satu customer (user_id), terbit saat refund ditolak (refund_id). `balance` = sisa saldo
 *    rupiah yang bisa dipakai sebagian berkali-kali sampai habis / kedaluwarsa.
 */
class Voucher extends Model
{
    use HasUlids;

    public const TYPE_CREDIT = 'CREDIT';

    public const TYPE_PERCENT = 'PERCENT';

    public const TYPE_FIXED = 'FIXED';

    /** Cara sebar promo (PRD §4.1): A kode umum, B kode unik massal, C klaim ke akun. D (otomatis) menyusul. */
    public const DISTRIBUTION_CODE = 'CODE';

    public const DISTRIBUTION_UNIQUE = 'UNIQUE';

    public const DISTRIBUTION_CLAIM = 'CLAIM';

    public const DISTRIBUTIONS = [
        self::DISTRIBUTION_CODE => 'A. Kode umum',
        self::DISTRIBUTION_UNIQUE => 'B. Kode unik massal',
        self::DISTRIBUTION_CLAIM => 'C. Klaim ke akun',
    ];

    /** Batas jumlah kode unik per kampanye (B) — satu kali buat. */
    public const MAX_UNIQUE_CODES = 1000;

    public const CHANNEL_ALL = 'ALL';

    public const CHANNEL_ONLINE = 'ONLINE';

    public const CHANNEL_POS = 'POS';

    public const CHANNELS = [
        self::CHANNEL_ALL => 'Online & kasir',
        self::CHANNEL_ONLINE => 'Online saja',
        self::CHANNEL_POS => 'Kasir saja',
    ];

    /**
     * Kategori yang bisa dipotong promo (PRD §5) — ditetapkan di kode karena tiap kategori butuh sambungan ke checkout-nya.
     * PADEL = biaya sewa lapangan saja (add-on raket/bola tidak ikut). FNB disiapkan: tersimpan di promo, berlaku otomatis
     * begitu kasir F&B tersambung ke VoucherService.
     */
    public const PROMO_CATEGORIES = [
        'PADEL' => 'Sewa lapangan padel',
        'FNB' => 'F&B (disiapkan — belum tersambung)',
    ];

    protected $fillable = [
        'user_id',
        'refund_id',
        'code',
        'name',
        'distribution',
        'campaign_id',
        'discount_type',
        'discount_value',
        'min_order_amount',
        'max_discount_amount',
        'categories',
        'channel',
        'stack_with_membership',
        'balance',
        'quota',
        'used_count',
        'valid_from',
        'valid_until',
        'is_active',
        'created_by_id',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'balance' => 'decimal:2',
            'categories' => 'array',
            'stack_with_membership' => 'boolean',
            'quota' => 'integer',
            'used_count' => 'integer',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function isCredit(): bool
    {
        return $this->discount_type === self::TYPE_CREDIT;
    }

    /** Kategori yang dipotong promo ini (promo lama tanpa isian = sewa lapangan padel). */
    public function promoCategories(): array
    {
        return array_values(array_intersect($this->categories ?: ['PADEL'], array_keys(self::PROMO_CATEGORIES)));
    }

    /** Baris kampanye kode unik (B) — wadah aturan; kodenya sendiri TIDAK bisa dipakai di checkout. */
    public function isCampaign(): bool
    {
        return $this->distribution === self::DISTRIBUTION_UNIQUE && $this->campaign_id === null;
    }

    /** Satu kode dari kampanye kode unik (B), kuota 1. */
    public function isUniqueCode(): bool
    {
        return $this->campaign_id !== null;
    }

    /** Promo klaim ke akun (C): hanya bisa dipakai akun yang sudah klaim; kuota = jumlah klaim. */
    public function isClaim(): bool
    {
        return $this->distribution === self::DISTRIBUTION_CLAIM;
    }

    /** Kuota awal promo (null = tanpa batas). */
    public function totalQuota(): ?int
    {
        return $this->quota === null ? null : (int) $this->quota + (int) $this->used_count;
    }

    public function campaign()
    {
        return $this->belongsTo(self::class, 'campaign_id');
    }

    /** Kode-kode milik kampanye kode unik (B). */
    public function codes()
    {
        return $this->hasMany(self::class, 'campaign_id');
    }

    public function claims()
    {
        return $this->hasMany(VoucherClaim::class, 'voucher_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function refund()
    {
        return $this->belongsTo(Refund::class, 'refund_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by_id');
    }
}
