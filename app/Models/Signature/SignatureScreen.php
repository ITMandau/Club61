<?php

namespace App\Models\Signature;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** Satu TV. Dibuka lewat /signature/{token} tanpa login; hanya menayangkan versi yang sudah ditayangkan. */
class SignatureScreen extends Model
{
    use HasUlids;

    public const NAME_DISPLAYS = ['FULL' => 'Nama lengkap', 'INITIALS' => 'Inisial (B. S.)', 'BOOKED' => 'Hanya tulisan Booked'];

    protected $fillable = ['name', 'token', 'template_id', 'name_display', 'sound_enabled', 'operating_hours_only', 'published_version_id', 'last_seen_at'];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return ['sound_enabled' => 'boolean', 'operating_hours_only' => 'boolean', 'last_seen_at' => 'datetime'];
    }

    public function template()
    {
        return $this->belongsTo(SignatureTemplate::class, 'template_id');
    }

    public function publishedVersion()
    {
        return $this->belongsTo(SignatureVersion::class, 'published_version_id');
    }

    public function versions()
    {
        return $this->hasMany(SignatureVersion::class, 'screen_id')->orderByDesc('version');
    }

    /** TV melapor tiap ±12 detik; lebih dari 2 menit tanpa kabar = offline. */
    public function isOnline(): bool
    {
        return $this->last_seen_at !== null && $this->last_seen_at->gt(now()->subMinutes(2));
    }
}
