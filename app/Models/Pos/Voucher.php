<?php

namespace App\Models\Pos;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasUlids;

    protected $fillable = [
        'code',
        'discount_type',
        'discount_value',
        'min_order_amount',
        'max_discount_amount',
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
            'quota' => 'integer',
            'used_count' => 'integer',
            'valid_until' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
