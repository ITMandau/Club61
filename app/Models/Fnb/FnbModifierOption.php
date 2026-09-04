<?php

namespace App\Models\Fnb;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class FnbModifierOption extends Model
{
    use HasUlids;

    protected $fillable = ['group_id', 'name', 'extra_price'];

    protected function casts(): array
    {
        return ['extra_price' => 'decimal:2'];
    }

    public function group()
    {
        return $this->belongsTo(FnbModifierGroup::class, 'group_id');
    }
}
