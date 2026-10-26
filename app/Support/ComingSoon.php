<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Mode "Coming Soon" sebelum grand opening (config/coming_soon.php → COMING_SOON_UNTIL).
 * Hanya pintu customer yang ditutup: staf tetap login lewat tombol "Login Staf" di halaman /login.
 */
class ComingSoon
{
    public const MEMBER_MESSAGE = 'Akun member belum bisa masuk. Portal dibuka :date.';

    /** Waktu grand opening, atau null bila mode mati / nilainya tidak valid. */
    public static function until(): ?CarbonImmutable
    {
        $raw = trim((string) config('coming_soon.until', ''));

        if ($raw === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($raw, config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }

    public static function active(): bool
    {
        $until = self::until();

        return $until !== null && now()->lt($until);
    }

    /** Detik tersisa menurut jam server (dipakai hitung mundur supaya tidak bergantung jam perangkat pengunjung). */
    public static function secondsLeft(): int
    {
        $until = self::until();

        return $until === null ? 0 : max(0, (int) now()->diffInSeconds($until, false));
    }

    /** Mis. "Kamis, 26 November 2026". */
    public static function dateLabel(): string
    {
        return self::until()?->locale('id')->translatedFormat('l, j F Y') ?? '';
    }

    public static function memberMessage(): string
    {
        return str_replace(':date', self::dateLabel(), self::MEMBER_MESSAGE);
    }
}
