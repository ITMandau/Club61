<?php

namespace App\Models\Setting;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class CompanyProfileFacility extends Model
{
    use HasUlids;

    protected $table = 'company_profile_facilities';

    protected $fillable = [
        'title',
        'title_en',
        'description',
        'description_en',
        'amenities',
        'amenities_en',
        'photo_path',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'amenities' => 'array',
        'amenities_en' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /** Fallback ke teks Indonesia kalau versi Inggris belum diisi staf lewat panel. */
    public function localizedTitle(): string
    {
        if (app()->getLocale() === 'en' && trim((string) $this->title_en) !== '') {
            return $this->title_en;
        }

        return (string) $this->title;
    }

    public function localizedDescription(): string
    {
        if (app()->getLocale() === 'en' && trim((string) $this->description_en) !== '') {
            return $this->description_en;
        }

        return (string) $this->description;
    }

    public function localizedAmenities(): array
    {
        if (app()->getLocale() === 'en' && ! empty($this->amenities_en)) {
            return $this->amenities_en;
        }

        return $this->amenities ?: [];
    }

    public function photoUrl(): ?string
    {
        // Sengaja bikin URL relatif ("/storage/...") sendiri, BUKAN pakai
        // Storage::disk('public')->url() — itu membangun URL absolut dari APP_URL di .env
        // (mis. "http://localhost", tanpa port), yang bisa beda dari port dev server yang
        // sebenarnya dipakai browser (mis. 127.0.0.1:8000) dan bikin gambar gagal dimuat.
        // URL relatif selalu benar apa pun host/port yang sedang diakses.
        return $this->photo_path ? '/storage/'.$this->photo_path : null;
    }
}
