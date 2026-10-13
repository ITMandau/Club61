<?php

namespace App\Models\Signature;

use App\Services\Signature\SignatureFileJanitor;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** Satu konten di library Signature: gambar, video, atau widget dari sistem. */
class SignatureContent extends Model
{
    use HasUlids;

    public const TYPES = [
        'IMAGE' => 'Gambar',
        'VIDEO' => 'Video MP4',
        'MONITORING' => 'Monitoring Lapangan',
        'SCHEDULE' => 'Jadwal Hari Ini',
        'SPONSOR' => 'Sponsor',
        'CLOCK' => 'Jam & Tanggal',
    ];

    /** Jenis yang berupa file upload (sisanya widget dari sistem). */
    public const FILE_TYPES = ['IMAGE', 'VIDEO'];

    protected $fillable = ['name', 'type', 'file_path', 'url', 'duration_seconds', 'sound', 'volume', 'is_active'];

    protected function casts(): array
    {
        return ['duration_seconds' => 'integer', 'sound' => 'boolean', 'volume' => 'integer', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        // File lama dibuang saat diganti / kontennya dihapus — kecuali masih / baru saja tayang di TV (SignatureFileJanitor).
        static::updated(function (self $content) {
            if ($content->wasChanged('file_path')) {
                app(SignatureFileJanitor::class)->release($content->getOriginal('file_path'));
            }
        });
        static::deleted(fn (self $content) => app(SignatureFileJanitor::class)->release($content->file_path));
    }

    public function playlistItems()
    {
        return $this->hasMany(SignaturePlaylistItem::class, 'content_id');
    }
}
