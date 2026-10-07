<?php

namespace App\Models\Finance;

use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\Pos\Refund;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Satu baris Buku Transaksi (Modul 17): satu kategori pendapatan dari satu pembayaran atau refund.
 * IMMUTABLE — ditulis sekali oleh App\Services\Finance\LedgerWriter saat uang masuk / refund diproses, tidak bisa
 * diubah atau dihapus lewat Eloquent. Koreksi = baris baru (refund), bukan edit.
 *
 * Definisi angka (PRD §2): net_amount = penjualan bersih (pendapatan), service_amount = biaya layanan (terpisah),
 * tax_amount = pajak terkumpul (BUKAN pendapatan), total_amount = uang diterima (negatif untuk refund),
 * benefit_amount = nilai kuota/voucher yang dipakai (informasi, non-tunai).
 */
class LedgerEntry extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    public const TYPE_PAYMENT = 'PAYMENT';

    public const TYPE_OVERPAYMENT = 'OVERPAYMENT';

    public const TYPE_REFUND = 'REFUND';

    public const CATEGORIES = [
        'SEWA_LAPANGAN' => 'Sewa Lapangan Padel',
        'ADDON_PADEL' => 'Add-on Padel',
        'MEMBERSHIP' => 'Membership',
        'FNB' => 'F&B',
        'WELLNESS' => 'Wellness',
        'GYM' => 'Gym',
        'MERCH' => 'Merchandise',
        'SALON' => 'Salon',
        'LAINNYA' => 'Lainnya',
    ];

    public const SOURCES = [
        'POS_WALKIN_PADEL' => 'POS Walk-In Padel',
        'ONLINE_PADEL' => 'Booking Online Padel',
        'RESCHEDULE_DELTA_POS' => 'Pelunasan Selisih (Kasir)',
        'RESCHEDULE_DELTA_ONLINE' => 'Pelunasan Selisih (Online)',
        'POS_MEMBERSHIP' => 'POS Membership',
        'ONLINE_MEMBERSHIP' => 'Membership Online',
        'POS_FNB' => 'POS F&B',
        'LAINNYA' => 'Lainnya',
    ];

    public const ENTRY_TYPES = [
        self::TYPE_PAYMENT => 'Pembayaran',
        self::TYPE_OVERPAYMENT => 'Kelebihan Bayar',
        self::TYPE_REFUND => 'Refund',
    ];

    protected $table = 'ledger_entries';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
            'gross_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'service_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'benefit_amount' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Baris buku transaksi tidak boleh diubah. Koreksi dicatat sebagai baris baru (refund).');
        });

        static::deleting(function () {
            throw new LogicException('Baris buku transaksi tidak boleh dihapus.');
        });
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id')->withTrashed();
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function refund()
    {
        return $this->belongsTo(Refund::class, 'refund_id');
    }

    public static function categoryLabel(?string $code): string
    {
        return self::CATEGORIES[$code] ?? (string) $code;
    }

    public static function sourceLabel(?string $code): string
    {
        return self::SOURCES[$code] ?? (string) $code;
    }
}
