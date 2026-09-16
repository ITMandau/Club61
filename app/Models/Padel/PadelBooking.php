<?php

namespace App\Models\Padel;

use App\Models\Staff\StaffProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PadelBooking extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'booking_code',
        'order_id',
        'user_id',
        'court_id',
        'coach_id',
        'booking_date',
        'start_time',
        'end_time',
        'court_fee',
        'coach_fee',
        'equipment_fee',
        'total_amount',
        'status',
        'qr_code_hash',
        'checked_in_at',
        'reschedule_count',
        'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'date:Y-m-d',
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'checked_in_at' => 'datetime',
            'court_fee' => 'decimal:2',
            'coach_fee' => 'decimal:2',
            'equipment_fee' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'reschedule_count' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function court()
    {
        return $this->belongsTo(PadelCourt::class, 'court_id');
    }

    public function coach()
    {
        return $this->belongsTo(StaffProfile::class, 'coach_id');
    }

    public function equipments()
    {
        return $this->hasMany(PadelBookingEquipment::class, 'booking_id');
    }

    public function order()
    {
        return $this->belongsTo(\App\Models\Pos\Order::class, 'order_id');
    }
}
