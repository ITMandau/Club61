<?php

namespace App\Models\Pos;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Dua jenis voucher:
 *  - promo (PERCENT / FIXED): kode umum dengan kuota pemakaian;
 *  - saldo (CREDIT): milik satu customer (user_id), terbit saat refund ditolak (refund_id). `balance` = sisa saldo
 *    rupiah yang bisa dipakai sebagian berkali-kali sampai habis / kedaluwarsa.
 */
class Voucher extends Model
{
    use HasUlids;

    public const TYPE_CREDIT = 'CREDIT';

    protected $fillable = [
        'user_id',
        'refund_id',
        'code',
        'discount_type',
        'discount_value',
        'min_order_amount',
        'max_discount_amount',
        'balance',
        'quota',
        'used_count',
        'valid_until',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'balance' => 'decimal:2',
            'quota' => 'integer',
            'used_count' => 'integer',
            'valid_until' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function isCredit(): bool
    {
        return $this->discount_type === self::TYPE_CREDIT;
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function refund()
    {
        return $this->belongsTo(Refund::class, 'refund_id');
    }
}
