<?php

namespace App\Models\Fnb;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

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
}
