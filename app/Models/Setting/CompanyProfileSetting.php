<?php

namespace App\Models\Setting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CompanyProfileSetting extends Model
{
    protected $table = 'company_profile_settings';

    protected $fillable = [
        'hero_badge_text',
        'hero_badge_text_en',
        'hero_headline_line1',
        'hero_headline_line1_en',
        'hero_headline_highlight',
        'hero_headline_highlight_en',
        'hero_headline_line2',
        'hero_headline_line2_en',
        'hero_subtitle',
        'hero_subtitle_en',
        'court_count',
        'facility_cards',
        'address_line',
        'operating_hours_text',
        'operating_hours_text_en',
        'portal_domain_text',
        'whatsapp_number',
        'maps_embed_url',
        'footer_tagline',
        'footer_tagline_en',
        'footer_social_links',
        'updated_by',
    ];

    protected $casts = [
        'court_count' => 'integer',
        'facility_cards' => 'array',
        'footer_social_links' => 'array',
    ];

    public const CACHE_KEY = 'company_profile_settings';

    /**
     * Daftar tertutup icon_key yang boleh dipakai kartu fasilitas — sengaja bukan upload
     * SVG/HTML bebas dari staf (lihat komponen <x-company-profile.icon>), karena SVG mentah
     * adalah vektor XSS yang sudah ditandai di checklist keamanan proyek ini.
     */
    public const ALLOWED_ICON_KEYS = ['arena', 'wellness', 'lounge', 'gate', 'tournament', 'star'];

    protected static function booted(): void
    {
        static::saved(function () {
            Cache::forget(self::CACHE_KEY);
        });

        static::deleted(function () {
            Cache::forget(self::CACHE_KEY);
        });
    }

    /**
     * Ambil singleton record konten company profile dengan proteksi cache.
     */
    public static function current(): self
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return self::firstOrCreate(
                ['id' => 1],
                [
                    'hero_badge_text' => 'Medan Flagship Venue • Gedung Indosat • Open Daily',
                    'hero_headline_line1' => 'The Sanctuary for',
                    'hero_headline_highlight' => 'Padel Athletes',
                    'hero_headline_line2' => 'in Medan.',
                    'hero_subtitle' => 'Fasilitas terpadu berstandar internasional di Gedung Indosat Medan: {court_count} Lapangan Padel Panoramic Full Indoor ber-AC, Thermal Wellness Recovery (Sauna).',
                    'court_count' => 3,
                    'facility_cards' => [
                        ['icon_key' => 'arena', 'title' => 'Padel Arena', 'subtitle' => '+ Panoramic Courts'],
                        ['icon_key' => 'wellness', 'title' => 'Wellness Suite', 'subtitle' => 'Sauna'],
                        ['icon_key' => 'lounge', 'title' => 'Social Lounge', 'subtitle' => 'Artisan Cafe & Bar'],
                    ],
                    'address_line' => 'Gedung Indosat, Jl. Perintis Kemerdekaan No. 39, Medan, Sumatera Utara.',
                    'operating_hours_text' => 'Open 06:00 – 23:00',
                    'portal_domain_text' => 'portal.club61padel.com',
                ]
            );
        });
    }

    /**
     * Subtitle hero dengan placeholder {court_count} sudah diganti angka asli — dipakai
     * langsung oleh halaman publik supaya staf tidak perlu ketik ulang angka lapangan
     * manual di tengah kalimat kalau jumlahnya berubah.
     */
    public function renderedHeroSubtitle(): string
    {
        return str_replace('{court_count}', (string) $this->court_count, $this->localized('hero_subtitle'));
    }

    /**
     * Ambil versi teks sesuai locale aktif ("id"/"en"). Kolom Inggris ("{field}_en") itu
     * opsional — kalau staf belum isi (atau localenya bukan "en"), otomatis balik ke teks
     * Indonesia yang selalu terisi supaya toggle bahasa tidak pernah menampilkan kotak kosong.
     */
    public function localized(string $field): string
    {
        if (app()->getLocale() === 'en') {
            $enValue = trim((string) ($this->{$field.'_en'} ?? ''));
            if ($enValue !== '') {
                return $enValue;
            }
        }

        return (string) ($this->{$field} ?? '');
    }

    /**
     * Kartu ringkas fasilitas (facility_cards JSON) dengan title/subtitle sudah disesuaikan
     * locale aktif. Struktur tiap kartu boleh punya "title_en"/"subtitle_en" opsional.
     */
    public function localizedFacilityCards(): array
    {
        $locale = app()->getLocale();

        return collect($this->facility_cards ?: [])->map(function (array $card) use ($locale) {
            if ($locale === 'en') {
                $card['title'] = trim((string) ($card['title_en'] ?? '')) !== '' ? $card['title_en'] : $card['title'];
                $card['subtitle'] = trim((string) ($card['subtitle_en'] ?? '')) !== '' ? $card['subtitle_en'] : ($card['subtitle'] ?? '');
            }

            return $card;
        })->all();
    }
}
