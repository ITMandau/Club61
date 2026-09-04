<?php

namespace App\Models\Salon;

use App\Models\Staff\StaffProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class SalonAppointment extends Model
{
    use HasUlids;

    protected $fillable = [
        'appointment_code',
        'user_id',
        'stylist_id',
        'appointment_date',
        'start_time',
        'end_time',
        'total_duration_minutes',
        'total_amount',
        'status',
        'qr_code_hash',
    ];

    protected function casts(): array
    {
        return [
            'appointment_date' => 'date',
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'total_duration_minutes' => 'integer',
            'total_amount' => 'decimal:2',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function stylist()
    {
        return $this->belongsTo(StaffProfile::class, 'stylist_id');
    }

    public function services()
    {
        return $this->hasMany(SalonAppointmentService::class, 'appointment_id');
    }
}
