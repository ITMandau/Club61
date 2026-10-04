<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Toggle bahasa ID/EN di halaman depan (welcome.blade.php) disimpan di session, bukan di
 * config app.locale — supaya default situs tetap Bahasa Indonesia terlepas dari APP_LOCALE
 * di .env, dan pilihan bahasa staf/pengunjung tidak saling menimpa antar request lain.
 */
class SetLocale
{
    public const ALLOWED_LOCALES = ['id', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->hasSession() ? $request->session()->get('site_locale') : null;

        app()->setLocale(in_array($locale, self::ALLOWED_LOCALES, true) ? $locale : 'id');

        return $next($request);
    }
}
