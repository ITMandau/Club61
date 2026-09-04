<?php

namespace App\Models\Padel;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class CourtEquipment extends Model
{
    use HasUlids;

    protected $table = 'court_equipments';

    protected $fillable = [
        'name',
        'type',
        'rental_price',
        'stock_quantity',
    ];

    protected function casts(): array
    {
        return [
            'rental_price' => 'decimal:2',
            'stock_quantity' => 'integer',
        ];
    }
}
