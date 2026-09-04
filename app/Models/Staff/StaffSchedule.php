<?php

namespace App\Models\Staff;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class StaffSchedule extends Model
{
    use HasUlids;

    protected $fillable = [
        'staff_id',
        'day_of_week',
        'start_time',
        'end_time',
        'is_day_off',
    ];

    protected function casts(): array
    {
        return [
            'is_day_off' => 'boolean',
        ];
    }

    public function staffProfile()
    {
        return $this->belongsTo(StaffProfile::class, 'staff_id');
    }
}
