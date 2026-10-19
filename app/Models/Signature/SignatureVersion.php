<?php

namespace App\Models\Signature;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Snapshot tampilan yang ditayangkan ke TV. Versi baru dibuat saat layar pindah template; Tayangkan dengan template yang
 * sama memperbarui versi yang sedang tayang (revision naik). "Kembalikan" memindahkan tanda tayang ke versi lama.
 * aired_at / aired_until = masa tayang terakhir versi ini.
 */
class SignatureVersion extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = ['screen_id', 'template_id', 'version', 'revision', 'payload', 'note', 'published_by_id', 'created_at', 'updated_at', 'updated_by_id', 'aired_at', 'aired_by_id', 'aired_until'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'version' => 'integer', 'revision' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime', 'aired_at' => 'datetime', 'aired_until' => 'datetime'];
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public function screen()
    {
        return $this->belongsTo(SignatureScreen::class, 'screen_id');
    }

    public function publishedBy()
    {
        return $this->belongsTo(User::class, 'published_by_id');
    }

    public function airedBy()
    {
        return $this->belongsTo(User::class, 'aired_by_id');
    }
}
