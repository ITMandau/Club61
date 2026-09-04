<?php

namespace App\Models\Gym;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class GymPackage extends Model
{
    use HasUlids;

    protected $fillable = [
        'name',
        'duration_days',
        'visit_limit',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
            'visit_limit' => 'integer',
            'price' => 'decimal:2',
        ];
    }
}
