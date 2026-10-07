<?php

namespace App\Models\Pos;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    use HasUlids;

    protected $fillable = [
        'order_id',
        'payment_id',
        'padel_booking_id',
        'requested_by_id',
        'refund_amount',
        'reason',
        'status',
        'refund_method',
        'refund_reference',
        'processed_at',
        'processed_by_id',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'refund_amount' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Refund $refund) {
            if ($refund->status === 'PROCESSED' && $refund->processed_at === null) {
                $refund->processed_at = now();
            }
        });

        // Uang keluar masuk Buku Transaksi (Modul 17) saat refund jadi PROCESSED — di jalur mana pun (pembatalan admin,
        // antrian refund). Di transaksi DB yang sama dengan penyimpanan refund: gagal tulis buku = refund ikut gagal.
        static::saved(function (Refund $refund) {
            if ($refund->status === 'PROCESSED' && ($refund->wasRecentlyCreated || $refund->wasChanged('status'))) {
                app(\App\Services\Finance\LedgerWriter::class)->recordRefund($refund);
            }
        });
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function processedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'processed_by_id');
    }

    /** Booking yang dibatalkan lewat pengajuan refund (Kelola Pemesanan); null untuk refund otomatis sistem. */
    public function booking()
    {
        return $this->belongsTo(\App\Models\Padel\PadelBooking::class, 'padel_booking_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'requested_by_id');
    }

    /** Voucher saldo yang diterbitkan saat refund ini ditolak. */
    public function voucher()
    {
        return $this->hasOne(Voucher::class, 'refund_id');
    }
}
