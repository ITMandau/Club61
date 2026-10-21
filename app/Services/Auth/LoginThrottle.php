<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Jeda login bertahap per akun (audit go-live G13), dipakai login web & API. Menggantikan kunci total "20x salah dari
 * IP mana pun → akun terkunci 15 menit".
 *
 * Setelah FREE_FAILURES kali salah, tiap kesalahan berikutnya membuat akun harus menunggu sebelum boleh mencoba lagi
 * (30 dtk, 1, 2, 4 … menit, maks 1 jam). Selama menunggu semua percobaan ditolak tanpa memeriksa sandi dan tidak
 * menambah hitungan. Sandi walk-in 6 digit tetap aman dari tebakan dari banyak IP (± 1 tebakan per jam setelah jeda
 * maksimal). Hitungan hilang setelah login berhasil, setelah sandi diganti / direset, atau 24 jam setelah salah pertama.
 *
 * Akun dikenali dari akun yang sebenarnya (email, 0812…, +62812… untuk akun yang sama = satu hitungan). Identitas
 * yang tidak terdaftar diperlakukan sama persis supaya tidak bisa dipakai menebak akun mana yang ada.
 */
class LoginThrottle
{
    public const FREE_FAILURES = 5;

    public const FIRST_DELAY_SECONDS = 30;

    public const MAX_DELAY_SECONDS = 3600;

    public const MEMORY_SECONDS = 86400;

    /** Detik yang masih harus ditunggu sebelum akun ini boleh mencoba login (0 = boleh). */
    public static function waitSeconds(?User $user, string $identifier): int
    {
        $until = (int) Cache::get(self::untilKey(self::account($user, $identifier)), 0);

        return max(0, $until - now()->getTimestamp());
    }

    /**
     * Catat satu login gagal (sandi salah). Kalau akun mulai / makin lama ditunda, dicatat di Log Aktivitas (IP ikut).
     *
     * @return int jeda yang berlaku sekarang (detik), 0 = belum ditunda
     */
    public static function failed(?User $user, string $identifier, bool $viaApi = false): int
    {
        $account = self::account($user, $identifier);
        $failuresKey = 'login-delay:failures:'.$account;
        Cache::add($failuresKey, 0, self::MEMORY_SECONDS);
        $failures = (int) Cache::increment($failuresKey);

        $delay = self::delayFor($failures);
        if ($delay === 0) {
            return 0;
        }

        Cache::put(self::untilKey($account), now()->getTimestamp() + $delay, $delay);

        ActivityLogger::record(
            module: 'AUTH',
            event: 'auth.login_delayed',
            description: ($viaApi ? 'Login API' : 'Login').' untuk "'.Str::limit(trim($identifier), 80).'" ditunda '
                .self::humanize($delay).' setelah '.$failures.' kali salah sandi',
            subject: $user,
            meta: [
                'identitas_dicoba' => Str::limit(trim($identifier), 80),
                'akun_ditemukan' => $user !== null,
                'gagal_berturut' => $failures,
                'jeda_detik' => $delay,
            ],
            severity: $delay >= self::MAX_DELAY_SECONDS ? ActivityLogger::CRITICAL : ActivityLogger::WARNING,
        );

        return $delay;
    }

    /** Login berhasil → hitungan & jeda akun ini dihapus. */
    public static function succeeded(User $user): void
    {
        self::clear($user);
    }

    /** Sandi diganti / direset (siapa pun yang mengganti) → akun bisa langsung login lagi tanpa menunggu. */
    public static function clear(User $user): void
    {
        $account = self::account($user, '');
        Cache::forget('login-delay:failures:'.$account);
        Cache::forget(self::untilKey($account));
    }

    public static function delayFor(int $failures): int
    {
        if ($failures <= self::FREE_FAILURES) {
            return 0;
        }
        $steps = min($failures - self::FREE_FAILURES - 1, 12);

        return (int) min(self::MAX_DELAY_SECONDS, self::FIRST_DELAY_SECONDS * (2 ** $steps));
    }

    public static function humanize(int $seconds): string
    {
        return $seconds < 60 ? $seconds.' detik' : (int) ceil($seconds / 60).' menit';
    }

    private static function account(?User $user, string $identifier): string
    {
        if ($user) {
            return 'u:'.$user->getKey();
        }
        $identifier = trim($identifier);
        $normalized = str_contains($identifier, '@') ? Str::lower($identifier) : (PhoneNumber::normalize($identifier) ?? Str::lower($identifier));

        return 'x:'.sha1($normalized);
    }

    private static function untilKey(string $account): string
    {
        return 'login-delay:until:'.$account;
    }
}
