<?php

namespace App\Models\Pos;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Settlement mesin EDC satu loket (tiap loket punya mesin sendiri). Transaksi EDC yang dihitung saat tutup shift =
 * semua transaksi mesin itu di loket itu SEJAK settlement terakhir — jadi ganti shift tanpa settle (cocokkan dengan
 * Audit Report) dan settle di akhir hari (cocokkan dengan struk settlement) memakai angka sistem yang sama.
 */
class PosEdcSettlement extends Model
{
    public const SOURCE_CLOSING = 'CLOSING';

    public const SOURCE_OPENING = 'OPENING';

    /**
     * Awal batch pertama mesin yang belum pernah tercatat settle (saat fitur ini mulai dipakai): dicatat di jam buka shift
     * yang ditutup "belum settle", supaya shift berikutnya menghitung transaksi kedua shift — sama dengan Audit Report.
     */
    public const SOURCE_BASELINE = 'BASELINE';

    protected $fillable = ['counter', 'terminal', 'pos_shift_id', 'settled_at', 'settled_by_id', 'source'];

    protected function casts(): array
    {
        return ['settled_at' => 'datetime'];
    }

    public function settledBy()
    {
        return $this->belongsTo(User::class, 'settled_by_id');
    }
}
