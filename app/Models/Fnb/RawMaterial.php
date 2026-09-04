<?php

namespace App\Models\Fnb;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class RawMaterial extends Model
{
    use HasUlids;

    protected $fillable = [
        'code',
        'name',
        'unit',
        'current_stock',
        'min_alert_stock',
    ];

    protected function casts(): array
    {
        return [
            'current_stock' => 'decimal:2',
            'min_alert_stock' => 'decimal:2',
        ];
    }
}
