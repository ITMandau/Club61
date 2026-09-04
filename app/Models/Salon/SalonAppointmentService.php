<?php

namespace App\Models\Salon;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class SalonAppointmentService extends Model
{
    use HasUlids;

    protected $fillable = [
        'appointment_id',
        'service_id',
        'sequence_order',
        'duration_minutes',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'sequence_order' => 'integer',
            'duration_minutes' => 'integer',
            'price' => 'decimal:2',
        ];
    }

    public function appointment()
    {
        return $this->belongsTo(SalonAppointment::class, 'appointment_id');
    }

    public function service()
    {
        return $this->belongsTo(SalonService::class, 'service_id');
    }
}
