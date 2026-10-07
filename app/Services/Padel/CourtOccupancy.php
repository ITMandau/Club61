<?php

namespace App\Services\Padel;

use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Services\Finance\LedgerReport;
use Carbon\Carbon;

/**
 * Okupansi lapangan = jam terpakai ÷ (jam buka lapangan aktif × jumlah hari). Satu rumus untuk Dashboard & Analytics
 * (dulu Analytics memakai angka tetap 4 lapangan × 18 jam, dan Dashboard menulis "100% Okupansi" mati).
 */
class CourtOccupancy
{
    /** Status booking yang memakai slot lapangan. */
    public const OCCUPYING_STATUSES = ['PAID', 'CHECKED_IN', 'COMPLETED', 'EXPIRED'];

    /**
     * @param  ?string  $from  Y-m-d (WIB), null = sejak booking pertama
     * @param  ?string  $until  Y-m-d (WIB), null = hari ini
     * @return array{rate: float, hours_booked: float, capacity_hours: float, courts: int}
     */
    public static function calculate(?string $from, ?string $until): array
    {
        $bookings = PadelBooking::query()->whereIn('status', self::OCCUPYING_STATUSES)
            ->when($from, fn ($q) => $q->whereDate('booking_date', '>=', $from))
            ->when($until, fn ($q) => $q->whereDate('booking_date', '<=', $until));

        $hoursBooked = 0.0;
        foreach ((clone $bookings)->get(['start_time', 'end_time']) as $b) {
            $hoursBooked += max(0, $b->start_time->diffInMinutes($b->end_time)) / 60;
        }

        $start = $from ?? (clone $bookings)->min('booking_date');
        $end = $until ?? Carbon::now(LedgerReport::TIMEZONE)->toDateString();
        $days = $start ? max(1, (int) Carbon::parse($start)->startOfDay()->diffInDays(Carbon::parse($end)->startOfDay()) + 1) : 1;

        $courts = PadelCourt::query()->where('is_active', true)->get(['open_time', 'close_time']);
        $capacity = $courts->sum(fn (PadelCourt $court) => self::openHours($court)) * $days;

        return [
            'rate' => $capacity > 0 ? round($hoursBooked / $capacity * 100, 1) : 0.0,
            'hours_booked' => round($hoursBooked, 1),
            'capacity_hours' => round($capacity, 1),
            'courts' => $courts->count(),
        ];
    }

    /** Jam buka per hari satu lapangan (tutup 00:00 / 24:00 = sampai tengah malam). */
    public static function openHours(PadelCourt $court): float
    {
        $open = Carbon::createFromTimeString($court->open_time ?: '06:00');
        $closesAtMidnight = in_array($court->close_time, [null, '', '00:00', '24:00'], true);
        $close = $closesAtMidnight ? $open->copy()->endOfDay()->addSecond() : Carbon::createFromTimeString($court->close_time);

        return max(0, $open->diffInMinutes($close)) / 60;
    }
}
