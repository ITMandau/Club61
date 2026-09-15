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
        $parsedDate = Carbon::parse($date, $timezone);
        $dateStr = $parsedDate->format('Y-m-d');
        $isWeekend = $parsedDate->isWeekend();

        $courts = PadelCourt::where('is_active', true)->orderBy('name')->get();

        // Ambil semua booking aktif pada tanggal tersebut
        $activeBookings = PadelBooking::where('booking_date', $dateStr)
            ->whereIn('status', ['LOCKED', 'PAID', 'CHECKED_IN'])
            ->get();

        // Bulk prefetch Distributed Cache Locks untuk seluruh lapangan & jam dalam 1 query
        $allMatrixKeys = [];
        foreach ($courts as $c) {
            for ($h = 6; $h < 23; $h++) {
                $allMatrixKeys[] = "padel_lock:{$c->id}:{$dateStr}:" . sprintf('%02d00', $h);
            }
        }
        $bulkMatrixLocks = Cache::many($allMatrixKeys);

        $resultCourts = [];

        foreach ($courts as $court) {
            $slots = [];

            // Jam operasional: 06:00 sampai 23:00 (interval 1 jam)
            for ($hour = 6; $hour < 23; $hour++) {
                $startHourStr = sprintf('%02d:00', $hour);
                $endHourStr = sprintf('%02d:00', $hour + 1);

                $slotStart = Carbon::parse("{$dateStr} {$startHourStr}", $timezone);
                $slotEnd = Carbon::parse("{$dateStr} {$endHourStr}", $timezone);

                // Cek apakah slot ini tabrakan dengan booking aktif menggunakan rumus batas terbuka ketat (< dan >)
                $collidingBooking = $activeBookings->first(function ($booking) use ($court, $slotStart, $slotEnd) {
                    if ($booking->court_id !== $court->id) {
                        return false;
                    }
                    return $booking->start_time < $slotEnd && $booking->end_time > $slotStart;
                });

                // Cek juga Distributed Cache Lock (Tier 1) via bulk prefetch
                $cacheLockKey = "padel_lock:{$court->id}:{$dateStr}:" . $slotStart->format('Hi');
                $isCacheLocked = ! empty($bulkMatrixLocks[$cacheLockKey]);

                $status = 'AVAILABLE';
                if ($collidingBooking) {
                    $status = $collidingBooking->status === 'LOCKED' ? 'LOCKED' : 'BOOKED';
                } elseif ($isCacheLocked) {
                    $status = 'LOCKED';
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
                'slots' => $slots,
            ];
        }

        return [
            'date' => $dateStr,
            'timezone' => $timezone,
            'courts' => $resultCourts,
        ];
    }

    /**
     * Mengambil katalog peralatan dan add-on sewa.
     */
    public function getEquipments(): Collection
    {
        return CourtEquipment::orderBy('type')->get();
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

                foreach ($slots as $slot) {
                    $court = PadelCourt::where('id', $slot['court_id'])->where('is_active', true)->first();
                    if (! $court) {
                        throw new HttpException(404, "Lapangan ID {$slot['court_id']} tidak ditemukan.");
                    }

                    $startDt = Carbon::parse("{$bookingDate} {$slot['start_time']}");
                    $endDt = Carbon::parse("{$bookingDate} {$slot['end_time']}");

                    // RUMUS OVERLAP MATEMATIS KETAT: (< dan >)
                    $hasConflict = PadelBooking::where('court_id', $court->id)
                        ->whereDate('booking_date', $bookingDate)
                        ->whereIn('status', ['LOCKED', 'PAID', 'CHECKED_IN'])
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
     * Melepaskan kunci slot sukarela saat user menghapus item dari keranjang.
     */
    public function releaseSlots(array $bookingIds, User $user): int
    {
        $bookings = PadelBooking::whereIn('id', $bookingIds)
            ->where('user_id', $user->id)
            ->where('status', 'LOCKED')
            ->get();

        $count = 0;
        foreach ($bookings as $booking) {
            // Rilis cache lock
            $lockKey = "padel_lock:{$booking->court_id}:{$booking->booking_date->format('Y-m-d')}:" . $booking->start_time->format('Hi');
            Cache::forget($lockKey);

            $booking->update(['status' => 'CANCELLED']);
            $count++;
        }

        return $count;
    }

    /**
     * Garbage Collection: Merilis semua slot LOCKED yang ditinggal > 10 menit.
     */
    public function releaseExpiredLocks(): int
    {
        $expiredThreshold = now()->subSeconds(self::HOLD_DURATION_SECONDS);

        // Hanya rilis slot LOCKED dari keranjang checkout awal yang belum pernah dibayar (reschedule_count == 0 dan tanpa payment SUCCESS)
        $expiredBookings = PadelBooking::where('status', 'LOCKED')
            ->where('created_at', '<', $expiredThreshold)
            ->where('reschedule_count', 0)
            ->where(function ($query) {
                $query->whereNull('order_id')
                    ->orWhereDoesntHave('order.payments', function ($q) {
                        $q->where('status', 'SUCCESS');
                    });
            })
            ->get();

        $count = 0;
        foreach ($expiredBookings as $b) {
            $currLock = $b->start_time->copy();
            $endLock = $b->end_time->copy();
            while ($currLock->lt($endLock)) {
                $lockKey = "padel_lock:{$b->court_id}:{$b->booking_date->format('Y-m-d')}:" . $currLock->format('Hi');
                Cache::forget($lockKey);
                $currLock->addHour();
            }

            $b->update(['status' => 'EXPIRED']);
            $count++;
        }

        return $count;
    }
}
