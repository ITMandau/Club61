<?php

namespace App\Models\Setting;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class CompanyProfileValueProp extends Model
{
    use HasUlids;

    protected $table = 'company_profile_value_props';

    protected $fillable = [
        'icon_key',
        'title',
        'title_en',
        'description',
        'description_en',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public const ALLOWED_ICON_KEYS = ['booking', 'membership', 'sponsor', 'voucher', 'support', 'shield', 'star'];

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
}
