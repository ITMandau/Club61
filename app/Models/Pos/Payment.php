<?php

namespace App\Models\Pos;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasUlids;

    protected $fillable = [
        'order_id',
        'pos_shift_id',
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

    /**
     * payload_log berisi notifikasi mentah gateway (dulu termasuk signature_key Midtrans), data kasir & bukti EDC.
     * Tidak pernah ikut ke JSON — dulu API tiket customer membocorkannya dan signature-nya bisa dipakai ulang untuk
     * memalsukan webhook "lunas". Kode server tetap membaca atributnya langsung.
     */
    protected $hidden = ['payload_log'];

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

    public function posShift()
    {
        return $this->belongsTo(PosCashierShift::class, 'pos_shift_id');
    }
}
