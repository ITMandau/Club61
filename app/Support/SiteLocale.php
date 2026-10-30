<?php

namespace App\Support;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;

/**
 * Bahasa pesan dari server (respons API / JSON) yang sampai ke customer — lang/{id,en}/messages.php.
 *
 * Pesan ini dulu ditulis langsung dalam Bahasa Indonesia, dan sebagian service-nya juga dipakai panel admin, kasir
 * (Livewire), antrean & scheduler. Supaya semua itu tetap berbahasa Indonesia apa pun APP_LOCALE-nya, pesan memakai
 * Bahasa Inggris HANYA kalau request ini membawa pilihan bahasa customer 'en' yang eksplisit (dicatat SetLocale untuk
 * grup web dan SetApiLocale untuk grup api). Tanpa pilihan (aplikasi mobile / integrasi bearer token, panel admin,
 * console) → Bahasa Indonesia, sama persis dengan teks sebelumnya.
 */
final class SiteLocale
{
    /** Atribut request tempat middleware mencatat pilihan bahasa customer. */
    public const ATTRIBUTE = 'site_locale';

    /** Pilihan bahasa eksplisit customer: session 'site_locale', lalu cookie 'site_locale'. Null = belum memilih. */
    public static function explicitChoice(Request $request): ?string
    {
        $locale = ($request->hasSession() ? $request->session()->get('site_locale') : null) ?? $request->cookie(SetLocale::COOKIE);

        return in_array($locale, SetLocale::ALLOWED_LOCALES, true) ? $locale : null;
    }

    public static function remember(Request $request, string $locale): void
    {
        $request->attributes->set(self::ATTRIBUTE, $locale);
    }

    /** 'en' hanya bila customer memilih Bahasa Inggris; selain itu 'id'. Request Livewire (admin / kasir) selalu 'id'. */
    public static function forMessages(): string
    {
        $request = app()->bound('request') ? app('request') : null;

        if (! $request instanceof Request || $request->hasHeader('X-Livewire')) {
            return 'id';
        }

        return $request->attributes->get(self::ATTRIBUTE) === 'en' ? 'en' : 'id';
    }

    public static function isEnglish(): bool
    {
        return self::forMessages() === 'en';
    }

    /** Teks lang/{locale}/messages.php, mis. SiteLocale::text('checkout.cash_not_allowed'). */
    public static function text(string $key, array $replace = []): string
    {
        return __('messages.'.$key, $replace, self::forMessages());
    }

    /** Sama dengan text(), tapi null kalau kuncinya tidak ada (untuk label dinamis seperti kategori promo). */
    public static function textOrNull(string $key, array $replace = []): ?string
    {
        $locale = self::forMessages();

        return Lang::has('messages.'.$key, $locale, false) ? __('messages.'.$key, $replace, $locale) : null;
    }
}
