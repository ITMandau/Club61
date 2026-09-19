<?php

namespace App\Services\Padel\Concerns;

use App\Exceptions\SlotConflictException;
use App\Models\Padel\CourtEquipment;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait ManagesScheduleAndSlots
{
    /**
     * Mengambil matriks ketersediaan seluruh lapangan (06:00 - 23:00) secara timezone-aware.
     * Privasi terproteksi penuh (identitas pemesan di-masking).
     */
    public function getScheduleMatrix(string $date, string $timezone = 'Asia/Jakarta'): array
    {
        // Sinkronkan booking kedaluwarsa & rilis lock yang hangus secara otomatis
        $this->syncExpiredAndCompletedBookings();

        $parsedDate = Carbon::parse($date, $timezone);
        $dateStr = $parsedDate->format('Y-m-d');
        $isWeekend = $parsedDate->isWeekend();

        $courts = PadelCourt::where('is_active', true)->orderBy('name')->get();

        $holdThreshold = now()->subSeconds(self::HOLD_DURATION_SECONDS);
        $paymentThreshold = now()->subMinutes(15);

        // Ambil semua booking aktif pada tanggal tersebut (hanya yang benar-benar aktif dan belum hangus)
        $activeBookings = PadelBooking::whereDate('booking_date', $dateStr)
            ->where(function ($query) use ($holdThreshold, $paymentThreshold) {
                $query->whereIn('status', ['PAID', 'CHECKED_IN'])
                    ->orWhere(function ($q) use ($holdThreshold) {
                        $q->where('status', 'LOCKED')
                            ->where('created_at', '>=', $holdThreshold);
                    })
                    ->orWhere(function ($q) use ($paymentThreshold) {
                        $q->whereIn('status', ['PENDING_PAYMENT', 'PENDING'])
                            ->where('created_at', '>=', $paymentThreshold);
                    });
            })
            ->get();

        $minOpenHour = 6;
        $maxCloseHour = 23;

        if ($courts->isNotEmpty()) {
            $minOpenHour = $courts->min(function ($c) {
                return (int) substr($c->open_time ?: '06:00', 0, 2);
            }) ?? 6;

            $maxCloseHour = $courts->max(function ($c) {
                $val = $c->close_time ?: '23:00';
                return ($val === '00:00' || $val === '24:00') ? 24 : (int) substr($val, 0, 2);
            }) ?? 23;

            $minOpenHour = max(0, min($minOpenHour, 23));
            $maxCloseHour = max($minOpenHour + 1, min($maxCloseHour, 24));
        }

        // Bulk prefetch Distributed Cache Locks untuk seluruh lapangan & jam dalam 1 query
        $allMatrixKeys = [];
        foreach ($courts as $c) {
            for ($h = $minOpenHour; $h < $maxCloseHour; $h++) {
                $allMatrixKeys[] = "padel_lock:{$c->id}:{$dateStr}:" . sprintf('%02d00', $h);
            }
        }
        $bulkMatrixLocks = Cache::many($allMatrixKeys);

        $resultCourts = [];

        foreach ($courts as $court) {
            $slots = [];
            $courtOpen = (int) substr($court->open_time ?: '06:00', 0, 2);
            $courtCloseVal = $court->close_time ?: '23:00';
            $courtClose = ($courtCloseVal === '00:00' || $courtCloseVal === '24:00') ? 24 : (int) substr($courtCloseVal, 0, 2);

            // Jam operasional dinamis: minOpenHour sampai maxCloseHour
            for ($hour = $minOpenHour; $hour < $maxCloseHour; $hour++) {
                $startHourStr = sprintf('%02d:00', $hour);
                $endHourStr = sprintf('%02d:00', $hour + 1);

                $slotStart = Carbon::parse("{$dateStr} {$startHourStr}", $timezone);
                $slotEnd = Carbon::parse("{$dateStr} {$endHourStr}", $timezone);

                $isOpenForCourt = ($hour >= $courtOpen && $hour < $courtClose);

                // Cek apakah slot ini tabrakan dengan booking aktif menggunakan rumus batas terbuka ketat (< dan >)
                $collidingBooking = $isOpenForCourt ? $activeBookings->first(function ($booking) use ($court, $slotStart, $slotEnd) {
                    if ($booking->court_id !== $court->id) {
                        return false;
                    }
                    $bStart = $booking->start_time->format('Y-m-d H:i:s');
                    $bEnd = $booking->end_time->format('Y-m-d H:i:s');
                    $sStart = $slotStart->format('Y-m-d H:i:s');
                    $sEnd = $slotEnd->format('Y-m-d H:i:s');

                    return $bStart < $sEnd && $bEnd > $sStart;
                }) : null;

                // Cek juga Distributed Cache Lock (Tier 1) via bulk prefetch
                $cacheLockKey = "padel_lock:{$court->id}:{$dateStr}:" . $slotStart->format('Hi');
                $isCacheLocked = $isOpenForCourt && ! empty($bulkMatrixLocks[$cacheLockKey]);

                if (! $isOpenForCourt) {
                    $status = 'CLOSED';
                } elseif ($collidingBooking) {
                    $status = in_array($collidingBooking->status, ['LOCKED', 'PENDING_PAYMENT', 'PENDING']) ? 'LOCKED' : 'BOOKED';
                } elseif ($isCacheLocked) {
                    $status = 'LOCKED';
                } else {
                    $status = 'AVAILABLE';
                }

                $isPrime = $isWeekend || $hour >= 17; // Prime time 17:00 ke atas atau akhir pekan
                $price = $isPrime ? (float)$court->hourly_rate_prime : (float)$court->hourly_rate_regular;
                $originalPrice = $isPrime ? (float)$court->hourly_rate_prime * 1.25 : (float)$court->hourly_rate_regular * 1.5;

                $slots[] = [
                    'time' => "{$startHourStr} - {$endHourStr}",
                    'start_time' => $slotStart->toISOString(),
                    'end_time' => $slotEnd->toISOString(),
                    'local_start' => $startHourStr,
                    'local_end' => $endHourStr,
                    'status' => $status,
                    'is_prime_time' => $isPrime,
                    'original_price' => round($originalPrice),
                    'price' => round($price),
                ];
            }

            $resultCourts[] = [
                'court_id' => $court->id,
                'court_name' => $court->name,
                'type' => $court->type,
                'description' => $court->description ?: ($court->type === 'INDOOR' ? 'Indoor • Central AC' : 'Outdoor • Open Air Court'),
                'open_time' => $court->open_time ?: '06:00',
                'close_time' => $court->close_time ?: '23:00',
                'slots' => $slots,
            ];
        }

        return [
            'date' => $dateStr,
            'timezone' => $timezone,
            'open_hour' => sprintf('%02d:00', $minOpenHour),
            'close_hour' => sprintf('%02d:00', $maxCloseHour),
            'courts' => $resultCourts,
        ];
    }

    /**
     * Mengambil katalog peralatan dan add-on sewa.
     */
    public function getEquipments(): Collection
    {
        return CourtEquipment::where('is_active', true)->orderBy('type')->get();
    }

    /**
     * Mengunci multi-slot jam lapangan secara ATOMIK (All-or-Nothing).
     * Mencegah Race Condition / Concurrency Collision dengan Two-Tier Defense:
     * 1. Distributed Cache Multi-Lock (Sorted Keys anti-deadlock)
     * 2. ACID DB Pessimistic Lock (SELECT ... FOR UPDATE)
     *
     * @throws SlotConflictException
     * @throws HttpException
     */
    public function holdBatchSlots(array $slots, string $bookingDate, User $user, ?string $coachId = null): array
    {
        if (empty($slots)) {
            throw new HttpException(422, 'Daftar slot tidak boleh kosong.');
        }

        $bookingDateParsed = Carbon::parse($bookingDate)->startOfDay();
        if ($bookingDateParsed->isPast() && ! $bookingDateParsed->isToday()) {
            throw new HttpException(422, 'Tanggal booking tidak boleh di masa lampau.');
        }

        // 1. SORTING ARRAY SECARA KRONOLOGIS (Anti-Deadlock pada Concurrent Transactions)
        usort($slots, function ($a, $b) {
            $cmp = strcmp($a['court_id'], $b['court_id']);
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp($a['start_time'], $b['start_time']);
        });

        // Validasi waktu masing-masing slot
        foreach ($slots as $slot) {
            $start = Carbon::parse("{$bookingDate} {$slot['start_time']}");
            $end = Carbon::parse("{$bookingDate} {$slot['end_time']}");
            if ($end->lessThanOrEqualTo($start)) {
                throw new HttpException(422, "Waktu selesai ({$slot['end_time']}) harus lebih besar dari waktu mulai ({$slot['start_time']}).");
            }
        }

        // Bersihkan lock kedaluwarsa secara proaktif sebelum memegang slot baru
        $this->releaseExpiredLocks();

        // TIER 1: ACQUIRE DISTRIBUTED CACHE LOCKS SECARA BERURUTAN (Tiap Interval 1 Jam)
        $acquiredLocks = [];
        try {
            foreach ($slots as $slot) {
                $startDt = Carbon::parse("{$bookingDate} {$slot['start_time']}");
                $endDt = Carbon::parse("{$bookingDate} {$slot['end_time']}");

                $currLock = $startDt->copy();
                while ($currLock->lt($endDt)) {
                    $lockKey = "padel_lock:{$slot['court_id']}:{$bookingDate}:" . $currLock->format('Hi');
                    $lock = Cache::lock($lockKey, self::HOLD_DURATION_SECONDS);

                    if (! $lock->get()) {
                        throw new SlotConflictException(
                            "Slot lapangan pada jam {$currLock->format('H:i')} sedang di-hold pemain lain. Transaksi multi-slot dibatalkan penuh.",
                            $slot['court_id'],
                            "{$slot['start_time']} - {$slot['end_time']}"
                        );
                    }

                    $acquiredLocks[] = $lock;
                    $currLock->addHour();
                }
            }

            // TIER 2: PESSIMISTIC DB LOCK DALAM TRANSAKSI ACID
            return DB::transaction(function () use ($slots, $bookingDate, $user, $coachId) {
                $createdBookings = [];
                $totalCourtFee = 0;
                $batchId = 'BATCH-PAD-' . strtoupper(Str::random(8));

                $holdThreshold = now()->subSeconds(self::HOLD_DURATION_SECONDS);
                $paymentThreshold = now()->subMinutes(15);

                foreach ($slots as $slot) {
                    $court = PadelCourt::where('id', $slot['court_id'])->where('is_active', true)->first();
                    if (! $court) {
                        throw new HttpException(404, "Lapangan ID {$slot['court_id']} tidak ditemukan.");
                    }

                    $startDt = Carbon::parse("{$bookingDate} {$slot['start_time']}");
                    $endDt = Carbon::parse("{$bookingDate} {$slot['end_time']}");

                    // Validasi jam operasional lapangan (tolak booking di luar jam buka/tutup)
                    $courtOpen = (int) substr($court->open_time ?: '06:00', 0, 2);
                    $courtCloseVal = $court->close_time ?: '23:00';
                    $courtClose = ($courtCloseVal === '00:00' || $courtCloseVal === '24:00') ? 24 : (int) substr($courtCloseVal, 0, 2);

                    $slotStartH = (int) $startDt->format('H');
                    $slotEndH = ($endDt->format('H:i') === '00:00' && $endDt->isNextDay($startDt)) ? 24 : (int) $endDt->format('H');

                    if ($slotStartH < $courtOpen || $slotEndH > $courtClose) {
                        throw new HttpException(422, "Slot {$court->name} pada jam {$slot['start_time']} - {$slot['end_time']} berada di luar jam operasional ({$court->open_time} - {$court->close_time} WIB).");
                    }

                    // RUMUS OVERLAP MATEMATIS KETAT: (< dan >) dengan proteksi anti-stale locks
                    $hasConflict = PadelBooking::where('court_id', $court->id)
                        ->whereDate('booking_date', $bookingDate)
                        ->where(function ($q) use ($holdThreshold, $paymentThreshold) {
                            $q->whereIn('status', ['PAID', 'CHECKED_IN'])
                                ->orWhere(function ($sub) use ($holdThreshold) {
                                    $sub->where('status', 'LOCKED')
                                        ->where('created_at', '>=', $holdThreshold);
                                })
                                ->orWhere(function ($sub) use ($paymentThreshold) {
                                    $sub->whereIn('status', ['PENDING_PAYMENT', 'PENDING'])
                                        ->where('created_at', '>=', $paymentThreshold);
                                });
                        })
                        ->where('start_time', '<', $endDt->format('Y-m-d H:i:s'))
                        ->where('end_time', '>', $startDt->format('Y-m-d H:i:s'))
                        ->lockForUpdate() // Kunci baris database secara eksklusif
                        ->exists();

                    if ($hasConflict) {
                        throw new SlotConflictException(
                            "Slot {$court->name} pada jam {$slot['start_time']} - {$slot['end_time']} sudah terisi atau terkunci. Transaksi multi-slot di-rollback.",
                            $court->name,
                            "{$slot['start_time']} - {$slot['end_time']}"
                        );
                    }

                    // Hitung tarif akumulasi per jam (menjaga transisi reguler vs prime time)
                    $isWeekend = Carbon::parse($bookingDate)->isWeekend();
                    $courtFee = 0;
                    $currFee = $startDt->copy();
                    while ($currFee->lt($endDt)) {
                        $hour = (int)$currFee->format('H');
                        $isPrime = $isWeekend || $hour >= 17;
                        $rate = $isPrime ? (float)$court->hourly_rate_prime : (float)$court->hourly_rate_regular;
                        $courtFee += $rate;
                        $currFee->addHour();
                    }
                    $totalCourtFee += $courtFee;

                    $bookingCode = 'BK-PAD-' . strtoupper(Str::random(8));

                    $booking = PadelBooking::create([
                        'booking_code' => $bookingCode,
                        'user_id' => $user->id,
                        'court_id' => $court->id,
                        'coach_id' => $coachId,
                        'booking_date' => $bookingDate,
                        'start_time' => $startDt,
                        'end_time' => $endDt,
                        'court_fee' => $courtFee,
                        'coach_fee' => 0.00,
                        'equipment_fee' => 0.00,
                        'total_amount' => $courtFee,
                        'status' => 'LOCKED',
                        'qr_code_hash' => hash_hmac('sha256', $bookingCode . $user->id . $court->id . $startDt->toISOString(), config('app.key')),
                    ]);

                    $createdBookings[] = $booking;
                }

                $expiresAt = now()->addSeconds(self::HOLD_DURATION_SECONDS);

                return [
                    'batch_id' => $batchId,
                    'expires_at' => $expiresAt->toISOString(),
                    'hold_seconds_remaining' => self::HOLD_DURATION_SECONDS,
                    'bookings' => $createdBookings,
                    'subtotal' => $totalCourtFee,
                ];
            });
        } catch (\Throwable $e) {
            // Rollback seluruh Cache Locks jika transaksi gagal
            foreach ($acquiredLocks as $lock) {
                try {
                    $lock->release();
                } catch (\Throwable $releaseEx) {}
            }
            throw $e;
        }
    }

    /**
     * Melepaskan kunci slot sukarela saat user membatalkan dari keranjang atau membatalkan pesanan pending.
     */
    public function releaseSlots(array $bookingIds, User $user): int
    {
        $orderNumbersToCancel = [];

        $count = DB::transaction(function () use ($bookingIds, $user, &$orderNumbersToCancel) {
            $bookings = PadelBooking::whereIn('id', $bookingIds)
                ->where('user_id', $user->id)
                ->whereIn('status', ['LOCKED', 'PENDING_PAYMENT', 'PENDING'])
                ->lockForUpdate()
                ->get();

            $c = 0;
            foreach ($bookings as $booking) {
                // Guard: Jika booking sudah berstatus PAID karena webhook concurrent, jangan batalkan
                if ($booking->status === 'PAID') {
                    continue;
                }

                if ($booking->order_id) {
                    $order = \App\Models\Pos\Order::where('id', $booking->order_id)
                        ->orWhere('order_number', $booking->order_id)
                        ->lockForUpdate()
                        ->first();
                    if ($order && $order->payment_status === 'PAID') {
                        // Order sudah dibayar lunas, jangan dibatalkan
                        continue;
                    }
                    if ($order && $order->payment_status !== 'PAID') {
                        $order->update(['payment_status' => 'CANCELLED']);
                        $orderNumbersToCancel[] = $order->order_number;
                    }
                }

                $currLock = $booking->start_time->copy();
                $endLock = $booking->end_time->copy();
                while ($currLock->lt($endLock)) {
                    $lockKey = "padel_lock:{$booking->court_id}:{$booking->booking_date->format('Y-m-d')}:" . $currLock->format('Hi');
                    Cache::forget($lockKey);
                    try {
                        Cache::lock($lockKey)->forceRelease();
                    } catch (\Throwable $e) {}
                    $currLock->addHour();
                }

                $booking->update(['status' => 'CANCELLED']);
                $c++;
            }

            return $c;
        });

        // Panggil Midtrans Cancel API secara non-blocking di luar transaksi DB
        if (! empty($orderNumbersToCancel)) {
            $midtrans = app(\App\Services\Payment\MidtransService::class);
            foreach (array_unique($orderNumbersToCancel) as $orderNumber) {
                try {
                    $midtrans->cancelTransaction($orderNumber);
                } catch (\Throwable $e) {
                    // Best-effort
                }
            }
        }

        return $count;
    }

    /**
     * Garbage Collection: Merilis semua slot LOCKED yang ditinggal > 10 menit
     * atau PENDING_PAYMENT / PENDING yang tidak diselesaikan dalam 15 menit.
     */
    public function releaseExpiredLocks(): int
    {
        $holdThreshold = now()->subSeconds(self::HOLD_DURATION_SECONDS); // 10 menit
        $paymentThreshold = now()->subMinutes(15); // 15 menit

        $orderNumbersToCancel = [];

        // 1. Slot LOCKED tanpa checkout (> 10 menit)
        $expiredHolds = PadelBooking::where('status', 'LOCKED')
            ->where('created_at', '<', $holdThreshold)
            ->where('reschedule_count', 0)
            ->where(function ($query) {
                $query->whereNull('order_id')
                    ->orWhereDoesntHave('order.payments', function ($q) {
                        $q->where('status', 'SUCCESS');
                    });
            })
            ->get();

        // 2. Slot PENDING_PAYMENT / PENDING yang tidak selesai dibayar (> 15 menit)
        $expiredPendingPayments = PadelBooking::whereIn('status', ['PENDING_PAYMENT', 'PENDING'])
            ->where('created_at', '<', $paymentThreshold)
            ->where('reschedule_count', 0)
            ->where(function ($query) {
                $query->whereNull('order_id')
                    ->orWhereDoesntHave('order.payments', function ($q) {
                        $q->where('status', 'SUCCESS');
                    });
            })
            ->get();

        $expiredBookings = $expiredHolds->merge($expiredPendingPayments);

        $count = 0;
        foreach ($expiredBookings as $b) {
            $currLock = $b->start_time->copy();
            $endLock = $b->end_time->copy();
            while ($currLock->lt($endLock)) {
                $lockKey = "padel_lock:{$b->court_id}:{$b->booking_date->format('Y-m-d')}:" . $currLock->format('Hi');
                Cache::forget($lockKey);
                try {
                    Cache::lock($lockKey)->forceRelease();
                } catch (\Throwable $e) {}
                $currLock->addHour();
            }

            $b->update(['status' => 'EXPIRED']);
            $count++;

            if ($b->order_id) {
                $order = \App\Models\Pos\Order::find($b->order_id);
                if ($order && $order->payment_status !== 'PAID') {
                    $order->update(['payment_status' => 'CANCELLED']);
                    $orderNumbersToCancel[] = $order->order_number;
                }
            }
        }

        // Panggil Midtrans Cancel API di luar DB lock
        if (! empty($orderNumbersToCancel)) {
            $midtrans = app(\App\Services\Payment\MidtransService::class);
            foreach (array_unique($orderNumbersToCancel) as $orderNumber) {
                try {
                    $midtrans->cancelTransaction($orderNumber);
                } catch (\Throwable $e) {
                    // Best-effort
                }
            }
        }

        return $count;
    }
}
