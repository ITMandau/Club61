<?php

namespace App\Models\Wellness;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class WellnessSlot extends Model
{
    use HasUlids;

    protected $fillable = [
        'facility_id',
        'session_date',
        'start_time',
        'end_time',
        'max_capacity',
        'booked_count',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'max_capacity' => 'integer',
            'booked_count' => 'integer',
        ];
    }

    public function facility()
    {
        return $this->belongsTo(WellnessFacility::class, 'facility_id');
    }

    public function bookings()
    {
        return $this->hasMany(WellnessBooking::class, 'slot_id');
    }

    public function waitlists()
    {
        return $this->hasMany(WellnessWaitlist::class, 'slot_id');
    }
}
