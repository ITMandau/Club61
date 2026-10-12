<?php

namespace App\Models\Signature;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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
        // File lama dibuang begitu diganti / kontennya dihapus.
        static::saving(function (self $content) {
            if ($content->exists && $content->isDirty('file_path') && $content->getOriginal('file_path')) {
                Storage::disk('public')->delete($content->getOriginal('file_path'));
            }
        });
        static::deleting(function (self $content) {
            if ($content->file_path) {
                Storage::disk('public')->delete($content->file_path);
            }
        });
    }

    public function playlistItems()
    {
        return $this->hasMany(SignaturePlaylistItem::class, 'content_id');
    }
}
