<?php

namespace App\Models\Pos;

use App\Services\Payment\OnlinePaymentMethodService;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Pengaturan satu metode pembayaran online. Metode bawaan berasal dari OnlinePaymentCatalog (kode & kanalnya tetap);
 * admin bisa MENAMBAH metode jenis QRIS / VA (`is_custom`) dengan kanal dari daftar kode resmi gateway. Kode metode
 * tidak boleh berubah dan metode tidak boleh dihapus (transaksi lama menyimpan kodenya) — nonaktifkan saja.
 */
class OnlinePaymentMethod extends Model
{
    use HasUlids;

    protected $fillable = [
        'code', 'label', 'description', 'badge', 'kind', 'midtrans_channels', 'doku_channels', 'is_custom',
        'is_active', 'show_at_pos', 'sort_order', 'min_amount', 'max_amount',
    ];

    protected function casts(): array
    {
        return [
            'midtrans_channels' => 'array',
            'doku_channels' => 'array',
            'is_custom' => 'boolean',
            'is_active' => 'boolean',
            'show_at_pos' => 'boolean',
            'sort_order' => 'integer',
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $method) {
            if ($method->isDirty('code')) {
                throw new \LogicException('Kode metode pembayaran tidak boleh diubah.');
            }
            if (! $method->is_custom && $method->isDirty(['kind', 'midtrans_channels', 'doku_channels', 'is_custom'])) {
                throw new \LogicException('Kanal metode pembayaran bawaan tidak boleh diubah.');
            }
        });

        static::deleting(function () {
            throw new \LogicException('Metode pembayaran tidak boleh dihapus — nonaktifkan saja.');
        });

        // Flush SETELAH commit: toggle / urutan disimpan di dalam transaksi — flush sebelum commit membuat request
        // lain sempat membaca data lama dan menyimpannya lagi ke cache (QRIS yang baru dinonaktifkan tetap ditawarkan).
        static::saved(fn () => \Illuminate\Support\Facades\DB::afterCommit(fn () => app(OnlinePaymentMethodService::class)->flush()));
    }
}
