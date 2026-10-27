<?php

namespace App\Models\Setting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Template Invoice PDF (lampiran email invoice lunas) — diatur di Keuangan → Template Invoice. Satu baris (id 1);
 * kolom kosong memakai teks bawaan, jadi invoice tetap lengkap walau belum pernah diatur.
 */
class InvoiceTemplateSetting extends Model
{
    public const CACHE_KEY = 'invoice_template_settings';

    /** Logo bawaan (sebelum admin mengunggah logo invoice sendiri). */
    public const DEFAULT_LOGO = 'images/identity/logo-terracotta.png';

    /** Warna aksen — hanya palet brand Club 61 (teks putih di atasnya tetap terbaca). */
    public const ACCENTS = [
        'terra' => ['label' => 'Terakota', 'hex' => '#662721'],
        'brown' => ['label' => 'Cokelat', 'hex' => '#4F2F2A'],
        'ink' => ['label' => 'Hitam Arang', 'hex' => '#231815'],
    ];

    public const DEFAULTS = [
        'business_name' => 'Club 61 Padel & Wellness',
        'document_title' => 'Invoice',
        'status_label' => 'Lunas',
        'intro_text' => '',
        'notes_text' => '',
        'footer_text' => 'Invoice ini diterbitkan secara elektronik oleh Club 61 dan sah tanpa tanda tangan.',
        'accent' => 'terra',
    ];

    protected $fillable = [
        'logo_path', 'business_name', 'address', 'phone', 'email', 'website', 'tax_id',
        'document_title', 'status_label', 'intro_text', 'notes_text', 'footer_text', 'accent',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));

        // Logo lama dibuang setelah gantinya benar-benar tersimpan (pola sama dengan foto Menu F&B).
        static::updated(function (self $setting) {
            $old = $setting->getOriginal('logo_path');
            if ($setting->wasChanged('logo_path') && $old) {
                DB::afterCommit(fn () => Storage::disk('public')->delete($old));
            }
        });
    }

    /** Setelan tersimpan, atau setelan kosong (= semua bawaan) kalau tabel belum ada / gagal dibaca — invoice tetap terkirim. */
    public static function current(): self
    {
        return rescue(
            fn () => Cache::rememberForever(self::CACHE_KEY, fn () => self::firstOrNew(['id' => 1])),
            fn () => new self,
            false,
        );
    }

    /**
     * Nilai siap pakai untuk template PDF: isian admin, selain itu bawaan. Alamat & kontak bawaan diambil dari Konten
     * Website (sumber yang sama dengan struk & halaman depan).
     *
     * @return array<string, string>
     */
    public function resolved(): array
    {
        $pick = fn (string $key, string $default = '') => trim((string) $this->{$key}) !== '' ? trim((string) $this->{$key}) : $default;
        $accent = array_key_exists((string) $this->accent, self::ACCENTS) ? (string) $this->accent : self::DEFAULTS['accent'];

        return [
            'business_name' => $pick('business_name', self::DEFAULTS['business_name']),
            'address' => $pick('address', CompanyProfileSetting::receiptAddress()),
            'phone' => $pick('phone', self::defaultPhone()),
            'email' => $pick('email'),
            'website' => $pick('website', self::defaultWebsite()),
            'tax_id' => $pick('tax_id'),
            'document_title' => $pick('document_title', self::DEFAULTS['document_title']),
            'status_label' => $pick('status_label', self::DEFAULTS['status_label']),
            'intro_text' => $pick('intro_text'),
            'notes_text' => $pick('notes_text'),
            'footer_text' => $pick('footer_text', self::DEFAULTS['footer_text']),
            'accent' => $accent,
            'accent_hex' => self::ACCENTS[$accent]['hex'],
        ];
    }

    /**
     * Path file logo di disk: unggahan admin → file logo invoice lama (OrderInvoiceMailer::LOGO_FILE, kalau pernah
     * dipasang manual) → logo terakota Club 61. Null kalau tidak ada satu pun.
     */
    public function logoFile(): ?string
    {
        if ($this->logo_path && Storage::disk('public')->exists($this->logo_path)) {
            return Storage::disk('public')->path($this->logo_path);
        }

        if ($legacy = \App\Services\Mail\OrderInvoiceMailer::logoPath()) {
            return $legacy;
        }

        $default = public_path(self::DEFAULT_LOGO);

        return is_file($default) ? $default : null;
    }

    /** Logo sebagai data URI (dompdf & pratinjau tidak mengambil file lewat URL). */
    public function logoDataUri(): ?string
    {
        $file = $this->logoFile();
        if (! $file) {
            return null;
        }

        $mime = @mime_content_type($file) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($file));
    }

    private static function defaultPhone(): string
    {
        $digits = rescue(fn () => CompanyProfileSetting::whatsappDigits(), null, false);

        return $digits ? '+'.$digits : '';
    }

    private static function defaultWebsite(): string
    {
        return (string) rescue(fn () => trim((string) CompanyProfileSetting::current()->portal_domain_text), '', false);
    }
}
