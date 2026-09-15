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
     * Durasi kuncian slot (10 Menit = 600 Detik).
     */
    public const HOLD_DURATION_SECONDS = 600;

    /**
     * Durasi masa hidup Idempotency Key (24 Jam = 86.400 Detik).
     */
    public const IDEMPOTENCY_TTL_SECONDS = 86400;
}
