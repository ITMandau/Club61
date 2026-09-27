<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Cabut semua Sanctum token API (mis. sesi mobile app) begitu password diganti,
        // supaya token yang mungkin sudah bocor tidak tetap valid selamanya (Sanctum token
        // di app ini tidak auto-expire, lihat config/sanctum.php).
        $request->user()->tokens()->delete();

        return back()->with('status', 'password-updated');
    }
}
