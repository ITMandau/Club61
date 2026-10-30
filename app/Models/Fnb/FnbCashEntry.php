<?php

namespace App\Models\Fnb;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Satu catatan Pengeluaran F&B (Modul 29). Tidak dihapus — dibatalkan dengan alasan
 * (voided_*). Penerimaan Barang membuat entrinya otomatis (receipt_id) dan ikut batal saat penerimaannya dibatalkan.
 */
class FnbCashEntry extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'amount' => 'decimal:2',
            'voided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $entry) {
            if (array_diff(array_keys($entry->getDirty()), ['voided_at', 'voided_by', 'void_reason', 'updated_at']) !== []) {
                throw new LogicException('Catatan pengeluaran tidak dapat diubah. Batalkan lalu catat ulang.');
            }
        });
        static::deleting(fn () => throw new LogicException('Catatan pengeluaran tidak dapat dihapus. Gunakan Batalkan.'));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FnbCashCategory::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(FnbStockReceipt::class, 'receipt_id');
    }
}
