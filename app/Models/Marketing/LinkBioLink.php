<?php

namespace App\Models\Marketing;

use App\Models\Coaching\CoachingSetting;
use App\Models\Setting\CompanyProfileSetting;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Tombol halaman link bio Instagram (/links) — Modul 24 Opsi B Tahap 2. Tipe otomatis mengambil tujuan dari data yang
 * sudah ada (tidak diketik ulang), IMAGE = gambar unggahan (mis. pricelist), URL = link bebas yang wajib http/https.
 * Tombol yang tujuannya belum tersedia (mis. WA belum diisi) tidak ditampilkan.
 */
class LinkBioLink extends Model
{
    use HasUlids;

    public const TYPES = [
        'BOOK_COURT' => 'Book Court (halaman booking)',
        'LOCATION' => 'Lokasi (Google Maps)',
        'COACHING_WA' => 'WhatsApp Admin Coaching',
        'CLUB_WA' => 'WhatsApp Admin Club 61',
        'COACHES' => 'Profil Coach',
        'IMAGE' => 'Gambar (mis. Pricelist)',
        'URL' => 'Link lain',
    ];

    protected $fillable = ['label', 'type', 'url', 'image_path', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        // Path gambar dikirim form Livewire (bisa diubah dari browser) — hanya terima file hasil SecureImageUploader.
        static::saving(function (self $link) {
            if ($link->isDirty('image_path') && filled($link->image_path)
                && ! preg_match('#^link-bio/[0-9a-f-]{36}\.jpg$#', (string) $link->image_path)) {
                abort(422, 'Gambar tidak valid. Unggah ulang gambarnya.');
            }
        });

        static::updated(function (self $link) {
            if ($link->wasChanged('image_path')) {
                self::deleteImage($link->getOriginal('image_path'));
            }
        });
        static::deleted(fn (self $link) => self::deleteImage($link->image_path));
    }

    private static function deleteImage(?string $path): void
    {
        if ($path && str_starts_with($path, 'link-bio/')) {
            Storage::disk('public')->delete($path);
        }
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? (string) $this->type;
    }

    /** Tujuan tombol; null = belum bisa ditampilkan. Link ketikan admin selalu dicek ulang http/https saat dibaca. */
    public function href(): ?string
    {
        $custom = CompanyProfileSetting::isSafeWebUrl($this->url) ? trim((string) $this->url) : null;

        return match ($this->type) {
            'BOOK_COURT' => route('customer.booking'),
            'LOCATION' => $custom ?? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode(CompanyProfileSetting::receiptAddress()),
            'COACHING_WA' => CoachingSetting::adminWhatsappUrl('Halo Admin Coaching Club 61, saya mau tanya soal coaching.'),
            'CLUB_WA' => CompanyProfileSetting::whatsappUrl('Halo Admin Club 61, saya mau tanya.'),
            'COACHES' => route('customer.coaching'),
            'IMAGE' => $this->image_path ? '/storage/'.$this->image_path : null,
            'URL' => $custom,
            default => null,
        };
    }

    /** Tombol aktif yang tujuannya tersedia, urut sort_order. */
    public static function visible(): array
    {
        return self::query()->where('is_active', true)->orderBy('sort_order')->orderBy('created_at')->get()
            ->map(fn (self $link) => ['label' => $link->label, 'type' => $link->type, 'href' => $link->href()])
            ->filter(fn (array $link) => filled($link['href']))
            ->values()->all();
    }
}
