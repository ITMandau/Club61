<?php

namespace App\Models\Pos;

use App\Services\Payment\OnlineGatewayRouter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Satu baris pengaturan: gateway untuk pembayaran online customer & QR otomatis kasir (Modul 27). Berlaku untuk tagihan
 * BARU saja. Dibaca lewat OnlineGatewayRouter (ter-cache, gateway yang belum siap otomatis jatuh ke Midtrans).
 */
class PaymentGatewaySetting extends Model
{
    protected $fillable = ['online_gateway', 'pos_qris_gateway'];

    protected static function booted(): void
    {
        static::deleting(function () {
            throw new \LogicException('Pengaturan gateway pembayaran tidak boleh dihapus.');
        });

        // Flush setelah commit — lihat catatan yang sama di OnlinePaymentMethod.
        static::saved(fn () => DB::afterCommit(fn () => app(OnlineGatewayRouter::class)->flush()));
    }
}
