<?php

namespace App\Services\Padel;

use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PadelBookingService
{
    /**
     * Hold / Reserve a court slot with Pessimistic Lock & Cache Lock.
     * Prevents race conditions and double bookings under concurrent load.
     */
    public function holdSlot(string $courtId, string $bookingDate, string $startTime, string $endTime, User $user, ?string $coachId = null): PadelBooking
    {
        $court = PadelCourt::where('id', $courtId)->where('is_active', true)->first();
        if (! $court) {
            throw new HttpException(404, 'Lapangan tidak ditemukan atau sedang tidak aktif.');
        }

        $startDt = Carbon::parse("$bookingDate $startTime");
        $endDt = Carbon::parse("$bookingDate $endTime");

        if ($endDt->lessThanOrEqualTo($startDt)) {
            throw new HttpException(422, 'Waktu selesai harus lebih besar dari waktu mulai.');
        }

        // 1. TAMENG CACHE LOCK (5 Menit Hold)
        $cacheLockKey = "padel_hold_{$courtId}_{$bookingDate}_" . $startDt->format('Hi');
        $cacheLock = Cache::lock($cacheLockKey, 300); // 5 menit hold

        if (! $cacheLock->get()) {
            throw new HttpException(409, 'Slot lapangan ini sedang di-hold oleh pemain lain. Silakan pilih jam lain.');
        }

        try {
            // 2. TAMENG PESSIMISTIC LOCK (InnoDB lockForUpdate)
            return DB::transaction(function () use ($court, $courtId, $bookingDate, $startDt, $endDt, $user, $coachId) {
                // Periksa apakah ada booking bentrok yang aktif (PENDING, LOCKED, atau PAID)
                $hasConflict = PadelBooking::where('court_id', $courtId)
                    ->where('booking_date', $bookingDate)
                    ->whereIn('status', ['PENDING', 'LOCKED', 'PAID'])
                    ->where('start_time', '<', $endDt)
                    ->where('end_time', '>', $startDt)
                    ->lockForUpdate() // Kunci baris database secara eksklusif
                    ->exists();

                if ($hasConflict) {
                    throw new HttpException(409, 'Slot lapangan pada jam tersebut sudah dipesan atau sedang dikunci.');
                }

                // Hitung biaya
                $durationHours = max(1, $startDt->diffInHours($endDt));
                $isPrime = (int)$startDt->format('H') >= 17; // Prime time 17:00 ke atas
                $courtRate = $isPrime ? $court->hourly_rate_prime : $court->hourly_rate_regular;
                $courtFee = $courtRate * $durationHours;
                $coachFee = 0.00;

                $bookingCode = 'BK-PADEL-' . strtoupper(Str::random(8));

                return PadelBooking::create([
                    'booking_code' => $bookingCode,
                    'user_id' => $user->id,
                    'court_id' => $courtId,
                    'coach_id' => $coachId,
                    'booking_date' => $bookingDate,
                    'start_time' => $startDt,
                    'end_time' => $endDt,
                    'court_fee' => $courtFee,
                    'coach_fee' => $coachFee,
                    'equipment_fee' => 0.00,
                    'total_amount' => $courtFee + $coachFee,
                    'status' => 'LOCKED', // Di-hold 5 menit menunggu pembayaran
                    'qr_code_hash' => hash('sha256', $bookingCode . $user->id),
                ]);
            });
        } catch (\Throwable $e) {
            // Jika transaksi gagal / conflict, rilis cache lock
            $cacheLock->release();
            throw $e;
        }
    }
}
