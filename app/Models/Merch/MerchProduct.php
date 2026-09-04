<?php

namespace App\Models\Merch;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class MerchProduct extends Model
{
    use HasUlids;

    protected $fillable = [
        'name',
        'brand',
        'description',
        'base_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function variants()
    {
        return $this->hasMany(MerchVariant::class, 'product_id');
    }
}
