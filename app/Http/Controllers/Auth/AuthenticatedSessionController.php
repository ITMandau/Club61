<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
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

        $request->session()->regenerate();

        $user = $request->user();

        // 1. Cek rute home_route yang disetel pada peran pengguna secara eksplisit
        $primaryRole = $user->roles()->first();
        if ($primaryRole && ! empty($primaryRole->home_route)) {
            $request->session()->forget('url.intended');
            return redirect($primaryRole->home_route);
        }

        // 2. Fallback cerdas berbasis role default
        if ($user->hasRole('cashier') && $user->roles->count() === 1) {
            return redirect()->intended('/pos');
        }
        if ($user->hasRole('kitchen') && $user->roles->count() === 1) {
            return redirect()->intended('/kitchen');
        }
        if ($user->canAccessPanel(\Filament\Facades\Filament::getPanel('admin'))) {
            $request->session()->forget('url.intended');
            return redirect('/admin');
        }

        return redirect()->intended(route('dashboard', absolute: false));
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