<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Batas tanggal main yang boleh dibooking (config/booking_window.php → BOOKING_OPEN_UNTIL, BOOKING_CLOSED_DATES,
 * BOOKING_CLOSED_REASON). Dicek di server pada setiap jalur yang membuat / memindah booking; tampilan jadwal customer &
 * POS menampilkan slotnya sebagai Tutup beserta pesannya.
 */
final class BookingWindow
{
    /** Tanggal main terakhir yang boleh dibooking (Y-m-d), atau null bila tanpa batas / nilainya tidak valid. */
    public static function openUntil(): ?string
    {
        return self::validDate(trim((string) config('booking_window.open_until', '')));
    }

    /** @return list<string> tanggal tutup sehari penuh (Y-m-d) */
    public static function closedDates(): array
    {
        $dates = array_map(fn ($d) => self::validDate(trim($d)), explode(',', (string) config('booking_window.closed_dates', '')));

        return array_values(array_unique(array_filter($dates)));
    }

    public static function closedReason(): ?string
    {
        $reason = trim((string) config('booking_window.closed_reason', ''));

        return $reason === '' ? null : mb_substr($reason, 0, 60);
    }

    public static function isClosed(CarbonInterface|string $date): bool
    {
        return in_array(self::dateString($date), self::closedDates(), true);
    }

    /**
     * Alasan tanggal main ini tidak bisa dibooking (pesan untuk customer / staf), atau null bila boleh.
     * $closedOnly = Jadwal Tetap (dibayar kuota jam, bukan harga lapangan): hanya tanggal tutup yang berlaku.
     */
    public static function blockedReason(CarbonInterface|string $date, bool $closedOnly = false): ?string
    {
        $date = self::dateString($date);

        if (in_array($date, self::closedDates(), true)) {
            $reason = self::closedReason();

            return SiteLocale::text('booking.date_closed', ['date' => self::label($date), 'reason' => $reason ? " ({$reason})" : '']);
        }

        $until = self::openUntil();
        if (! $closedOnly && $until !== null && $date > $until) {
            return SiteLocale::text('booking.date_not_open', ['date' => self::label($until)]);
        }

        return null;
    }

    public static function assertBookable(CarbonInterface|string $date, bool $closedOnly = false): void
    {
        if ($reason = self::blockedReason($date, $closedOnly)) {
            throw new HttpException(422, $reason);
        }
    }

    /** Mis. "Kamis, 26 November 2026" (bahasa mengikuti pesan customer). */
    public static function label(string $date): string
    {
        return Carbon::parse($date)->locale(SiteLocale::forMessages())->translatedFormat('l, j F Y');
    }

    private static function dateString(CarbonInterface|string $date): string
    {
        return $date instanceof CarbonInterface ? $date->toDateString() : substr(trim($date), 0, 10);
    }

    private static function validDate(string $value): ?string
    {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $value : null;
    }
}
