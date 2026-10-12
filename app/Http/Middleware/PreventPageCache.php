<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman form login / daftar / lupa sandi tidak boleh disimpan browser. Tanpa ini, tombol Back setelah login atau
 * tab HP yang lama terbuka menampilkan form lama dengan token CSRF basi → kirim form = 419 "Page Expired".
 */
class PreventPageCache
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
