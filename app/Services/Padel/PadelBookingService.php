<?php

namespace App\Services\Padel;

use App\Services\Padel\Concerns\ManagesCheckInAndTurnstile;
use App\Services\Padel\Concerns\ManagesCheckoutAndPayments;
use App\Services\Padel\Concerns\ManagesRescheduleAndCashier;
use App\Services\Padel\Concerns\ManagesScheduleAndSlots;
use App\Services\Padel\Concerns\ManagesTicketsAndRefunds;

class PadelBookingService
{
    use ManagesScheduleAndSlots;
    use ManagesCheckoutAndPayments;
    use ManagesCheckInAndTurnstile;
    use ManagesTicketsAndRefunds;
    use ManagesRescheduleAndCashier;

    /**
     * @deprecated Durasi tahan slot sekarang diatur admin — pakai BookingTimeService::holdSeconds().
     *             Dipertahankan hanya untuk kompatibilitas kode lama.
     */
    public const HOLD_DURATION_SECONDS = 600;

    /**
     * Durasi masa hidup Idempotency Key (24 Jam = 86.400 Detik).
     */
    public const IDEMPOTENCY_TTL_SECONDS = 86400;

    /** Modul 21: reschedule biasa paling lambat sekian jam sebelum jam main (keputusan PM). */
    public const RESCHEDULE_CUTOFF_HOURS = 2;

    /** Batas akhir reschedule booking ini; lewat dari ini jadwal tidak bisa dipindah lagi. */
    public static function rescheduleDeadline(\App\Models\Padel\PadelBooking $booking): \Carbon\Carbon
    {
        return $booking->start_time->copy()->subHours(self::RESCHEDULE_CUTOFF_HOURS);
    }
}
