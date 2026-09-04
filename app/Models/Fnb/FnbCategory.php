<?php

namespace App\Models\Fnb;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class FnbCategory extends Model
{
    use HasUlids;

    protected $fillable = ['name', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function menus()
    {
        return $this->hasMany(FnbMenu::class, 'category_id');
    }
}
