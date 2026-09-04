<?php

namespace App\Models\Merch;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class MerchVariant extends Model
{
    use HasUlids;

    protected $fillable = [
        'product_id',
        'sku',
        'color',
        'size',
        'additional_price',
        'stock_quantity',
    ];

    protected function casts(): array
    {
        return [
            'additional_price' => 'decimal:2',
            'stock_quantity' => 'integer',
        ];
    }

    public function product()
    {
        return $this->belongsTo(MerchProduct::class, 'product_id');
    }
}
