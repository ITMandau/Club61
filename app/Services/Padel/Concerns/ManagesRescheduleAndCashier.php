<?php

namespace App\Services\Padel\Concerns;

use App\Exceptions\SlotConflictException;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\Payment;
use App\Models\Pos\Refund;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait ManagesRescheduleAndCashier
{
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
            ->whereIn('status', ['LOCKED', 'PENDING_PAYMENT', 'PENDING', 'PAID', 'CHECKED_IN'])
            ->where('id', '!=', $booking->id)
            ->get();

        $openHour = (int) substr($court->open_time ?: '06:00', 0, 2);
        $closeVal = $court->close_time ?: '23:00';
        $closeHour = ($closeVal === '00:00' || $closeVal === '24:00') ? 24 : (int) substr($closeVal, 0, 2);

        // Bulk prefetch Distributed Cache Locks untuk seluruh rentang jam lapangan tujuan (1 query)
        $allSlotCacheKeys = [];
        for ($h = $openHour; $h < $closeHour; $h++) {
            $allSlotCacheKeys[] = "padel_lock:{$court->id}:{$dateStr}:" . sprintf('%02d00', $h);
        }
        $bulkSlotLocks = Cache::many($allSlotCacheKeys);

        $availableSlots = [];
        $timeWindow = $this->rescheduleMembershipTimeWindow($booking);
        $nowLocal = Carbon::now($timezone);
        $isToday = $dateStr === $nowLocal->format('Y-m-d');

        // Jam operasional: open_time sampai close_time (batas start adalah closeHour - durasi)
        for ($startHour = $openHour; $startHour <= ($closeHour - $durationHours); $startHour++) {
            // Aturan jam lewat sama dengan grid booking: jam sebelum jam berjalan hari ini tidak ditawarkan.
            if ($isToday && $startHour < (int) $nowLocal->format('H')) {
                continue;
            }

            $slotStart = Carbon::parse("{$dateStr} " . sprintf('%02d:00', $startHour), $timezone);
            $slotEnd = $slotStart->copy()->addHours($durationHours);

            // Contiguous check: pastikan seluruh jam berturut-turut kosong
            $isAvailable = true;
            $currCheck = $slotStart->copy();
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

                if ($isWeekend || $hour >= 17) {
                    $hasPrime = true;
                }

                $currCheck->addHour();
            }

            if ($isAvailable) {
                $quote = $this->quoteReschedule($booking, $court, $slotStart, $slotEnd, $timeWindow);
                if ($quote['blocked_reason']) {
                    continue; // di luar jam berlaku paket membership — benefit tidak bisa ikut pindah
                }

                $startStr = sprintf('%02d:00', $startHour);
                $endStr = sprintf('%02d:00', $startHour + $durationHours);

                $availableSlots[] = [
                    'start_time' => $startStr,
                    'end_time' => $endStr,
                    'duration_hours' => $durationHours,
                    'label' => "{$startStr} - {$endStr} WIB ({$durationHours} Jam)" . ($hasPrime ? ' [Prime Time]' : ' [Reguler]'),
                    'estimated_fee' => $quote['net_new'],
                    'gross_fee' => $quote['gross_new'],
                    'benefit_discount' => $quote['member_discount_new'] + $quote['sponsor_discount_new'],
                    'delta' => $quote['court_delta'],
                    'tax_delta' => $quote['tax'],
                    'admin_fee_delta' => $quote['admin_fee'],
                    'total_delta' => $quote['total_charge'],
                    'forfeited' => $quote['forfeited'],
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
            'membership_time_window' => $timeWindow,
            'slots' => $availableSlots,
        ];
    }

    /**
     * SATU-SATUNYA rumus selisih reschedule — dipakai preview di modal DAN eksekusi, jadi angka yang
     * dilihat kasir selalu sama dengan yang ditagih.
     *
     * court_fee yang tersimpan adalah nominal SETELAH benefit (kuota/diskon member, voucher sponsor).
     * Dulu selisih = tarif normal jadwal baru - court_fee, akibatnya booking yang dibayar pakai kuota
     * (court_fee 0) ditagih harga penuh jadwal baru (bayar dua kali) dan booking diskon 20% ditagih
     * ulang 20%-nya. Sekarang benefit ikut pindah dengan PROPORSI yang sama dengan booking aslinya:
     *   - tanpa benefit      -> jadwal baru harga normal
     *   - kuota jam (100%)   -> jadwal baru tetap tertutup kuota (durasi terkunci, jam kuota sama)
     *   - diskon %           -> jadwal baru dapat diskon % yang sama
     *   - voucher sponsor    -> sama, proporsional jam yang ditutup voucher
     *
     * Kebijakan (PM, 30 Sep 2026): pindah ke jadwal lebih murah -> selisih HANGUS, tidak dikembalikan.
     *
     * @param  array{start: string, end: string}|null  $timeWindow  jendela jam paket member (null = bebas)
     */
    public function quoteReschedule(PadelBooking $booking, PadelCourt $court, Carbon $start, Carbon $end, ?array $timeWindow = null): array
    {
        $isWeekend = $start->isWeekend();
        $grossNew = 0.0;
        for ($cursor = $start->copy(); $cursor->lt($end); $cursor->addHour()) {
            $isPrime = $isWeekend || (int) $cursor->format('H') >= 17;
            $grossNew += $isPrime ? (float) $court->hourly_rate_prime : (float) $court->hourly_rate_regular;
        }

        $paidCourt = (float) $booking->court_fee;
        $memberDiscount = (float) ($booking->member_discount_court ?? 0);
        $sponsorDiscount = (float) ($booking->sponsor_discount_court ?? 0);
        $grossOld = $paidCourt + $memberDiscount + $sponsorDiscount;

        $memberShare = $grossOld > 0 ? min(1.0, $memberDiscount / $grossOld) : 0.0;
        $sponsorShare = $grossOld > 0 ? min(1.0 - $memberShare, $sponsorDiscount / $grossOld) : 0.0;

        $memberDiscountNew = round($grossNew * $memberShare, 2);
        $sponsorDiscountNew = round($grossNew * $sponsorShare, 2);
        $netNew = max(0.0, round($grossNew - $memberDiscountNew - $sponsorDiscountNew, 2));
        $courtDelta = round($netNew - $paidCourt, 2);

        $blockedReason = null;
        if ($memberDiscount > 0 && $timeWindow !== null) {
            if ($start->format('H:i:s') < $timeWindow['start'] || $end->format('H:i:s') > $timeWindow['end']) {
                $blockedReason = "Jadwal baru di luar jam berlaku paket membership ({$timeWindow['start']} - {$timeWindow['end']}), benefit member tidak bisa ikut dipindah ke jam ini.";
            }
        }

        $tax = 0.0;
        $adminFee = 0.0;
        $totalCharge = 0.0;
        if ($courtDelta > 0) {
            $calc = app(\App\Services\Finance\TaxAndFeeService::class)->calculate(
                subtotal: $courtDelta,
                discountAmount: 0,
                channel: $this->rescheduleFeeChannel($booking),
                module: 'PADEL'
            );
            $tax = (float) $calc['tax_amount'];
            $adminFee = (float) $calc['admin_fee_amount'];
            $totalCharge = (float) $calc['grand_total'];
        }

        return [
            'gross_new' => $grossNew,
            'member_discount_new' => $memberDiscountNew,
            'sponsor_discount_new' => $sponsorDiscountNew,
            'net_new' => $netNew,
            'paid_court' => $paidCourt,
            'court_delta' => $courtDelta,
            'tax' => $tax,
            'admin_fee' => $adminFee,
            'total_charge' => $totalCharge,
            'forfeited' => $courtDelta < 0 ? abs($courtDelta) : 0.0,
            'blocked_reason' => $blockedReason,
        ];
    }

    /** Biaya selisih mengikuti kanal transaksi aslinya (booking online vs walk-in kasir). */
    protected function rescheduleFeeChannel(PadelBooking $booking): string
    {
        return $booking->order?->order_type === 'WALK_IN' ? 'POS_WALKIN' : 'ONLINE';
    }

    /** @return array{start: string, end: string}|null */
    protected function rescheduleMembershipTimeWindow(PadelBooking $booking): ?array
    {
        if (! $booking->membership_balance_id || (float) $booking->member_discount_court <= 0) {
            return null;
        }

        $balance = \App\Models\Membership\UserMembershipBalance::find($booking->membership_balance_id);

        return $balance && $balance->time_window_start && $balance->time_window_end
            ? ['start' => (string) $balance->time_window_start, 'end' => (string) $balance->time_window_end]
            : null;
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
        ?string $paymentMethod = 'QRIS',
        bool $isDeltaPaid = true,
        string $timezone = 'Asia/Jakarta',
        array $paymentProof = [],
    ): array {
        // 100% Cashless: pembayaran tunai tidak diperbolehkan sama sekali untuk pelunasan selisih reschedule.
        if ($paymentMethod && in_array(strtoupper($paymentMethod), ['CASH', 'TUNAI'])) {
            throw new HttpException(422, 'Pembayaran tunai (CASH) tidak diperbolehkan. Venue Club 61 beroperasi 100% Cashless.');
        }

        $parsedDate = Carbon::parse($newDate, $timezone)->startOfDay();
        if ($parsedDate->isPast() && ! $parsedDate->isToday()) {
            throw new HttpException(422, 'Tanggal reschedule tidak boleh di masa lampau.');
        }

        return DB::transaction(function () use (
            $bookingId, $newCourtId, $newStartTimeStr, $reason,
            $adminUser, $paymentMethod, $isDeltaPaid, $timezone, $parsedDate, $paymentProof
        ) {
            $booking = PadelBooking::with(['order', 'court'])->where('id', $bookingId)->lockForUpdate()->firstOrFail();

            // Hanya booking yang SUDAH LUNAS. Dulu status LOCKED juga diterima: keranjang customer yang belum
            // dibayar bisa "dipindah" admin lalu keluar sebagai PAID + QR aktif tanpa uang masuk, dan booking
            // yang masih punya tagihan selisih reschedule bisa dipindah lagi sehingga tagihannya menumpuk ganda.
            if ($booking->status !== 'PAID') {
                $hasPendingDelta = $booking->status === 'LOCKED' && (int) $booking->reschedule_count > 0;
                throw new HttpException(400, $hasPendingDelta
                    ? 'Booking ini masih punya tagihan selisih reschedule yang belum lunas. Lunasi dulu (tombol Settle / customer bayar via invoice) sebelum dipindah lagi.'
                    : "Hanya booking yang sudah lunas yang bisa dipindah jadwalnya. Status saat ini: {$booking->status}.");
            }

            $durationHours = (int) $booking->start_time->diffInHours($booking->end_time);
            if ($durationHours < 1) {
                $durationHours = 1;
            }

            $scheduleBefore = ($booking->court?->name ?? '-').', '.$booking->booking_date->format('d M Y').' '
                .$booking->start_time->format('H:i').'-'.$booking->end_time->format('H:i');
            $oldCourtId = $booking->court_id;
            $oldDateStr = $booking->booking_date->format('Y-m-d');
            $oldStart = $booking->start_time->copy();
            $oldEnd = $booking->end_time->copy();

            $newCourt = PadelCourt::where('id', $newCourtId)->lockForUpdate()->firstOrFail();
            $dateStr = $parsedDate->format('Y-m-d');

            $newStartDt = Carbon::parse("{$dateStr} {$newStartTimeStr}", $timezone);
            $newEndDt = $newStartDt->copy()->addHours($durationHours);

            // Aturan jam lewat sama dengan grid booking (jam berjalan masih boleh).
            if ($newStartDt->copy()->startOfHour()->lt(Carbon::now($timezone)->startOfHour())) {
                throw new HttpException(422, "Jam {$newStartDt->format('H:i')} sudah lewat. Pilih jam lain.");
            }

            $courtOpen = (int) substr($newCourt->open_time ?: '06:00', 0, 2);
            $courtCloseVal = $newCourt->close_time ?: '23:00';
            $courtClose = ($courtCloseVal === '00:00' || $courtCloseVal === '24:00') ? 24 : (int) substr($courtCloseVal, 0, 2);
            $newEndHour = $newEndDt->isSameDay($newStartDt) ? (int) $newEndDt->format('H') : 24;
            if ((int) $newStartDt->format('H') < $courtOpen || $newEndHour > $courtClose) {
                throw new HttpException(422, "Jadwal baru di luar jam operasional {$newCourt->name} ({$newCourt->open_time} - {$newCourt->close_time} WIB).");
            }

            // Contiguous Check: Pastikan tidak ada tabrakan di jadwal baru
            $hasConflict = PadelBooking::where('court_id', $newCourt->id)
                ->whereDate('booking_date', $dateStr)
                ->whereIn('status', ['LOCKED', 'PENDING_PAYMENT', 'PENDING', 'PAID', 'CHECKED_IN'])
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

            // Hitung selisih dengan rumus yang SAMA dengan preview di modal (benefit member/sponsor ikut pindah).
            $order = $this->ensureBookingOrder($booking);
            $quote = $this->quoteReschedule($booking, $newCourt, $newStartDt, $newEndDt, $this->rescheduleMembershipTimeWindow($booking));

            if ($quote['blocked_reason']) {
                throw new HttpException(422, $quote['blocked_reason']);
            }

            // Cek kunci slot di cache dulu (hanya membaca) — bentrok slot dilaporkan sebelum validasi
            // pembayaran. Kunci milik jadwal lama booking ini sendiri tidak dihitung sebagai bentrok.
            for ($cursor = $newStartDt->copy(); $cursor->lt($newEndDt); $cursor->addHour()) {
                $holder = Cache::get("padel_lock:{$newCourt->id}:{$dateStr}:".$cursor->format('Hi'));
                $isOwnOldSlot = $newCourt->id === $oldCourtId && $dateStr === $oldDateStr
                    && $cursor->gte($oldStart) && $cursor->lt($oldEnd);
                if ($holder !== null && $holder !== $booking->id && ! $isOwnOldSlot) {
                    throw new SlotConflictException(
                        "Slot {$newCourt->name} jam {$cursor->format('H:i')} sedang dikunci transaksi lain.",
                        $newCourt->name,
                        $cursor->format('H:i')
                    );
                }
            }

            $mustPay = $quote['total_charge'] > 0;
            $payNow = $mustPay && $isDeltaPaid;
            $proofPayload = [];

            // Semua validasi pembayaran SEBELUM menyentuh kunci slot di cache — cache tidak ikut di-rollback
            // transaksi DB, jadi validasi yang gagal belakangan akan meninggalkan kunci nyangkut.
            if ($payNow) {
                if (! \App\Models\Pos\PosCashierShift::getActiveShift('PADEL_FRONTDESK')) {
                    // Berlaku juga untuk super_admin: uang yang masuk tanpa shift tidak pernah ikut rekap
                    // setoran tutup shift, jadi tidak ada yang bisa mencocokkan apakah uangnya benar ada.
                    throw new HttpException(422, 'Tidak ada shift kasir yang aktif untuk loket [PADEL_FRONTDESK]. Silakan buka shift terlebih dahulu.');
                }
                $proofPayload = \App\Services\Pos\PosPaymentProof::validate((string) $paymentMethod, $paymentProof, $quote['total_charge']);
            }

            // Kunci cache: lepas kunci jadwal lama DULU (jadwal baru boleh beririsan dengan jadwal lama
            // booking ini sendiri), baru ambil kunci jadwal baru secara atomik (anti-TOCTOU).
            for ($cursor = $oldStart->copy(); $cursor->lt($oldEnd); $cursor->addHour()) {
                Cache::forget("padel_lock:{$oldCourtId}:{$oldDateStr}:".$cursor->format('Hi'));
            }

            $acquiredLocks = [];
            for ($cursor = $newStartDt->copy(); $cursor->lt($newEndDt); $cursor->addHour()) {
                $lockKey = "padel_lock:{$newCourt->id}:{$dateStr}:".$cursor->format('Hi');
                if (! Cache::add($lockKey, $booking->id, 86400) && Cache::get($lockKey) !== $booking->id) {
                    foreach ($acquiredLocks as $key) {
                        Cache::forget($key);
                    }
                    throw new SlotConflictException(
                        "Slot {$newCourt->name} jam {$cursor->format('H:i')} sedang dikunci transaksi lain.",
                        $newCourt->name,
                        $cursor->format('H:i')
                    );
                }
                $acquiredLocks[] = $lockKey;
            }

            try {
                $targetStatus = 'PAID';
                $newQrCodeHash = hash_hmac('sha256', $booking->booking_code.$booking->user_id.$newCourt->id.$newStartDt->toISOString(), config('app.key'));
                $deltaPayload = [
                    'type' => 'RESCHEDULE_PRICE_DELTA',
                    'booking_id' => $booking->id,
                    'admin_id' => $adminUser->id,
                    'reason' => $reason,
                    'schedule_before' => $scheduleBefore,
                    'court_delta' => $quote['court_delta'],
                    'tax_delta' => $quote['tax'],
                    'admin_fee_delta' => $quote['admin_fee'],
                    'total_delta' => $quote['total_charge'],
                ];

                if ($mustPay) {
                    // Kurang bayar: tarif & benefit mengikuti jadwal baru, tagihan selisih masuk ke order yang sama.
                    $booking->court_fee = $quote['net_new'];
                    $booking->member_discount_court = $quote['member_discount_new'];
                    if ($booking->sponsor_discount_court !== null || $quote['sponsor_discount_new'] > 0) {
                        $booking->sponsor_discount_court = $quote['sponsor_discount_new'];
                    }
                    $booking->total_amount = (float) $booking->total_amount + $quote['total_charge'];
                    $booking->reschedule_forfeited_amount = 0;

                    $order->update([
                        'subtotal' => (float) $order->subtotal + $quote['court_delta'],
                        'tax_amount' => (float) $order->tax_amount + $quote['tax'],
                        'service_charge' => (float) $order->service_charge + $quote['admin_fee'],
                        'grand_total' => (float) $order->grand_total + $quote['total_charge'],
                    ]);

                    if ($payNow) {
                        app(\App\Services\Payment\PaymentOrchestratorService::class)->markOrderAsPaid($order, [
                            'payment_gateway' => 'CASHIER_POS',
                            'counter' => 'PADEL_FRONTDESK',
                            'payment_method' => strtoupper((string) $paymentMethod),
                            'amount' => $quote['total_charge'],
                            'transaction_id' => 'SUPP-'.strtoupper(Str::random(12)),
                            'admin_user' => $adminUser,
                            'payload_log' => $deltaPayload + $proofPayload,
                        ]);
                    } else {
                        // Tagihan dikirim ke customer: QR ditahan sampai lunas (bayar via Midtrans di halaman
                        // invoice, atau di kasir lewat tombol Settle).
                        $targetStatus = 'LOCKED';
                        $newQrCodeHash = null;

                        Payment::create([
                            'order_id' => $order->id,
                            'payment_gateway' => 'CASHIER_POS',
                            'transaction_id' => 'SUPP-'.strtoupper(Str::random(12)),
                            'amount' => $quote['total_charge'],
                            'payment_method' => 'MENUNGGU_PEMBAYARAN',
                            'status' => 'PENDING',
                            'payload_log' => $deltaPayload,
                        ]);
                    }
                } elseif ($quote['forfeited'] > 0) {
                    // Lebih bayar (jadwal baru lebih murah): kebijakan PM — selisih HANGUS, tidak dikembalikan.
                    // court_fee & benefit TETAP nominal yang sudah dibayar supaya pembukuan order tidak berubah;
                    // nominal hangusnya dicatat terpisah agar transparan di invoice & laporan.
                    $booking->reschedule_forfeited_amount = $quote['forfeited'];
                } else {
                    $booking->court_fee = $quote['net_new'];
                    $booking->member_discount_court = $quote['member_discount_new'];
                    if ($booking->sponsor_discount_court !== null || $quote['sponsor_discount_new'] > 0) {
                        $booking->sponsor_discount_court = $quote['sponsor_discount_new'];
                    }
                    $booking->reschedule_forfeited_amount = 0;
                }

                // Flat Equipment Invariant: Tabel padel_booking_equipments terikat ke order_id, tidak perlu disentuh.
                $booking->court_id = $newCourt->id;
                $booking->booking_date = $dateStr;
                $booking->start_time = $newStartDt;
                $booking->end_time = $newEndDt;
                $booking->status = $targetStatus;
                $booking->qr_code_hash = $newQrCodeHash;
                $booking->reschedule_count = $booking->reschedule_count + 1;
                $booking->cancel_reason = "Reschedule: {$reason} (Admin: {$adminUser->name})";
                $booking->save();
            } catch (\Throwable $e) {
                foreach ($acquiredLocks as $key) {
                    Cache::forget($key);
                }
                throw $e;
            }

            $scheduleAfter = $newCourt->name.', '.$newStartDt->format('d M Y H:i').'-'.$newEndDt->format('H:i');
            $rupiah = fn (float $v) => \App\Services\Audit\ActivityLogger::rupiah($v);
            $outcome = match (true) {
                $payNow => 'selisih '.$rupiah($quote['total_charge']).' dibayar di frontdesk ('.strtoupper((string) $paymentMethod).')',
                $mustPay => 'tagihan selisih '.$rupiah($quote['total_charge']).' dikirim ke customer (QR ditahan)',
                $quote['forfeited'] > 0 => 'jadwal lebih murah, selisih '.$rupiah($quote['forfeited']).' HANGUS',
                default => 'tanpa selisih',
            };

            \App\Services\Audit\ActivityLogger::record(
                module: 'PADEL',
                event: 'booking.rescheduled',
                description: "Memindahkan jadwal booking {$booking->booking_code}: {$scheduleBefore} -> {$scheduleAfter} | {$outcome}",
                subject: $booking,
                meta: array_filter([
                    'kode_booking' => $booking->booking_code,
                    'jadwal_lama' => $scheduleBefore,
                    'jadwal_baru' => $scheduleAfter,
                    'tarif_normal_jadwal_baru' => $quote['gross_new'],
                    'benefit_member_sponsor_ikut_pindah' => $quote['member_discount_new'] + $quote['sponsor_discount_new'] ?: null,
                    'selisih_tarif_lapangan' => $quote['court_delta'],
                    'total_tagihan_selisih' => $quote['total_charge'] ?: null,
                    'selisih_hangus' => $quote['forfeited'] ?: null,
                    'status_setelahnya' => $targetStatus,
                    'alasan' => $reason,
                ], fn ($v) => $v !== null && $v !== ''),
                severity: \App\Services\Audit\ActivityLogger::WARNING,
                changes: [
                    'jadwal' => ['old' => $scheduleBefore, 'new' => $scheduleAfter],
                    'court_fee' => ['old' => $quote['paid_court'], 'new' => (float) $booking->court_fee],
                ],
                causer: $adminUser,
            );

            return [
                'success' => true,
                'message' => 'Jadwal booking berhasil dipindahkan.',
                'booking' => $booking->fresh(['court', 'order']),
                'delta' => $quote['court_delta'],
                'total_charge' => $quote['total_charge'],
                'forfeited' => $quote['forfeited'],
                'is_locked' => $targetStatus === 'LOCKED',
            ];
        });
    }

    /**
     * Pelunasan tagihan selisih (Supplemental Payment) oleh kasir.
     * Mengubah status LOCKED menjadi PAID dan merilis QR tiket baru.
     */
    public function adminSettleSupplementalPayment(string $bookingId, string $paymentMethod, User $adminUser, array $paymentProof = []): array
    {
        return $this->adminSettleCashierPayment($bookingId, $paymentMethod, 0, $adminUser, $paymentProof);
    }

    /**
     * Pelunasan pembayaran oleh kasir/admin di meja frontdesk (Cashier Settle Module).
     * Dapat melunasi booking berstatus PENDING_PAYMENT maupun tagihan sisa LOCKED.
     *
     * Nominal yang dilunasi SELALU sisa tagihan sebenarnya (tagihan PENDING, atau grand_total dikurangi
     * yang sudah dibayar) — dulu kalau nominal tidak diisi, sistem mencatat seluruh grand_total order
     * sebagai uang masuk walau yang ditagih cuma selisih reschedule (omzet tercatat berlipat).
     */
    public function adminSettleCashierPayment(
        string $bookingId,
        string $paymentMethod,
        float $amountReceived,
        User $cashierUser,
        array $paymentProof = [],
    ): array {
        // 100% Cashless: pembayaran tunai tidak diperbolehkan sama sekali untuk pelunasan kasir.
        if (in_array(strtoupper($paymentMethod), ['CASH', 'TUNAI'])) {
            throw new HttpException(422, 'Pembayaran tunai (CASH) tidak diperbolehkan. Venue Club 61 beroperasi 100% Cashless.');
        }

        return DB::transaction(function () use ($bookingId, $paymentMethod, $amountReceived, $cashierUser, $paymentProof) {
            $booking = PadelBooking::with(['order', 'court', 'user'])
                ->where('id', $bookingId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($booking->status, ['PENDING_PAYMENT', 'LOCKED', 'PENDING'])) {
                throw new HttpException(400, "Booking dengan status {$booking->status} tidak dapat dilunasi via kasir.");
            }

            if (! \App\Models\Pos\PosCashierShift::getActiveShift('PADEL_FRONTDESK')) {
                throw new HttpException(422, 'Tidak ada shift kasir yang aktif untuk loket [PADEL_FRONTDESK]. Silakan buka shift terlebih dahulu.');
            }

            $order = $this->ensureBookingOrder($booking);

            // Customer pernah membuka pembayaran Midtrans untuk tagihan ini? Tanya Midtrans DULU. Tanpa ini,
            // kasir bisa menerima uang lagi padahal customer sudah membayar online (terjadi: tagihan selisih
            // Rp 309.000 lunas di Midtrans 16:36, lalu di-settle EDC di kasir 16:39 = customer bayar dua kali).
            $reconciler = app(\App\Services\Payment\MidtransReconciliationService::class);
            if ($reconciler->hasOnlinePaymentAttempt($order)) {
                $online = $reconciler->reconcileOrder($order);

                if ($online === \App\Services\Payment\MidtransReconciliationService::PAID) {
                    Cache::forget('kelola_pemesanan_tab_counts');

                    return [
                        'success' => true,
                        'settled_via' => 'MIDTRANS',
                        'message' => 'Customer SUDAH membayar tagihan ini via Midtrans — tiket otomatis aktif. JANGAN terima pembayaran lagi di kasir.',
                        'booking' => $booking->fresh(['court', 'order', 'user']),
                    ];
                }

                if ($online === \App\Services\Payment\MidtransReconciliationService::PENDING) {
                    throw new HttpException(409, 'Customer sedang membayar tagihan ini lewat Midtrans (menunggu pembayaran). Minta customer menyelesaikan atau membatalkan pembayaran online-nya dulu, supaya tidak ditagih dua kali.');
                }

                if ($online === \App\Services\Payment\MidtransReconciliationService::ERROR) {
                    throw new HttpException(503, 'Status pembayaran online customer belum bisa dipastikan (Midtrans tidak merespons). Coba lagi sebentar — jangan terima pembayaran dulu supaya tidak dobel.');
                }
            }

            $pendingPayment = Payment::where('order_id', $order->id)->where('status', 'PENDING')->latest()->first();
            $totalPaid = (float) Payment::where('order_id', $order->id)->where('status', 'SUCCESS')->sum('amount');
            $amountDue = $pendingPayment
                ? (float) $pendingPayment->amount
                : max(0.0, (float) ($order->grand_total ?: $booking->total_amount) - $totalPaid);

            if ($amountDue <= 0) {
                throw new HttpException(422, 'Tidak ada tagihan tersisa untuk booking ini.');
            }

            if ($amountReceived > 0 && abs($amountReceived - $amountDue) > 1) {
                throw new HttpException(422, 'Nominal pelunasan harus sama dengan sisa tagihan: Rp '.number_format($amountDue, 0, ',', '.').'.');
            }

            $proofPayload = \App\Services\Pos\PosPaymentProof::validate($paymentMethod, $paymentProof, $amountDue);

            $orchestrator = app(\App\Services\Payment\PaymentOrchestratorService::class);
            $orchestrator->markOrderAsPaid($order, [
                'payment_gateway' => 'CASHIER_POS',
                'counter' => 'PADEL_FRONTDESK',
                'payment_method' => strtoupper($paymentMethod),
                'amount' => $amountDue,
                'payload_log' => [
                    'settled_by' => $cashierUser->id,
                    'settled_by_name' => $cashierUser->name,
                    'settled_at' => now()->toIso8601String(),
                    'channel' => 'FRONTDESK_CASHIER',
                ] + $proofPayload,
            ]);

            // Bersihkan cache kuncian dan counter tab
            Cache::forget('kelola_pemesanan_tab_counts');

            return [
                'success' => true,
                'message' => 'Pelunasan kasir berhasil diverifikasi! E-Tiket QR telah aktif.',
                'booking' => $booking->fresh(['court', 'order', 'user']),
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
        // "Saldo deposit member" dulu jadi opsi, padahal fitur saldo tidak ada: refund tercatat PROCESSED
        // tapi uangnya tidak pernah sampai ke customer. Hanya metode yang benar-benar mengembalikan uang.
        if ($refundAmount > 0 && ! in_array(strtoupper($refundMethod), ['TRANSFER_MANUAL', 'TRANSFER_BANK', 'VOID_EDC', 'ORIGINAL_PAYMENT'], true)) {
            throw new HttpException(422, 'Metode pengembalian dana tidak dikenali. Pilih Transfer Bank Manual atau Void / Refund di Mesin EDC.');
        }

        if ($refundAmount < 0) {
            throw new HttpException(422, 'Nominal refund tidak boleh negatif.');
        }

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

                // Batas refund = uang yang BENAR-BENAR sudah masuk dikurangi refund sebelumnya — bukan
                // grand_total (yang ikut menghitung tagihan selisih reschedule yang belum dibayar).
                $totalPaid = (float) Payment::where('order_id', $order->id)->where('status', 'SUCCESS')->sum('amount');
                $alreadyRefunded = (float) Refund::where('order_id', $order->id)->whereIn('status', ['PENDING', 'APPROVED', 'PROCESSED'])->sum('refund_amount');
                $maxRefund = $totalPaid > 0
                    ? max(0.0, $totalPaid - $alreadyRefunded)
                    : (float) ($order->grand_total ?: $booking->total_amount); // data lama tanpa catatan pembayaran
                if ($refundAmount > $maxRefund) {
                    throw new HttpException(422, 'Nominal refund (Rp ' . number_format($refundAmount, 0, ',', '.') . ') tidak boleh melebihi total pembayaran pesanan (Rp ' . number_format($maxRefund, 0, ',', '.') . ').');
                }

                $origPayment = Payment::where('order_id', $order->id)
                    ->where('status', 'SUCCESS')
                    ->latest()
                    ->first();

                if (! $origPayment) {
                    // Fallback pembukuan: booking ini sudah berstatus PAID tapi tidak ada payment record
                    // asli (data legacy). Dicatat sebagai TRANSFER_MANUAL, bukan CASH — venue 100% Cashless.
                    $origPayment = Payment::create([
                        'order_id' => $order->id,
                        'payment_gateway' => 'TRANSFER_MANUAL',
                        'transaction_id' => 'INIT-' . strtoupper(Str::random(10)),
                        'amount' => (float) $booking->total_amount,
                        'payment_method' => 'TRANSFER_MANUAL',
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

            if ($booking->membership_balance_id && (float) $booking->member_hours_consumed > 0) {
                app(\App\Services\Membership\MembershipBalanceService::class)->adjustQuota(
                    balanceId: $booking->membership_balance_id,
                    changeType: 'REVERSAL',
                    quantity: (float) $booking->member_hours_consumed,
                    notes: "Reversal pembatalan booking Padel {$booking->booking_code}: [{$reasonCategory}] {$notes}",
                    relatedType: PadelBooking::class,
                    relatedId: $booking->id,
                    performedBy: $adminUser->id
                );
                $booking->member_hours_consumed = 0.00;
            }

            if ($booking->sponsor_member_voucher_id && (float) $booking->sponsor_hours_consumed > 0) {
                $sponsorVoucher = \App\Models\Sponsor\SponsorMemberVoucher::where('id', $booking->sponsor_member_voucher_id)
                    ->lockForUpdate()
                    ->first();
                if ($sponsorVoucher) {
                    $sponsorVoucher->hours_used = max(0, (float) $sponsorVoucher->hours_used - (float) $booking->sponsor_hours_consumed);
                    $sponsorVoucher->save();
                }
                $booking->sponsor_hours_consumed = 0.00;
            }

            $booking->update([
                'status' => $newStatus,
                'qr_code_hash' => null,
                'cancel_reason' => "[{$reasonCategory}] {$notes} (Diproses oleh: {$adminUser->name})",
            ]);

            // Tagihan selisih reschedule yang belum dibayar ikut ditutup — kalau tidak, customer masih bisa
            // membayarnya lewat invoice untuk booking yang sudah dibatalkan.
            if ($booking->order_id) {
                foreach (Payment::where('order_id', $booking->order_id)->where('status', 'PENDING')->get() as $pending) {
                    $log = is_array($pending->payload_log) ? $pending->payload_log : [];
                    if (($log['booking_id'] ?? $booking->id) !== $booking->id) {
                        continue; // tagihan milik booking lain dalam order yang sama
                    }
                    $log['closed_by'] = 'BOOKING_CANCELLED_BY_ADMIN';
                    $pending->update(['status' => 'FAILED', 'payload_log' => $log]);
                }
            }

            \App\Services\Audit\ActivityLogger::record(
                module: 'PADEL',
                event: $refundAmount > 0 ? 'booking.refunded' : 'booking.cancelled',
                description: $refundAmount > 0
                    ? 'Refund '.\App\Services\Audit\ActivityLogger::rupiah($refundAmount)." untuk booking {$booking->booking_code} via {$refundMethod}"
                    : "Membatalkan booking {$booking->booking_code} tanpa refund",
                subject: $booking,
                meta: array_filter([
                    'kode_booking' => $booking->booking_code,
                    'no_order' => $booking->order?->order_number,
                    'lapangan' => $booking->court?->name,
                    'jadwal' => $booking->booking_date->format('d M Y').' '.$booking->start_time->format('H:i').'-'.$booking->end_time->format('H:i'),
                    'nominal_refund' => $refundAmount > 0 ? $refundAmount : null,
                    'metode_refund' => $refundAmount > 0 ? $refundMethod : null,
                    'kategori_alasan' => $reasonCategory,
                    'catatan' => $notes,
                ], fn ($v) => $v !== null && $v !== ''),
                severity: \App\Services\Audit\ActivityLogger::CRITICAL,
                causer: $adminUser,
            );

            return [
                'success' => true,
                'message' => 'Reservasi berhasil dibatalkan dan diproses refund.',
                'booking' => $booking->fresh(['court', 'order']),
            ];
        });
    }
}
