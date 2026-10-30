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
        'description',
        'open_time',
        'close_time',
        'hourly_rate_regular',
        'hourly_rate_prime',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'hourly_rate_regular' => 'decimal:2',
            'hourly_rate_prime' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** Urutan tampil lapangan di seluruh sistem: "Urutan Tampil" dari Master Data, lalu abjad nama. */
    public function scopeOrdered($query)
    {
        return $query->orderBy($query->qualifyColumn('sort_order'))->orderBy($query->qualifyColumn('name'));
    }

    public function bookings()
    {
        return $this->hasMany(PadelBooking::class, 'court_id');
    }
}
