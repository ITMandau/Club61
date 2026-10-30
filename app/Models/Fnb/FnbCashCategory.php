<?php

namespace App\Models\Fnb;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kategori Pengeluaran F&B (Modul 29). system_key RESTOCK = Pembelian Persediaan otomatis dari Penerimaan Barang
 * (tidak bisa dinonaktifkan). Pemasukan F&B hanya dari penjualan kasir (Buku Transaksi), tidak ada kategori pemasukan.
 */
class FnbCashCategory extends Model
{
    use HasUlids;

    public const RESTOCK_KEY = 'RESTOCK';

    protected $fillable = ['name', 'is_stock_purchase', 'system_key', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_stock_purchase' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(FnbCashEntry::class, 'category_id');
    }

    public function isSystem(): bool
    {
        return $this->system_key !== null;
    }
}
