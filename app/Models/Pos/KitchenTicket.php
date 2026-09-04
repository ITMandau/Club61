<?php

namespace App\Models\Pos;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class KitchenTicket extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'station',
        'status',
        'created_at',
        'served_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'served_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
