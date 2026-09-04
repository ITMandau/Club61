<?php

namespace App\Models\Fnb;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class FnbMenu extends Model
{
    use HasUlids;

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'image_url',
        'base_price',
        'station',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'is_available' => 'boolean',
        ];
    }

    public function category()
    {
        return $this->belongsTo(FnbCategory::class, 'category_id');
    }

    public function recipes()
    {
        return $this->hasMany(RecipeBom::class, 'menu_id');
    }
}
