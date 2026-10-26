<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Permission\HomeRouteResolver;
use App\Support\ComingSoon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        // Sebelum grand opening: halaman hitung mundur; form login biasa ada di balik tombol "Login Staf".
        if (ComingSoon::active()) {
            return view('auth.coming-soon');
        }

        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     * Smart 1-Door Redirection:
     * - SUPER_ADMIN / ADMIN -> /admin (Filament Dashboard)
     * - CASHIER             -> /pos (POS Kasir Frontdesk)
     * - KITCHEN             -> /kitchen (Kitchen Display System)
     * - CUSTOMER / Default  -> /dashboard (Member Dashboard)
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // Mode Coming Soon: hanya staf (tujuan login bukan Dashboard member) yang boleh mendapat sesi.
        if (ComingSoon::active() && HomeRouteResolver::resolve($request->user()) === HomeRouteResolver::CUSTOMER) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => ComingSoon::memberMessage(),
            ]);
        }

        $request->session()->regenerate();

        $user = $request->user();

        // 1. HOME ROUTE terdaftar yang dipilih di role → langsung ke sana.
        // 2. Kosong / tidak terdaftar → otomatis dari permission (Dashboard → POS → KDS → halaman admin
        //    pertama yang dicentang). Lihat HomeRouteResolver.
        $configured = HomeRouteResolver::configured($user);
        $destination = HomeRouteResolver::resolve($user);

        // Customer: kembali ke halaman portal yang tadi dibuka (mis. link klaim promo /promo/KODE). Dulu role customer
        // punya home route /dashboard sehingga tujuan ini dibuang dan customer harus membuka link-nya lagi.
        if ($destination === HomeRouteResolver::CUSTOMER) {
            return redirect(self::customerIntended($request) ?? route('dashboard', absolute: false));
        }

        if ($configured !== null || str_starts_with($destination, HomeRouteResolver::ADMIN)) {
            $request->session()->forget('url.intended');

            return redirect($destination);
        }

        return redirect()->intended($destination);
    }

    /**
     * Tujuan setelah login / daftar untuk customer: url.intended yang dipotong jadi path relatif (tidak bisa diarahkan ke
     * domain lain), dan bukan halaman staf / API. Null = tidak ada tujuan yang aman → Dashboard.
     */
    public static function customerIntended(Request $request): ?string
    {
        $intended = (string) $request->session()->pull('url.intended', '');
        $path = '/'.ltrim((string) parse_url($intended, PHP_URL_PATH), '/');
        $query = parse_url($intended, PHP_URL_QUERY);

        if ($intended === '' || $path === '/' || preg_match('#^/(admin|pos|kitchen|api|login|register|logout|livewire)(/|$)#', $path)) {
            return null;
        }

        return $path.($query ? '?'.$query : '');
    }

    /**
     * Destroy an authenticated session.
     * Redirects back to login portal (/login) so user can immediately sign in.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}