<?php

namespace App\Models\Pos;

use App\Models\Fnb\FnbModifierOption;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class OrderItemModifier extends Model
{
    use HasUlids;

    protected $fillable = [
        'order_item_id',
        'modifier_option_id',
        'modifier_name',
        'extra_price',
    ];

    protected function casts(): array
    {
        return ['extra_price' => 'decimal:2'];
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function modifierOption()
    {
        return $this->belongsTo(FnbModifierOption::class, 'modifier_option_id');
    }
}
