<?php

namespace App\Models\Pos;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class BillSplitItem extends Model
{
    use HasUlids;

    protected $fillable = [
        'bill_split_id',
        'order_item_id',
        'quantity_allocated',
        'allocated_amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity_allocated' => 'integer',
            'allocated_amount' => 'decimal:2',
        ];
    }

    public function billSplit()
    {
        return $this->belongsTo(BillSplit::class, 'bill_split_id');
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }
}
