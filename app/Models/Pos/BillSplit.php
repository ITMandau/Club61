<?php

namespace App\Models\Pos;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class BillSplit extends Model
{
    use HasUlids;

    protected $fillable = [
        'order_id',
        'payer_name',
        'split_type',
        'amount_due',
        'status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_due' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function items()
    {
        return $this->hasMany(BillSplitItem::class, 'bill_split_id');
    }
}
