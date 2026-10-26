<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ComingSoon;
use App\Support\PersonName;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View|RedirectResponse
    {
        // Mode Coming Soon: pendaftaran ditutup sampai grand opening.
        if (ComingSoon::active()) {
            return redirect()->route('login');
        }

        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        if (ComingSoon::active()) {
            return redirect()->route('login');
        }

        $request->validate([
            'name' => PersonName::rules(),
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => PersonName::clean($request->name),
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        // Daftar dari link promo / halaman portal lain → kembali ke sana (path relatif yang aman), selain itu Dashboard.
        return redirect(AuthenticatedSessionController::customerIntended($request) ?? route('dashboard', absolute: false));
    }
}
