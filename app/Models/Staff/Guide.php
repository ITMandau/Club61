<?php

namespace App\Models\Staff;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Panduan karyawan (mis. modul resepsionis). PDF diubah jadi gambar per halaman di server (GuideService) — yang dibaca
 * lewat /panduan/{token} hanya gambar ber-watermark, file aslinya tidak disimpan & tidak pernah dikirim ke browser.
 * Link tanpa login seperti layar Digital Signage: nonaktif = link mati; buat ulang link = link lama mati permanen.
 */
class Guide extends Model
{
    use HasUlids;

    public const TOKEN_LENGTH = 40;

    /** Disk private: tidak ada URL publik ke file di sini. */
    public const DISK = 'local';

    protected $fillable = ['title', 'description', 'version', 'token', 'is_active', 'pages_path', 'pages_count', 'source_name', 'created_by_id'];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'pages_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Gambar halaman ikut dihapus — setelah commit, supaya hapus yang dibatalkan tidak meninggalkan panduan tanpa halaman.
        static::deleted(function (Guide $guide) {
            $path = $guide->pages_path;
            if ($path) {
                DB::afterCommit(fn () => Storage::disk(self::DISK)->deleteDirectory($path));
            }
        });
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(GuideAccessLog::class);
    }

    public function shareUrl(): string
    {
        return route('guide.show', $this->token);
    }

    /** Path gambar halaman ke-n (1-based) di disk local, atau null kalau di luar jumlah halaman. */
    public function pagePath(int $page): ?string
    {
        if (! $this->pages_path || $page < 1 || $page > $this->pages_count) {
            return null;
        }

        return $this->pages_path.'/'.$page.'.jpg';
    }
}
