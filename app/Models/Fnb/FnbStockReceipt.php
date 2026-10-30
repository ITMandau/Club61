<?php

namespace App\Models\Fnb;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * Penerimaan Barang F&B (Modul 29): satu nota supplier berisi banyak menu, diinput manual atau diimpor dari Excel.
 * Ditulis hanya oleh FnbStockService. Tidak diubah / dihapus — dibatalkan seluruhnya (stok & pengeluarannya kembali).
 */
class FnbStockReceipt extends Model
{
    use HasUlids;

    public const SOURCES = ['MANUAL' => 'Input Manual', 'IMPORT' => 'Impor Excel'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'item_count' => 'integer',
            'total_amount' => 'decimal:2',
            'voided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $receipt) {
            if (array_diff(array_keys($receipt->getDirty()), ['voided_at', 'voided_by', 'void_reason', 'updated_at']) !== []) {
                throw new LogicException('Penerimaan Barang tidak dapat diubah. Batalkan lalu input ulang.');
            }
        });
        static::deleting(fn () => throw new LogicException('Penerimaan Barang tidak dapat dihapus.'));
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FnbStockMovement::class, 'receipt_id')->where('type', FnbStockMovement::RESTOCK);
    }

    public function cashEntry(): HasOne
    {
        return $this->hasOne(FnbCashEntry::class, 'receipt_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
