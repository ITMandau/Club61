<?php

namespace App\Models\Fnb;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FnbModifierGroup extends Model
{
    use HasUlids;

    protected $fillable = ['name', 'is_required', 'max_selection'];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'max_selection' => 'integer',
        ];
    }

    public function options()
    {
        return $this->hasMany(FnbModifierOption::class, 'group_id');
    }

    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(FnbMenu::class, 'fnb_menu_modifier_group', 'group_id', 'menu_id');
    }
}
