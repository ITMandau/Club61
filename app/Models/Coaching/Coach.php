<?php

namespace App\Models\Coaching;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Coach partner (bukan karyawan) — Modul 24 Opsi B. Coach yang sudah pernah dipakai booking tidak bisa dihapus
 * (riwayat booking menyimpannya), cukup dinonaktifkan. Tahap 2: profil untuk customer (foto, olahraga, poin
 * keunggulan) — tampil hanya kalau aktif & show_on_profile dinyalakan admin. Nomor WA / email / catatan coach
 * TIDAK pernah ditampilkan ke customer.
 */
class Coach extends Model
{
    use HasUlids;

    public const TITLES = ['HEAD_COACH' => 'Head Coach', 'COACH' => 'Coach'];

    public const MAX_HIGHLIGHTS = 6;

    protected $fillable = ['academy_id', 'name', 'title', 'phone', 'email', 'photo_path', 'sport', 'motto', 'highlights', 'notes', 'is_active', 'show_on_profile', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'show_on_profile' => 'boolean', 'highlights' => 'array', 'sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        // Path foto dikirim form Livewire (bisa diubah dari browser) — hanya terima file hasil SecureImageUploader.
        static::saving(function (self $coach) {
            if ($coach->isDirty('photo_path') && filled($coach->photo_path)
                && ! preg_match('#^coaches/[0-9a-f-]{36}\.jpg$#', (string) $coach->photo_path)) {
                abort(422, 'Foto coach tidak valid. Unggah ulang fotonya.');
            }
        });

        static::deleting(function (self $coach) {
            if ($coach->isUsed()) {
                throw new \LogicException('Coach ini sudah dipakai booking — nonaktifkan saja.');
            }
        });

        // Foto lama dibuang setelah diganti / coach dihapus (hanya file milik folder coach).
        static::updated(function (self $coach) {
            if ($coach->wasChanged('photo_path')) {
                self::deletePhoto($coach->getOriginal('photo_path'));
            }
        });
        static::deleted(fn (self $coach) => self::deletePhoto($coach->photo_path));
    }

    private static function deletePhoto(?string $path): void
    {
        if ($path && str_starts_with($path, 'coaches/')) {
            Storage::disk('public')->delete($path);
        }
    }

    public function academy(): BelongsTo
    {
        return $this->belongsTo(CoachingAcademy::class, 'academy_id');
    }

    /** Coach yang tampil di halaman profil customer & API. */
    public function scopePublicProfile(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('show_on_profile', true);
    }

    public function isUsed(): bool
    {
        return CoachingBooking::where('head_coach_id', $this->id)->exists()
            || DB::table('coaching_booking_coaches')->where('coach_id', $this->id)->exists();
    }

    public function titleLabel(): string
    {
        return self::TITLES[$this->title] ?? (string) $this->title;
    }

    /** Label pilihan: "Maimun (Head Coach · SR Academy)". */
    public function optionLabel(): string
    {
        return $this->name.' ('.trim($this->titleLabel().($this->academy ? ' · '.$this->academy->name : '')).')';
    }

    /** URL relatif foto ("/storage/…") — sama seperti foto fasilitas (tidak bergantung APP_URL). */
    public function photoUrl(): ?string
    {
        return $this->photo_path ? '/storage/'.$this->photo_path : null;
    }

    /** Poin keunggulan yang sudah dirapikan (teks kosong dibuang, maks. MAX_HIGHLIGHTS). */
    public function highlightList(): array
    {
        return array_slice(array_values(array_filter(
            array_map(fn ($line) => trim((string) (is_array($line) ? ($line['text'] ?? '') : $line)), (array) ($this->highlights ?? [])),
            fn (string $line) => $line !== '',
        )), 0, self::MAX_HIGHLIGHTS);
    }

    /** Motto: baris kosong & spasi berlebih dibuang, maks. 3 baris; kosong → null (tidak tampil). */
    public static function cleanMotto(?string $motto): ?string
    {
        $lines = array_filter(array_map(fn ($line) => trim(preg_replace('/[ \t]+/', ' ', $line)), preg_split('/\R/', (string) $motto)), fn ($line) => $line !== '');
        $clean = implode("\n", array_slice($lines, 0, 3));

        return $clean !== '' ? $clean : null;
    }

    /** Inisial untuk avatar saat belum ada foto. */
    public function initials(): string
    {
        $words = preg_split('/\s+/', trim($this->name)) ?: [];

        return mb_strtoupper(implode('', array_map(fn ($w) => mb_substr($w, 0, 1), array_slice(array_filter($words), 0, 2)))) ?: 'C';
    }
}
