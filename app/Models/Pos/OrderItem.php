<?php

namespace App\Models\Pos;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasUlids;

    protected $fillable = [
        'order_id',
        'item_type',
        'reference_id',
        'item_name',
        'quantity',
        'unit_price',
        'subtotal',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function modifiers()
    {
        return $this->hasMany(OrderItemModifier::class, 'order_item_id');
    }
}
