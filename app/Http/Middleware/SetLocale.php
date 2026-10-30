<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bahasa situs customer (ID / EN). Pilihan disimpan di session + cookie 1 tahun (route "lang.switch") — bukan di config
 * app.locale, supaya default situs tetap Bahasa Indonesia terlepas dari APP_LOCALE di .env, dan tanpa kolom database.
 * Carbon ikut bahasa yang dipilih (nama hari / bulan pada translatedFormat).
 * Hanya terpasang di grup "web"; panel admin Filament memakai middleware-nya sendiri sehingga tidak terpengaruh.
 */
class SetLocale
{
    public const ALLOWED_LOCALES = ['id', 'en'];

    public const COOKIE = 'site_locale';

    public const COOKIE_MINUTES = 60 * 24 * 365;

    public function handle(Request $request, Closure $next): Response
    {
        $locale = ($request->hasSession() ? $request->session()->get('site_locale') : null) ?? $request->cookie(self::COOKIE);
        $locale = in_array($locale, self::ALLOWED_LOCALES, true) && ! $this->isStaffSurface($request) ? $locale : 'id';

        app()->setLocale($locale);
        Carbon::setLocale($locale);
        // Dicatat untuk pesan server (App\Support\SiteLocale) — tidak mengubah perilaku halaman web.
        \App\Support\SiteLocale::remember($request, $locale);

        return $next($request);
    }

    /**
     * Panel admin, kasir & dapur selalu Bahasa Indonesia walau browser / tablet itu pernah memilih EN di situs customer.
     * Update Livewire (/livewire/update) memakai grup "web" juga, dan Livewire hanya dipakai di admin & POS.
     */
    private function isStaffSurface(Request $request): bool
    {
        return $request->hasHeader('X-Livewire') || $request->is('pos', 'pos/*', 'kitchen', 'kitchen/*');
    }
}
