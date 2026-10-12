<?php

namespace App\Models\Signature;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** Susunan tampilan TV: layout (preset / custom), warna & font, running text, logo. */
class SignatureTemplate extends Model
{
    use HasUlids;

    protected $fillable = ['name', 'layout', 'zones', 'theme', 'ticker', 'logo_path'];

    protected function casts(): array
    {
        return ['zones' => 'array', 'theme' => 'array', 'ticker' => 'array'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $template) {
            if ($template->exists && $template->isDirty('logo_path') && $template->getOriginal('logo_path')) {
                Storage::disk('public')->delete($template->getOriginal('logo_path'));
            }
        });
        static::deleting(function (self $template) {
            if ($template->logo_path) {
                Storage::disk('public')->delete($template->logo_path);
            }
        });
    }

    public function playlistItems()
    {
        return $this->hasMany(SignaturePlaylistItem::class, 'template_id')->orderBy('sort_order');
    }
}
