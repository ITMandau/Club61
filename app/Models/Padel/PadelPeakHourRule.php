<?php

namespace App\Models\Padel;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** Satu rentang jam peak pada satu hari (0 = Minggu ... 6 = Sabtu). end_hour eksklusif, 24 = tengah malam. */
class PadelPeakHourRule extends Model
{
    use HasUlids;

    protected $fillable = ['day_of_week', 'start_hour', 'end_hour'];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'start_hour' => 'integer',
            'end_hour' => 'integer',
        ];
    }
}
