<?php

namespace App\Models\Fnb;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Satu baris Kartu Stok F&B (Modul 29). Ditulis hanya oleh FnbStockService dan tidak diedit / dihapus — kesalahan
 * dikoreksi dengan Penyesuaian Stok, Penerimaan Barang yang salah dibatalkan (voided_* + baris RESTOCK_VOID).
 */
class FnbStockMovement extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    public const SALE = 'SALE';

    public const RESTOCK = 'RESTOCK';

    public const RESTOCK_VOID = 'RESTOCK_VOID';

    public const ADJUSTMENT = 'ADJUSTMENT';

    public const OPNAME = 'OPNAME';

    public const TYPES = [
        self::SALE => 'Penjualan',
        self::RESTOCK => 'Penerimaan Barang',
        self::RESTOCK_VOID => 'Pembatalan Penerimaan',
        self::ADJUSTMENT => 'Penyesuaian Stok',
        self::OPNAME => 'Stok Opname',
    ];

    /** Alasan Penyesuaian (pengurangan dinilai qty × HPP sebagai kerugian stok, kecuali NOT_A_LOSS). */
    public const REASONS = [
        'DAMAGED' => 'Rusak / Terbuang',
        'EXPIRED' => 'Kedaluwarsa',
        'LOST' => 'Hilang',
        'INTERNAL' => 'Dipakai Internal',
        'RETURNED_CONSIGNOR' => 'Dikembalikan ke Penitip',
        'WRONG_INPUT' => 'Salah Input',
        'OTHER' => 'Lainnya',
    ];

    /** Pengurangan dengan alasan ini bukan kerugian klub (koreksi input / barang titipan kembali ke pemiliknya). */
    public const NOT_A_LOSS = ['WRONG_INPUT', 'RETURNED_CONSIGNOR'];

    protected $table = 'fnb_stock_movements';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'qty_change' => 'integer',
            'stock_after' => 'integer',
            'unit_cost' => 'decimal:2',
            'purchase_total' => 'decimal:2',
            'voided_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Baris lama hanya boleh diberi tanda batal (Penerimaan Barang) — angka & jenisnya tidak pernah berubah.
        static::updating(function (self $movement) {
            if (array_diff(array_keys($movement->getDirty()), ['voided_at', 'voided_by', 'void_reason']) !== []) {
                throw new LogicException('Kartu stok tidak dapat diubah. Gunakan Penyesuaian.');
            }
        });
        static::deleting(fn () => throw new LogicException('Kartu stok tidak dapat dihapus.'));
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(FnbMenu::class, 'menu_id');
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(FnbStockReceipt::class, 'receipt_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function typeLabel(): string
    {
        return $this->type === self::ADJUSTMENT && $this->reason
            ? self::TYPES[self::ADJUSTMENT].' · '.(self::REASONS[$this->reason] ?? $this->reason)
            : (self::TYPES[$this->type] ?? $this->type);
    }
}
