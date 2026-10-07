<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Support\PhoneNumber;
use App\Support\PlaceholderEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public const GENERIC_STATUS = 'Jika akun tersebut terdaftar dan punya email, link atur ulang kata sandi sudah dikirim. Cek juga folder Spam / Promosi.';

    public const MAIL_FAILED = 'Email belum bisa dikirim saat ini. Coba lagi beberapa menit lagi atau hubungi frontdesk Club 61.';

    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * Bisa diisi email ATAU nomor HP (sama seperti halaman login) — nomor HP dicari ke email akunnya.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'max:150'],
        ], [
            'email.required' => 'Isi email atau nomor HP yang terdaftar.',
        ]);

        $email = $this->resolveEmail((string) $request->input('email'));

        // Sengaja SELALU tampilkan pesan generik yang sama, apapun hasilnya (terkirim / tidak terdaftar /
        // akun walk-in tanpa email) — kalau pesannya beda-beda, orang luar bisa menebak akun mana saja
        // yang terdaftar (account enumeration).
        if ($email === null || PlaceholderEmail::is($email)) {
            return back()->with('status', self::GENERIC_STATUS);
        }

        try {
            $status = Password::sendResetLink(['email' => $email]);
        } catch (\Throwable $e) {
            report($e);
            $this->forgetToken($email);
            ActivityLogger::record(
                module: 'AUTH',
                event: 'auth.password_reset_mail_failed',
                description: 'Email "Lupa Kata Sandi" gagal dikirim — cek pengaturan SMTP (MAIL_*) di server',
                meta: ['error' => Str::limit($e->getMessage(), 200)],
                severity: ActivityLogger::CRITICAL,
                asSystem: true,
            );

            return back()->withInput($request->only('email'))->withErrors(['email' => self::MAIL_FAILED]);
        }

        // Jeda 1 menit per akun (RESET_THROTTLED) juga dibalas pesan generik — dulu muncul "tunggu 1 menit" HANYA untuk
        // akun yang terdaftar, jadi mengirim nomor HP dua kali cukup untuk menebak nomor mana yang punya akun.
        return back()->with('status', self::GENERIC_STATUS);
    }

    private function resolveEmail(string $login): ?string
    {
        $login = trim($login);

        if (str_contains($login, '@')) {
            return filter_var($login, FILTER_VALIDATE_EMAIL) ? $login : null;
        }

        $phone = PhoneNumber::normalize($login);

        return $phone ? User::where('phone', $phone)->value('email') : null;
    }

    /** Token yang sudah dibuat tapi emailnya gagal terkirim dihapus — supaya bisa langsung coba lagi tanpa kena jeda 1 menit. */
    private function forgetToken(string $email): void
    {
        rescue(function () use ($email) {
            $broker = Password::broker();
            $user = $broker->getUser(['email' => $email]);

            if ($user) {
                $broker->deleteToken($user);
            }
        }, null, false);
    }
}
