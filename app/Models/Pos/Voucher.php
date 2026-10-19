<?php

namespace App\Models\Pos;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Dua jenis voucher:
 *  - promo (PERCENT / FIXED): dibuat tim marketing di halaman Daftar Voucher (Modul 22). `quota` = SISA kuota (berkurang
 *    saat order lunas), `used_count` = sudah dipakai; kuota awal = quota + used_count.
 *  - saldo (CREDIT): milik satu customer (user_id), terbit saat refund ditolak (refund_id). `balance` = sisa saldo
 *    rupiah yang bisa dipakai sebagian berkali-kali sampai habis / kedaluwarsa.
 */
class Voucher extends Model
{
    use HasUlids;

    public const TYPE_CREDIT = 'CREDIT';

    public const TYPE_PERCENT = 'PERCENT';

    public const TYPE_FIXED = 'FIXED';

    /** Cara sebar promo (PRD §4.1). Tahap 2 baru A (kode umum); B–D menyusul. */
    public const DISTRIBUTION_CODE = 'CODE';

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

    /** Kuota awal promo (null = tanpa batas). */
    public function totalQuota(): ?int
    {
        return $this->quota === null ? null : (int) $this->quota + (int) $this->used_count;
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
