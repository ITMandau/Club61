<?php

namespace App\Http\Middleware;

use App\Support\SiteLocale;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bahasa respons grup "api". Situs customer memanggil API dengan session / cookie yang sama (Sanctum stateful), jadi
 * pilihan bahasa customer (session / cookie 'site_locale') ikut dipakai di sini.
 *
 * Berbeda dengan SetLocale (grup web, default 'id'): kalau request TIDAK membawa pilihan bahasa — aplikasi mobile /
 * integrasi dengan bearer token — locale dibiarkan apa adanya (config app.locale), supaya pesan validasi bawaan dan
 * teks lain untuk klien tersebut tidak berubah diam-diam. Header Accept-Language sengaja TIDAK dipakai: browser
 * mengirimnya otomatis (mis. 'en-US' di HP berbahasa Inggris) padahal situs tetap tampil Bahasa Indonesia.
 */
class SetApiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($locale = SiteLocale::explicitChoice($request)) {
            app()->setLocale($locale);
            Carbon::setLocale($locale);
            SiteLocale::remember($request, $locale);
        }

        return $next($request);
    }
}
