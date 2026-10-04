<?php

namespace App\Models\Padel;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** Tanggal merah / libur nasional — tarif padel mengikuti jam peak hari Minggu. */
class PadelHoliday extends Model
{
    use HasUlids;

    protected $fillable = ['date', 'name'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
