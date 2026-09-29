<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan dasar yang sebelumnya tidak pernah di-set sama sekali di aplikasi ini
 * (baik untuk panel admin Filament berbasis session/cookie maupun API). CSP sengaja tidak
 * disertakan di sini — butuh audit terpisah per halaman (banyak script/style inline di
 * Blade & Filament) supaya tidak tiba-tiba mematahkan tampilan; header di bawah ini aman
 * diterapkan langsung tanpa risiko itu.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        // Pratinjau dashboard PIC ditampilkan via iframe di halaman panel "Dashboard Sponsor"
        // (origin yang sama) — hanya route itu yang boleh di-frame, itupun cuma same-origin.
        $response->headers->set('X-Frame-Options', $request->routeIs('corporate.preview') ? 'SAMEORIGIN' : 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if ($request->secure() || app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
