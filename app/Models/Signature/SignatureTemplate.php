<?php

namespace App\Models\Signature;

use App\Services\Signature\SignatureFileJanitor;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

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
        // Logo lama dibuang saat diganti / templatenya dihapus — kecuali masih / baru saja tayang di TV.
        static::updated(function (self $template) {
            if ($template->wasChanged('logo_path')) {
                app(SignatureFileJanitor::class)->release($template->getOriginal('logo_path'));
            }
        });
        static::deleted(fn (self $template) => app(SignatureFileJanitor::class)->release($template->logo_path));
    }

    public function playlistItems()
    {
        return $this->hasMany(SignaturePlaylistItem::class, 'template_id')->orderBy('sort_order');
    }
}
