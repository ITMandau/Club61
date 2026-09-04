<?php

namespace App\Models\Padel;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PadelCourt extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'hourly_rate_regular',
        'hourly_rate_prime',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'hourly_rate_regular' => 'decimal:2',
            'hourly_rate_prime' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function bookings()
    {
        return $this->hasMany(PadelBooking::class, 'court_id');
    }
}
