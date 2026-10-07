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
        'paid_at',
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
            'paid_at' => 'datetime',
            'payload_log' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Saat uang diterima (Modul 17): diisi sekali ketika status berubah jadi SUCCESS / DUPLICATE, di jalur mana pun.
        // Dipakai Buku Transaksi & riwayat — updated_at ikut berubah setiap kali baris pembayaran disentuh.
        static::saving(function (Payment $payment) {
            if ($payment->paid_at === null && $payment->isDirty('status')
                && in_array($payment->status, ['SUCCESS', \App\Services\Payment\PaymentOrchestratorService::DUPLICATE_STATUS], true)) {
                $payment->paid_at = now();
            }
        });
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
