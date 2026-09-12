<?php

namespace App\Services\Padel;

use App\Exceptions\SlotConflictException;
use App\Models\Padel\CourtEquipment;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelBookingEquipment;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\Pos\Refund;
use App\Models\Pos\Voucher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PadelBookingService
{
    /**
     * Durasi kuncian slot (10 Menit = 600 Detik).
     */
    public const HOLD_DURATION_SECONDS = 600;

    /**
     * Durasi masa hidup Idempotency Key (24 Jam = 86.400 Detik).
     */
    public const IDEMPOTENCY_TTL_SECONDS = 86400;

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
     * Eksekusi checkout pembayaran bergaransi IDEMPOTENT (Anti Debit Ganda).
     * Dilengkapi header X-Idempotency-Key ber-TTL 24 Jam di Redis/Cache.
     */
    public function checkout(
        array $bookingIds,
        array $equipments,
        ?string $voucherCode,
        string $paymentMethod,
        string $idempotencyKey,
        User $user
    ): array {
        $cacheIdempotencyKey = "idempotency:padel:checkout:{$idempotencyKey}";

        // Cek apakah request ini sudah pernah berhasil diproses dalam 24 jam terakhir
        if ($cachedResponse = Cache::get($cacheIdempotencyKey)) {
            return $cachedResponse;
        }

        $bookings = PadelBooking::with('court')
            ->whereIn('id', $bookingIds)
            ->where('user_id', $user->id)
            ->where('status', 'LOCKED')
            ->get();

        if ($bookings->count() !== count($bookingIds)) {
            throw new HttpException(422, 'Satu atau lebih slot booking tidak valid atau masa kuncian (10 menit) telah kedaluwarsa.');
        }

        return DB::transaction(function () use ($bookings, $equipments, $voucherCode, $paymentMethod, $user, $cacheIdempotencyKey) {
            $courtTotal = $bookings->sum('court_fee');
            $equipmentTotal = 0;
            $equipmentItems = [];
            $orderId = 'ORD-PAD-' . strtoupper(Str::random(10));

            // Proses Sewa Peralatan (Raket, Bola, Handuk) - Flat per Order ID
            if (! empty($equipments)) {
                $primaryBooking = $bookings->first();

                foreach ($equipments as $item) {
                    $eq = CourtEquipment::find($item['equipment_id']);
                    if (! $eq) {
                        continue;
                    }

                    $qty = max(1, (int)$item['quantity']);
                    $subtotal = $eq->rental_price * $qty;
                    $equipmentTotal += $subtotal;

                    PadelBookingEquipment::create([
                        'order_id' => $orderId,
                        'booking_id' => $primaryBooking->id,
                        'equipment_id' => $eq->id,
                        'quantity' => $qty,
                        'unit_price' => $eq->rental_price,
                        'subtotal' => $subtotal,
                    ]);

                    $equipmentItems[] = [
                        'name' => $eq->name,
                        'quantity' => $qty,
                        'unit_price' => (float)$eq->rental_price,
                        'subtotal' => (float)$subtotal,
                    ];
                }

                $primaryBooking->increment('equipment_fee', $equipmentTotal);
                $primaryBooking->increment('total_amount', $equipmentTotal);
            }

            // Validasi Voucher Diskon
            $discountAmount = 0;
            if ($voucherCode) {
                $code = strtoupper(trim($voucherCode));
                if (in_array($code, ['HEMAT10', 'VANTAGE20', 'CLUB61', 'GOLDVIP'])) {
                    $discountAmount = 40000;
                }
            }

            // Biaya Layanan Gateway
            $gatewayFee = strtoupper($paymentMethod) === 'QRIS' ? 2800 : 4440;
            $grandTotal = max(0, $courtTotal + $equipmentTotal + $gatewayFee - $discountAmount);

            // 🛡️ QA DEFENSE 3: Susun Item Details Persis Sama dengan Gross Amount untuk Midtrans
            $midtransItems = [];
            foreach ($bookings as $b) {
                $midtransItems[] = [
                    'id' => substr($b->id, 0, 50),
                    'price' => (int) $b->court_fee,
                    'quantity' => 1,
                    'name' => substr('Sewa ' . $b->court->name, 0, 50),
                ];
            }
            foreach ($equipmentItems as $eq) {
                $midtransItems[] = [
                    'id' => substr('EQ-' . Str::slug($eq['name']), 0, 50),
                    'price' => (int) $eq['unit_price'],
                    'quantity' => (int) $eq['quantity'],
                    'name' => substr($eq['name'], 0, 50),
                ];
            }
            if ($gatewayFee > 0) {
                $midtransItems[] = [
                    'id' => 'FEE-GATEWAY',
                    'price' => (int) $gatewayFee,
                    'quantity' => 1,
                    'name' => 'Biaya Layanan Gerbang',
                ];
            }
            if ($discountAmount > 0) {
                $midtransItems[] = [
                    'id' => 'DISC-' . substr($voucherCode ?? 'PROMO', 0, 10),
                    'price' => -(int) $discountAmount,
                    'quantity' => 1,
                    'name' => 'Voucher Diskon',
                ];
            }

            // Panggil Payment Manager (Agnostik Multi-Driver: Midtrans, Xendit, Mock)
            $paymentManager = app(\App\Services\Payment\PaymentManager::class);
            $paymentResult = $paymentManager->createPayment([
                'order_id' => $orderId,
                'gross_amount' => (int) $grandTotal,
                'item_details' => $midtransItems,
                'customer_details' => [
                    'first_name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone ?? '081261617233',
                ],
            ]);

            // Petakan Order ID ke ID Bookings di Cache selama 24 Jam
            Cache::put("order_bookings:{$orderId}", $bookings->pluck('id')->toArray(), 86400);

            // Tentukan status awal transaksi
            $isMockOrCash = $paymentResult['is_mock'] || strtoupper($paymentMethod) === 'CASH';
            $initialStatus = $isMockOrCash ? 'PAID' : 'PENDING_PAYMENT';

            // Update semua booking dengan order_id dan simpan hash tiket QR
            foreach ($bookings as $booking) {
                $booking->update([
                    'order_id' => $orderId,
                    'status' => $initialStatus,
                    'qr_code_hash' => 'VNT-TICKET-' . strtoupper(bin2hex(random_bytes(16))),
                ]);
            }

            $primaryBooking = $bookings->first();

            $response = [
                'success' => true,
                'message' => $isMockOrCash
                    ? 'Pembayaran berhasil dikonfirmasi. E-Tiket aktif.'
                    : 'Sesi transaksi pembayaran berhasil dibuat. Silakan selesaikan pembayaran.',
                'data' => [
                    'driver' => $paymentResult['driver'] ?? $paymentManager->getDefaultDriver(),
                    'order_id' => $orderId,
                    'booking_id' => $primaryBooking->id,
                    'snap_token' => $paymentResult['snap_token'] ?? null,
                    'payment_url' => $paymentResult['payment_url'] ?? $paymentResult['redirect_url'],
                    'redirect_url' => $paymentResult['redirect_url'],
                    'checkout_mode' => $paymentResult['checkout_mode'] ?? 'POPUP',
                    'is_mock' => $paymentResult['is_mock'],
                    'payment_method' => strtoupper($paymentMethod),
                    'payment_status' => $initialStatus,
                    'court_fee' => (float) $courtTotal,
                    'equipment_fee' => (float) $equipmentTotal,
                    'gateway_fee' => (float) $gatewayFee,
                    'discount' => (float) $discountAmount,
                    'grand_total' => (float) $grandTotal,
                    'equipments' => $equipmentItems,
                    'bookings' => $bookings->map(function ($b) {
                        return [
                            'booking_id' => $b->id,
                            'booking_code' => $b->booking_code,
                            'court_name' => $b->court->name,
                            'date' => $b->booking_date->format('Y-m-d'),
                            'time' => $b->start_time->format('H:i') . ' - ' . $b->end_time->format('H:i'),
                            'qr_code_hash' => $b->qr_code_hash,
                        ];
                    }),
                ],
            ];

            // Simpan ke Cache Idempotency selama 24 Jam (Anti-Bloat)
            Cache::put($cacheIdempotencyKey, $response, self::IDEMPOTENCY_TTL_SECONDS);

            return $response;
        });
    }

    /**
     * Verifikasi Check-In di Frontdesk Kasir / Gate.
     * Dilengkapi toleransi double-scan ramah kasir (HTTP 200) dan alert serah-terima alat (Equipments Handover).
     *
     * @throws HttpException
     */
    public function checkIn(string $code, User $staffUser): array
    {
        $booking = PadelBooking::with(['court', 'user', 'equipments.equipment'])
            ->where(function ($q) use ($code) {
                $q->where('qr_code_hash', $code)
                    ->orWhere('booking_code', $code);
            })
            ->first();

        if (! $booking) {
            throw new HttpException(404, 'Tiket tidak ditemukan. Pastikan QR Code atau Kode Booking benar.');
        }

        $now = now();

        // Validasi jika sesi sudah lewat dan pemain belum pernah check-in -> Otomatis EXPIRED
        if ($now->gt($booking->end_time) && $booking->checked_in_at === null) {
            $booking->update(['status' => 'EXPIRED']);
            Cache::forget('kelola_pemesanan_tab_counts');
            throw new HttpException(400, "Tiket kedaluwarsa (Expired). Sesi bermain pada pukul {$booking->start_time->format('H:i')} - {$booking->end_time->format('H:i')} WIB telah selesai.");
        }

        if (in_array($booking->status, ['CANCELLED', 'EXPIRED', 'REFUNDED', 'REFUND_PENDING'])) {
            throw new HttpException(400, "Tiket tidak aktif atau telah dibatalkan. Status saat ini: {$booking->status}.");
        }

        if ($booking->status === 'PENDING' || $booking->status === 'PENDING_PAYMENT') {
            throw new HttpException(400, "Tiket belum lunas. Silakan selesaikan pembayaran terlebih dahulu.");
        }

        // Ambil daftar peralatan sewa (flat per order atau per booking)
        $equipmentList = [];
        if ($booking->order_id) {
            $orderEquipments = PadelBookingEquipment::with('equipment')
                ->where('order_id', $booking->order_id)
                ->get();
            foreach ($orderEquipments as $oe) {
                $equipmentList[] = [
                    'name' => $oe->equipment ? $oe->equipment->name : 'Peralatan Padel',
                    'quantity' => $oe->quantity,
                ];
            }
        } elseif ($booking->equipments && $booking->equipments->isNotEmpty()) {
            foreach ($booking->equipments as $be) {
                $equipmentList[] = [
                    'name' => $be->equipment ? $be->equipment->name : 'Peralatan Padel',
                    'quantity' => $be->quantity,
                ];
            }
        }

        $earliestCheckIn = $booking->start_time->copy()->subMinutes(45);

        // Validasi waktu awal check-in (-45 menit)
        if ($now->lt($earliestCheckIn)) {
            $earlyMinutes = (int) $now->diffInMinutes($booking->start_time);
            throw new HttpException(400, "Akses check-in baru dibuka 45 menit sebelum sesi dimulai. Jadwal main masih {$earlyMinutes} menit lagi (pukul {$booking->start_time->format('H:i')} WIB).");
        }

        // 🛡️ TOLERANSI DOUBLE-SCAN KASIR: Kembalikan 200 OK dengan alert kuning/informasi
        if ($booking->status === 'CHECKED_IN' || $booking->checked_in_at !== null) {
            $formattedTime = $booking->checked_in_at ? $booking->checked_in_at->format('H:i') : 'sebelumnya';
            return [
                'already_checked_in' => true,
                'message' => "Member atas nama {$booking->user->name} sudah melakukan check-in sebelumnya pada pukul {$formattedTime} WIB.",
                'booking_code' => $booking->booking_code,
                'player_name' => $booking->user->name,
                'court_name' => $booking->court->name,
                'schedule' => $booking->booking_date->format('d M Y') . ', ' . $booking->start_time->format('H:i') . ' - ' . $booking->end_time->format('H:i') . ' WIB',
                'checked_in_at' => $booking->checked_in_at ? $booking->checked_in_at->toISOString() : $now->toISOString(),
                'equipments' => $equipmentList,
                'gate_marshall' => $staffUser->name,
            ];
        }

        // Scan Pertama: Ubah status menjadi CHECKED_IN
        $booking->update([
            'status' => 'CHECKED_IN',
            'checked_in_at' => $now,
        ]);

        Cache::forget('kelola_pemesanan_tab_counts');

        return [
            'already_checked_in' => false,
            'message' => "Check-in berhasil! Akses lapangan dibuka. Silakan serahkan peralatan sewa kepada pemain.",
            'booking_code' => $booking->booking_code,
            'player_name' => $booking->user->name,
            'court_name' => $booking->court->name,
            'schedule' => $booking->booking_date->format('d M Y') . ', ' . $booking->start_time->format('H:i') . ' - ' . $booking->end_time->format('H:i') . ' WIB',
            'checked_in_at' => $now->toISOString(),
            'equipments' => $equipmentList,
            'gate_marshall' => $staffUser->name,
        ];
    }

    /**
     * Tandai sesi bermain selesai (Complete) saat pemain keluar lapangan / mengembalikan raket.
     */
    public function completeBooking(string $bookingId, User $staffUser): PadelBooking
    {
        $booking = PadelBooking::findOrFail($bookingId);
        $booking->update(['status' => 'COMPLETED']);
        Cache::forget('kelola_pemesanan_tab_counts');
        return $booking;
    }

    /**
     * Otomatis menyinkronkan status booking yang telah lewat jadwal bermain:
     * 1. Status PAID & end_time < now() & checked_in_at IS NULL -> EXPIRED (No-show / Tiket Hangus)
     * 2. Status CHECKED_IN & end_time < now() -> COMPLETED (Selesai Bermain)
     * 3. Rilis expired locks (> 10 menit)
     */
    public function syncExpiredAndCompletedBookings(): array
    {
        $now = now();

        // 1. Booking yang lunas tapi tidak datang sampai sesi berakhir -> EXPIRED (No-Show)
        $expiredCount = PadelBooking::where('status', 'PAID')
            ->where('end_time', '<', $now)
            ->whereNull('checked_in_at')
            ->update(['status' => 'EXPIRED']);

        // 2. Pemain yang sudah check-in dan sesinya telah lewat -> COMPLETED
        $completedCount = PadelBooking::where('status', 'CHECKED_IN')
            ->where('end_time', '<', $now)
            ->update(['status' => 'COMPLETED']);

        // 3. Rilis slot LOCKED yang kedaluwarsa
        $releasedLocks = $this->releaseExpiredLocks();

        if ($expiredCount > 0 || $completedCount > 0 || $releasedLocks > 0) {
            Cache::forget('kelola_pemesanan_tab_counts');
        }

        return [
            'expired' => $expiredCount,
            'completed' => $completedCount,
            'released_locks' => $releasedLocks,
        ];
    }

    /**
     * Mengajukan pembatalan dengan jalur refund resmi (H-24).
     */
    public function requestRefund(string $bookingId, string $reason, User $user): PadelBooking
    {
        $booking = PadelBooking::where('id', $bookingId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($booking->status !== 'PAID') {
            throw new HttpException(400, 'Hanya booking lunas (PAID) yang dapat diajukan refund.');
        }

        // Syarat H-24
        if ($booking->start_time->diffInHours(now(), false) > -24) {
            throw new HttpException(422, 'Pembatalan dengan refund hanya dapat diajukan minimal 24 jam sebelum jadwal bertanding.');
        }

        $booking->update([
            'status' => 'REFUND_PENDING',
            'cancel_reason' => $reason,
        ]);

        return $booking;
    }

    /**
     * Mengambil riwayat booking user terfilter.
     */
    public function myBookings(User $user, ?string $status = null): Collection
    {
        $query = PadelBooking::with(['court', 'coach', 'equipments.equipment'])
            ->where('user_id', $user->id)
            ->latest('start_time');

        if ($status) {
            $statusUpper = strtoupper($status);
            if ($statusUpper === 'UPCOMING') {
                $query->whereIn('status', ['LOCKED', 'PAID'])->where('start_time', '>=', now());
            } elseif ($statusUpper === 'COMPLETED') {
                $query->whereIn('status', ['CHECKED_IN', 'COMPLETED']);
            } elseif ($statusUpper === 'CANCELLED') {
                $query->whereIn('status', ['CANCELLED', 'EXPIRED', 'REFUND_PENDING', 'REFUNDED']);
            }
        }

        return $query->get();
    }

    /**
     * Mengambil detail satu tiket booking beserta konteks order terpadu.
     */
    public function getTicket(string $bookingId, User $user): PadelBooking
    {
        $booking = PadelBooking::with(['court', 'coach', 'equipments.equipment'])
            ->where(function ($q) use ($bookingId) {
                $q->where('id', $bookingId)
                  ->orWhere('booking_code', $bookingId)
                  ->orWhere('order_id', $bookingId);
            })
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Jika booking ini terikat dengan order_id, sertakan seluruh sesi se-order dan alat sewa
        if ($booking->order_id) {
            $orderBookings = PadelBooking::with('court')
                ->where('order_id', $booking->order_id)
                ->where('user_id', $user->id)
                ->orderBy('start_time')
                ->get();

            $orderEquipments = PadelBookingEquipment::with('equipment')
                ->where('order_id', $booking->order_id)
                ->get();

            $totalEquipmentFee = $orderEquipments->sum('subtotal');
            $totalCourtFee = $orderBookings->sum('court_fee');

            $booking->setAttribute('order_bookings', $orderBookings);
            $booking->setAttribute('order_equipments', $orderEquipments);
            $booking->setAttribute('order_court_fee', (float) $totalCourtFee);
            $booking->setAttribute('order_equipment_fee', (float) $totalEquipmentFee);
            $booking->setAttribute('order_grand_total', (float) ($totalCourtFee + $totalEquipmentFee));
        }

        return $booking;
    }

    /**
     * Garbage Collection: Merilis semua slot LOCKED yang ditinggal > 10 menit.
     */
    public function releaseExpiredLocks(): int
    {
        $expiredThreshold = now()->subSeconds(self::HOLD_DURATION_SECONDS);

        $expiredBookings = PadelBooking::where('status', 'LOCKED')
            ->where('created_at', '<', $expiredThreshold)
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

    /**
     * Mengambil daftar slot reschedule yang tersedia dengan durasi terkunci (Anti-Jebakan Durasi)
     * dan validasi ketat anti-tanggal lampau.
     *
     * @throws HttpException
     */
    public function getAvailableRescheduleSlots(
        string $bookingId,
        string $targetCourtId,
        string $targetDate,
        string $timezone = 'Asia/Jakarta'
    ): array {
        $parsedDate = Carbon::parse($targetDate, $timezone)->startOfDay();
        if ($parsedDate->isPast() && ! $parsedDate->isToday()) {
            throw new HttpException(422, 'Tanggal reschedule tidak boleh di masa lampau.');
        }

        $booking = PadelBooking::with('court')->findOrFail($bookingId);
        $durationHours = (int) $booking->start_time->diffInHours($booking->end_time);
        if ($durationHours < 1) {
            $durationHours = 1;
        }

        $court = PadelCourt::findOrFail($targetCourtId);
        $dateStr = $parsedDate->format('Y-m-d');
        $isWeekend = $parsedDate->isWeekend();

        $activeBookings = PadelBooking::where('court_id', $court->id)
            ->where('booking_date', $dateStr)
            ->whereIn('status', ['LOCKED', 'PAID', 'CHECKED_IN'])
            ->where('id', '!=', $booking->id)
            ->get();

        // Bulk prefetch Distributed Cache Locks untuk seluruh rentang jam lapangan tujuan (1 query)
        $allSlotCacheKeys = [];
        for ($h = 6; $h < 23; $h++) {
            $allSlotCacheKeys[] = "padel_lock:{$court->id}:{$dateStr}:" . sprintf('%02d00', $h);
        }
        $bulkSlotLocks = Cache::many($allSlotCacheKeys);

        $availableSlots = [];

        // Jam operasional: 06:00 sampai 23:00 (batas start adalah 23 - durasi)
        for ($startHour = 6; $startHour <= (23 - $durationHours); $startHour++) {
            $slotStart = Carbon::parse("{$dateStr} " . sprintf('%02d:00', $startHour), $timezone);
            $slotEnd = $slotStart->copy()->addHours($durationHours);

            // Contiguous check: pastikan seluruh jam berturut-turut kosong
            $isAvailable = true;
            $currCheck = $slotStart->copy();
            $estimatedFee = 0;
            $hasPrime = false;

            while ($currCheck->lt($slotEnd)) {
                $subStart = $currCheck->copy();
                $subEnd = $subStart->copy()->addHour();
                $hour = (int) $subStart->format('H');

                // Cek tabrakan booking aktif
                $hasCollision = $activeBookings->first(function ($b) use ($subStart, $subEnd) {
                    return $b->start_time < $subEnd && $b->end_time > $subStart;
                });

                // Cek distributed cache lock via bulk prefetch
                $cacheKey = "padel_lock:{$court->id}:{$dateStr}:" . $subStart->format('Hi');
                $isCacheLocked = ! empty($bulkSlotLocks[$cacheKey]);

                if ($hasCollision || $isCacheLocked) {
                    $isAvailable = false;
                    break;
                }

                $isPrime = $isWeekend || $hour >= 17;
                if ($isPrime) {
                    $hasPrime = true;
                }
                $estimatedFee += $isPrime ? (float) $court->hourly_rate_prime : (float) $court->hourly_rate_regular;

                $currCheck->addHour();
            }

            if ($isAvailable) {
                $delta = $estimatedFee - (float) $booking->court_fee;
                $startStr = sprintf('%02d:00', $startHour);
                $endStr = sprintf('%02d:00', $startHour + $durationHours);

                $availableSlots[] = [
                    'start_time' => $startStr,
                    'end_time' => $endStr,
                    'duration_hours' => $durationHours,
                    'label' => "{$startStr} - {$endStr} WIB ({$durationHours} Jam)" . ($hasPrime ? ' [Prime Time]' : ' [Reguler]'),
                    'estimated_fee' => $estimatedFee,
                    'delta' => $delta,
                    'is_prime' => $hasPrime,
                ];
            }
        }

        return [
            'booking_id' => $booking->id,
            'duration_hours' => $durationHours,
            'original_court_fee' => (float) $booking->court_fee,
            'target_court_name' => $court->name,
            'target_date' => $dateStr,
            'slots' => $availableSlots,
        ];
    }

    /**
     * Eksekusi Admin Override: Pindah Jadwal Padel Booking (Atomik DB::transaction).
     *
     * @throws HttpException
     * @throws SlotConflictException
     */
    public function adminRescheduleBooking(
        string $bookingId,
        string $newCourtId,
        string $newDate,
        string $newStartTimeStr,
        string $reason,
        User $adminUser,
        ?string $paymentMethod = 'CASH',
        bool $isDeltaPaid = true,
        string $timezone = 'Asia/Jakarta'
    ): array {
        $parsedDate = Carbon::parse($newDate, $timezone)->startOfDay();
        if ($parsedDate->isPast() && ! $parsedDate->isToday()) {
            throw new HttpException(422, 'Tanggal reschedule tidak boleh di masa lampau.');
        }

        return DB::transaction(function () use (
            $bookingId, $newCourtId, $newStartTimeStr, $reason,
            $adminUser, $paymentMethod, $isDeltaPaid, $timezone, $parsedDate
        ) {
            $booking = PadelBooking::with(['order', 'court'])->where('id', $bookingId)->lockForUpdate()->firstOrFail();

            if (! in_array($booking->status, ['PAID', 'LOCKED'])) {
                throw new HttpException(400, "Booking dengan status {$booking->status} tidak dapat di-reschedule.");
            }

            $durationHours = (int) $booking->start_time->diffInHours($booking->end_time);
            if ($durationHours < 1) {
                $durationHours = 1;
            }

            $newCourt = PadelCourt::where('id', $newCourtId)->lockForUpdate()->firstOrFail();
            $dateStr = $parsedDate->format('Y-m-d');
            $isWeekend = $parsedDate->isWeekend();

            $newStartDt = Carbon::parse("{$dateStr} {$newStartTimeStr}", $timezone);
            $newEndDt = $newStartDt->copy()->addHours($durationHours);

            // Contiguous Check: Pastikan tidak ada tabrakan di jadwal baru
            $hasConflict = PadelBooking::where('court_id', $newCourt->id)
                ->whereDate('booking_date', $dateStr)
                ->whereIn('status', ['LOCKED', 'PAID', 'CHECKED_IN'])
                ->where('id', '!=', $booking->id)
                ->where('start_time', '<', $newEndDt->format('Y-m-d H:i:s'))
                ->where('end_time', '>', $newStartDt->format('Y-m-d H:i:s'))
                ->lockForUpdate()
                ->exists();

            if ($hasConflict) {
                throw new SlotConflictException(
                    "Jadwal baru pada {$newCourt->name} jam {$newStartDt->format('H:i')} - {$newEndDt->format('H:i')} bentrok dengan pemesanan lain.",
                    $newCourt->name,
                    "{$newStartDt->format('H:i')} - {$newEndDt->format('H:i')}"
                );
            }

            // Distributed Cache Lock check on new slots
            $currCheck = $newStartDt->copy();
            while ($currCheck->lt($newEndDt)) {
                $lockKey = "padel_lock:{$newCourt->id}:{$dateStr}:" . $currCheck->format('Hi');
                if (Cache::has($lockKey)) {
                    throw new SlotConflictException(
                        "Slot {$newCourt->name} jam {$currCheck->format('H:i')} sedang dikunci transaksi lain.",
                        $newCourt->name,
                        $currCheck->format('H:i')
                    );
                }
                $currCheck->addHour();
            }

            // Hitung tarif baru per jam (menjaga transisi reguler vs prime)
            $newCourtFee = 0;
            $currFee = $newStartDt->copy();
            while ($currFee->lt($newEndDt)) {
                $hour = (int) $currFee->format('H');
                $isPrime = $isWeekend || $hour >= 17;
                $rate = $isPrime ? (float) $newCourt->hourly_rate_prime : (float) $newCourt->hourly_rate_regular;
                $newCourtFee += $rate;
                $currFee->addHour();
            }

            $oldCourtFee = (float) $booking->court_fee;
            $delta = $newCourtFee - $oldCourtFee;

            // Lepas Distributed Cache Lock pada jadwal lama
            $oldLock = $booking->start_time->copy();
            $oldEnd = $booking->end_time->copy();
            while ($oldLock->lt($oldEnd)) {
                Cache::forget("padel_lock:{$booking->court_id}:{$booking->booking_date->format('Y-m-d')}:" . $oldLock->format('Hi'));
                $oldLock->addHour();
            }

            $newQrCodeHash = hash_hmac('sha256', $booking->booking_code . $booking->user_id . $newCourt->id . $newStartDt->toISOString(), config('app.key'));
            $targetStatus = 'PAID';

            // Pastikan booking memiliki relasi Order terikat untuk pembukuan
            $order = $this->ensureBookingOrder($booking);

            // Eksekusi Finansial Berdasarkan Price Delta
            if ($delta > 0) {
                // Kurang bayar (Reguler -> Prime): Buat supplemental payment record
                $paymentStatus = $isDeltaPaid ? 'SUCCESS' : 'PENDING';
                Payment::create([
                    'order_id' => $order->id,
                    'payment_gateway' => $paymentMethod ?? 'CASH',
                    'transaction_id' => 'SUPP-' . strtoupper(Str::random(12)),
                    'amount' => $delta,
                    'payment_method' => $paymentMethod ?? 'CASH',
                    'status' => $paymentStatus,
                    'payload_log' => [
                        'type' => 'RESCHEDULE_PRICE_DELTA',
                        'booking_id' => $booking->id,
                        'admin_id' => $adminUser->id,
                        'reason' => $reason,
                        'delta' => $delta,
                    ],
                ]);

                if (! $isDeltaPaid) {
                    // Jika belum dibayar kasir, status LOCKED dan QR code ditahan
                    $targetStatus = 'LOCKED';
                    $newQrCodeHash = null;
                }

                $booking->court_fee = $newCourtFee;
                $booking->total_amount += $delta;

                if ($booking->order) {
                    $booking->order->update([
                        'subtotal' => $booking->order->subtotal + $delta,
                        'grand_total' => $booking->order->grand_total + $delta,
                    ]);
                }
            } elseif ($delta < 0) {
                // Lebih bayar (Prime -> Reguler): Selisih dilempar ke tabel refunds sebagai saldo deposit member
                $refundAmount = abs($delta);
                $origPayment = Payment::where('order_id', $booking->order_id)
                    ->where('status', 'SUCCESS')
                    ->latest()
                    ->first();

                Refund::create([
                    'order_id' => $booking->order_id,
                    'payment_id' => $origPayment?->id ?? Payment::where('order_id', $booking->order_id)->first()?->id,
                    'refund_amount' => $refundAmount,
                    'reason' => "[DEPOSIT_MEMBER] Selisih reschedule booking {$booking->booking_code} ke jam Reguler",
                    'status' => 'PROCESSED',
                    'processed_at' => now(),
                ]);

                $booking->court_fee = $newCourtFee;
                $booking->total_amount -= $refundAmount;

                if ($booking->order) {
                    $booking->order->update([
                        'subtotal' => max(0, $booking->order->subtotal - $refundAmount),
                        'grand_total' => max(0, $booking->order->grand_total - $refundAmount),
                    ]);
                }
            } else {
                // Delta == 0: Tarif sama persis
                $booking->court_fee = $newCourtFee;
            }

            // Flat Equipment Invariant: Tabel padel_booking_equipments terikat ke order_id, tidak perlu disentuh.

            // Update row booking
            $booking->court_id = $newCourt->id;
            $booking->booking_date = $dateStr;
            $booking->start_time = $newStartDt;
            $booking->end_time = $newEndDt;
            $booking->status = $targetStatus;
            $booking->qr_code_hash = $newQrCodeHash;
            $booking->reschedule_count = $booking->reschedule_count + 1;
            $booking->cancel_reason = "Reschedule: {$reason} (Admin: {$adminUser->name})";
            $booking->save();

            // Pasang distributed cache lock baru jika status LOCKED
            if ($targetStatus === 'LOCKED') {
                $currNew = $newStartDt->copy();
                while ($currNew->lt($newEndDt)) {
                    Cache::put("padel_lock:{$newCourt->id}:{$dateStr}:" . $currNew->format('Hi'), $booking->user_id, self::HOLD_DURATION_SECONDS);
                    $currNew->addHour();
                }
            }

            return [
                'success' => true,
                'message' => 'Jadwal booking berhasil dipindahkan.',
                'booking' => $booking->fresh(['court', 'order']),
                'delta' => $delta,
                'is_locked' => $targetStatus === 'LOCKED',
            ];
        });
    }

    /**
     * Pelunasan tagihan selisih (Supplemental Payment) oleh kasir.
     * Mengubah status LOCKED menjadi PAID dan merilis QR tiket baru.
     */
    public function adminSettleSupplementalPayment(string $bookingId, string $paymentMethod, User $adminUser): array
    {
        return DB::transaction(function () use ($bookingId, $paymentMethod, $adminUser) {
            $booking = PadelBooking::with(['order', 'court'])->where('id', $bookingId)->lockForUpdate()->firstOrFail();
            $order = $this->ensureBookingOrder($booking);

            $pendingPayment = Payment::where('order_id', $order->id)
                ->where('status', 'PENDING')
                ->latest()
                ->first();

            if ($pendingPayment) {
                $pendingPayment->update([
                    'status' => 'SUCCESS',
                    'payment_method' => $paymentMethod,
                    'payment_gateway' => $paymentMethod,
                    'payload_log' => array_merge($pendingPayment->payload_log ?? [], [
                        'settled_by' => $adminUser->id,
                        'settled_at' => now()->toIso8601String(),
                    ]),
                ]);
            }

            // Rilis QR code dan ubah status menjadi PAID
            $newQrCodeHash = hash_hmac('sha256', $booking->booking_code . $booking->user_id . $booking->court_id . $booking->start_time->toISOString(), config('app.key'));
            $booking->update([
                'status' => 'PAID',
                'qr_code_hash' => $newQrCodeHash,
            ]);

            return [
                'success' => true,
                'message' => 'Pelunasan tagihan berhasil! Tiket QR telah dirilis.',
                'booking' => $booking->fresh(['court', 'order']),
            ];
        });
    }

    /**
     * Pembatalan & Refund Resmi oleh Kasir/Manager.
     * Melepaskan kuncian slot lapangan, mematikan tiket QR, dan mencatat transaksi ke tabel refunds.
     */
    public function adminCancelAndRefund(
        string $bookingId,
        float $refundAmount,
        string $refundMethod,
        string $reasonCategory,
        string $notes,
        User $adminUser
    ): array {
        return DB::transaction(function () use ($bookingId, $refundAmount, $refundMethod, $reasonCategory, $notes, $adminUser) {
            $booking = PadelBooking::with(['order', 'court'])->where('id', $bookingId)->lockForUpdate()->firstOrFail();

            if (! in_array($booking->status, ['PAID', 'LOCKED', 'REFUND_PENDING'])) {
                throw new HttpException(400, "Booking dengan status {$booking->status} tidak dapat dibatalkan.");
            }

            // Release distributed cache locks
            $currLock = $booking->start_time->copy();
            $endLock = $booking->end_time->copy();
            while ($currLock->lt($endLock)) {
                Cache::forget("padel_lock:{$booking->court_id}:{$booking->booking_date->format('Y-m-d')}:" . $currLock->format('Hi'));
                $currLock->addHour();
            }

            $newStatus = $refundAmount > 0 ? 'REFUNDED' : 'CANCELLED';

            if ($refundAmount > 0) {
                $order = $this->ensureBookingOrder($booking);

                $origPayment = Payment::where('order_id', $order->id)
                    ->where('status', 'SUCCESS')
                    ->latest()
                    ->first();

                if (! $origPayment) {
                    $origPayment = Payment::create([
                        'order_id' => $order->id,
                        'payment_gateway' => 'CASH',
                        'transaction_id' => 'INIT-' . strtoupper(Str::random(10)),
                        'amount' => (float) $booking->total_amount,
                        'payment_method' => 'CASH',
                        'status' => 'SUCCESS',
                    ]);
                }

                Refund::create([
                    'order_id' => $order->id,
                    'payment_id' => $origPayment->id,
                    'refund_amount' => $refundAmount,
                    'reason' => "[{$refundMethod}] [{$reasonCategory}] {$notes}",
                    'status' => 'PROCESSED',
                    'processed_at' => now(),
                ]);
            }

            $booking->update([
                'status' => $newStatus,
                'qr_code_hash' => null,
                'cancel_reason' => "[{$reasonCategory}] {$notes} (Diproses oleh: {$adminUser->name})",
            ]);

            return [
                'success' => true,
                'message' => 'Reservasi berhasil dibatalkan dan diproses refund.',
                'booking' => $booking->fresh(['court', 'order']),
            ];
        });
    }

    /**
     * Memastikan booking memiliki relasi Order yang valid di tabel orders.
     * Mencegah kegagalan Foreign Key constraint saat mutasi pembayaran dan refund.
     */
    protected function ensureBookingOrder(PadelBooking $booking): Order
    {
        $order = null;
        if ($booking->order_id) {
            $order = Order::find($booking->order_id)
                ?? Order::where('order_number', $booking->order_id)->first();
        }

        if (! $order) {
            $orderNumber = ($booking->order_id && str_starts_with($booking->order_id, 'ORD-'))
                ? $booking->order_id
                : ('ORD-' . ($booking->booking_code ?: strtoupper(Str::random(8))));

            if (Order::where('order_number', $orderNumber)->exists()) {
                $orderNumber .= '-' . strtoupper(Str::random(4));
            }

            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => $booking->user_id,
                'subtotal' => $booking->total_amount,
                'grand_total' => $booking->total_amount,
                'payment_status' => in_array($booking->status, ['PAID', 'COMPLETED', 'CHECKED_IN']) ? 'PAID' : 'PENDING',
            ]);

            $booking->order_id = $order->id;
            $booking->saveQuietly();
        }

        if ($booking->order_id !== $order->id) {
            $booking->order_id = $order->id;
            $booking->saveQuietly();
        }

        $booking->setRelation('order', $order);

        return $order;
    }
}

