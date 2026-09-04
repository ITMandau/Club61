<?php

namespace App\Models\Fnb;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class RecipeBom extends Model
{
    use HasUlids;

    protected $fillable = [
        'menu_id',
        'raw_material_id',
        'quantity_used',
    ];

    protected function casts(): array
    {
        return ['quantity_used' => 'decimal:2'];
    }

    public function menu()
    {
        return $this->belongsTo(FnbMenu::class, 'menu_id');
    }

    public function rawMaterial()
    {
        return $this->belongsTo(RawMaterial::class, 'raw_material_id');
    }
}
