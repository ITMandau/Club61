<?php

namespace App\Models\Wellness;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class WellnessFacility extends Model
{
    use HasUlids;

    protected $fillable = [
        'name',
        'max_capacity_per_slot',
        'duration_minutes',
        'price_per_person',
    ];

    protected function casts(): array
    {
        return [
            'max_capacity_per_slot' => 'integer',
            'duration_minutes' => 'integer',
            'price_per_person' => 'decimal:2',
        ];
    }

    public function slots()
    {
        return $this->hasMany(WellnessSlot::class, 'facility_id');
    }
}
