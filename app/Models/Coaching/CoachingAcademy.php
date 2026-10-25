<?php

namespace App\Models\Coaching;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Academy partner coaching (mis. SR Academy) — Modul 24 Opsi B. */
class CoachingAcademy extends Model
{
    use HasUlids;

    protected $fillable = ['name', 'pic_name', 'phone', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function coaches(): HasMany
    {
        return $this->hasMany(Coach::class, 'academy_id');
    }
}
