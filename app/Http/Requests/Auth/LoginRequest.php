<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\Auth\LoginThrottle;
use App\Support\PhoneNumber;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $login = trim($this->input('email'));
        $password = $this->input('password');

        if (strcasecmp($login, 'admin') === 0) {
            $login = 'admin@club61.com';
        } elseif (! str_contains($login, '@')) {
            $normalizedPhone = PhoneNumber::normalize($login);

            $found = User::where(function ($query) use ($login, $normalizedPhone) {
                if ($normalizedPhone) {
                    $query->orWhere('phone', $normalizedPhone);
                }
                $query->orWhere('email', $login)
                    ->orWhere('email', $login.'@club61.com');
            })->first();

            if ($found) {
                $login = $found->email;
            }
        }

        // Batas percobaan dihitung dari identitas yang SUDAH di-resolve: dulu kuncinya teks mentah, jadi
        // "admin", email, nomor HP (0812…/+62812…) untuk akun yang sama masing-masing dapat jatah 5x.
        $this->resolvedLogin = Str::lower($login);
        // Akun sebenarnya (atau null kalau tidak terdaftar) untuk jeda bertahap per akun — lihat LoginThrottle.
        $this->targetUser = User::where('email', $login)->first();
        $this->ensureIsNotRateLimited();

        $credentials = [
            'email' => $login,
            'password' => $password,
        ];

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            $this->hitRateLimits();
            $delay = LoginThrottle::failed($this->targetUser, $this->loginIdentity());

            // Beri tahu sebelum terkunci, supaya user sempat beralih ke "Lupa kata sandi?".
            $remaining = RateLimiter::remaining($this->throttleKey(), self::MAX_ATTEMPTS_ACCOUNT_IP);
            $message = $delay > 0 ? self::waitMessage($delay) : trans('auth.failed');
            if ($delay === 0 && $remaining > 0 && $remaining <= 2) {
                $message .= ' '.trans_choice('auth.remaining', $remaining, ['count' => $remaining]);
            }

            throw ValidationException::withMessages([
                'email' => $message,
            ]);
        }

        // Akun nonaktif (staf yang sudah keluar, customer yang diblokir) tidak boleh mendapat sesi web.
        if (Auth::user()?->is_active === false) {
            Auth::guard('web')->logout();
            $this->hitRateLimits();

            throw ValidationException::withMessages([
                'email' => \App\Http\Middleware\EnsureUserIsActive::MESSAGE,
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        LoginThrottle::succeeded(Auth::user());
    }

    /** Identitas login setelah alias/nomor HP di-resolve ke email (null sebelum authenticate() jalan). */
    protected ?string $resolvedLogin = null;

    /** Akun yang dicoba (null = tidak terdaftar). */
    protected ?User $targetUser = null;

    /** Maks gagal per akun + IP (pengguna sah yang salah ketik). */
    public const MAX_ATTEMPTS_ACCOUNT_IP = 5;

    /**
     * Maks gagal per IP ke akun mana pun per menit (password spraying ke banyak akun). Penyerang yang berganti-ganti IP
     * ke satu akun ditahan jeda bertahap per akun (LoginThrottle), bukan kunci total.
     */
    public const MAX_ATTEMPTS_IP = 30;

    protected function hitRateLimits(): void
    {
        RateLimiter::hit($this->throttleKey());
        RateLimiter::hit($this->ipThrottleKey());
    }

    public static function waitMessage(int $seconds): string
    {
        return trans('auth.throttle', ['seconds' => $seconds, 'minutes' => (int) ceil($seconds / 60)]);
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        // Akun sedang ditunda (terlalu sering salah dari IP mana pun) → tolak tanpa memeriksa sandi.
        $wait = LoginThrottle::waitSeconds($this->targetUser, $this->loginIdentity());
        if ($wait > 0) {
            throw ValidationException::withMessages(['email' => self::waitMessage($wait)]);
        }

        $limits = [
            $this->throttleKey() => self::MAX_ATTEMPTS_ACCOUNT_IP,
            $this->ipThrottleKey() => self::MAX_ATTEMPTS_IP,
        ];

        $blockedKey = null;
        foreach ($limits as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $blockedKey = $key;
                break;
            }
        }

        if ($blockedKey === null) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($blockedKey);

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate($this->loginIdentity().'|'.$this->ip());
    }

    public function ipThrottleKey(): string
    {
        return 'login-ip:'.$this->ip();
    }

    protected function loginIdentity(): string
    {
        return $this->resolvedLogin ?? Str::lower(trim((string) $this->string('email')));
    }
}
