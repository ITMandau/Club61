<?php

namespace App\Models\Pos;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasUlids;

    protected $fillable = [
        'order_id',
        'bill_split_id',
        'payment_gateway',
        'transaction_id',
        'snap_token',
        'payment_url',
        'amount',
        'payment_method',
        'status',
        'payload_log',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payload_log' => 'array',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
