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

        // Jam operasional: open_time sampai close_time (batas start adalah closeHour - durasi)
        for ($startHour = $openHour; $startHour <= ($closeHour - $durationHours); $startHour++) {
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

                $totalDelta = $delta;
                if ($delta > 0) {
                    $deltaCalc = app(\App\Services\Finance\TaxAndFeeService::class)->calculate(
                        subtotal: $delta,
                        discountAmount: 0,
                        channel: 'POS_WALKIN',
                        module: 'PADEL'
                    );
                    $totalDelta = $deltaCalc['grand_total'];
                }

                $availableSlots[] = [
                    'start_time' => $startStr,
                    'end_time' => $endStr,
                    'duration_hours' => $durationHours,
                    'label' => "{$startStr} - {$endStr} WIB ({$durationHours} Jam)" . ($hasPrime ? ' [Prime Time]' : ' [Reguler]'),
                    'estimated_fee' => $estimatedFee,
                    'delta' => $delta,
                    'total_delta' => $totalDelta,
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
        ?string $paymentMethod = 'QRIS',
        bool $isDeltaPaid = true,
        string $timezone = 'Asia/Jakarta'
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

            // Distributed Cache Lock check on new slots with atomic Cache::add (Anti-TOCTOU)
            $currCheck = $newStartDt->copy();
            $acquiredLocks = [];
            while ($currCheck->lt($newEndDt)) {
                $lockKey = "padel_lock:{$newCourt->id}:{$dateStr}:" . $currCheck->format('Hi');
                $locked = Cache::add($lockKey, $booking->user_id, 86400);
                if (! $locked) {
                    foreach ($acquiredLocks as $k) {
                        Cache::forget($k);
                    }
                    throw new SlotConflictException(
                        "Slot {$newCourt->name} jam {$currCheck->format('H:i')} sedang dikunci transaksi lain.",
                        $newCourt->name,
                        $currCheck->format('H:i')
                    );
                }
                $acquiredLocks[] = $lockKey;
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

            // Eksekusi Finansial Berdasarkan Price Delta Terpusat via TaxAndFeeService
            if ($delta > 0) {
                // Kurang bayar (Reguler -> Prime): Hitung via TaxAndFeeService
                $deltaCalc = app(\App\Services\Finance\TaxAndFeeService::class)->calculate(
                    subtotal: $delta,
                    discountAmount: 0,
                    channel: 'POS_WALKIN',
                    module: 'PADEL'
                );
                $taxDelta = $deltaCalc['tax_amount'];
                $adminFeeDelta = $deltaCalc['admin_fee_amount'];
                $totalDeltaToPay = $deltaCalc['grand_total'];

                $booking->court_fee = $newCourtFee;
                $booking->total_amount += $totalDeltaToPay;

                if ($booking->order) {
                    $booking->order->update([
                        'subtotal' => $booking->order->subtotal + $deltaCalc['subtotal'],
                        'tax_amount' => $booking->order->tax_amount + $taxDelta,
                        'service_charge' => $booking->order->service_charge + $adminFeeDelta,
                        'grand_total' => $booking->order->grand_total + $totalDeltaToPay,
                    ]);
                }

                if ($isDeltaPaid) {
                    // Eksekusi pelunasan via PaymentOrchestratorService: wajib validasi shift aktif & ikat pos_shift_id
                    $orchestrator = app(\App\Services\Payment\PaymentOrchestratorService::class);
                    $orchestrator->markOrderAsPaid($booking->order, [
                        'payment_gateway' => 'CASHIER_POS',
                        'counter' => 'PADEL_FRONTDESK',
                        'payment_method' => strtoupper($paymentMethod ?? 'QRIS'),
                        'amount' => (float) $totalDeltaToPay,
                        'transaction_id' => 'SUPP-' . strtoupper(Str::random(12)),
                        'admin_user' => $adminUser,
                        'payload_log' => [
                            'type' => 'RESCHEDULE_PRICE_DELTA',
                            'booking_id' => $booking->id,
                            'admin_id' => $adminUser->id,
                            'reason' => $reason,
                            'court_delta' => $delta,
                            'tax_delta' => $taxDelta,
                            'admin_fee_delta' => $adminFeeDelta,
                            'total_delta' => $totalDeltaToPay,
                        ],
                    ]);
                } else {
                    // Jika belum dibayar kasir, status LOCKED dan QR code ditahan
                    $targetStatus = 'LOCKED';
                    $newQrCodeHash = null;

                    Payment::create([
                        'order_id' => $order->id,
                        'payment_gateway' => 'CASHIER_POS',
                        'transaction_id' => 'SUPP-' . strtoupper(Str::random(12)),
                        'amount' => $totalDeltaToPay,
                        'payment_method' => strtoupper($paymentMethod ?? 'QRIS'),
                        'status' => 'PENDING',
                        'payload_log' => [
                            'type' => 'RESCHEDULE_PRICE_DELTA',
                            'booking_id' => $booking->id,
                            'admin_id' => $adminUser->id,
                            'reason' => $reason,
                            'court_delta' => $delta,
                            'tax_delta' => $taxDelta,
                            'admin_fee_delta' => $adminFeeDelta,
                            'total_delta' => $totalDeltaToPay,
                        ],
                    ]);
                }
            } elseif ($delta < 0) {
                // Lebih bayar (Prime -> Reguler): Hitung refund proporsional via TaxAndFeeService
                $refundCalc = app(\App\Services\Finance\TaxAndFeeService::class)->calculate(
                    subtotal: abs($delta),
                    discountAmount: 0,
                    channel: 'POS_WALKIN',
                    module: 'PADEL'
                );
                $totalRefundToDeposit = $refundCalc['subtotal'] + $refundCalc['tax_amount'];

                $origPayment = Payment::where('order_id', $booking->order_id)
                    ->where('status', 'SUCCESS')
                    ->latest()
                    ->first();

                Refund::create([
                    'order_id' => $booking->order_id,
                    'payment_id' => $origPayment?->id ?? Payment::where('order_id', $booking->order_id)->first()?->id,
                    'refund_amount' => $totalRefundToDeposit,
                    'reason' => "[DEPOSIT_MEMBER] Selisih reschedule booking {$booking->booking_code} ke jam Reguler",
                    'status' => 'PROCESSED',
                    'processed_at' => now(),
                ]);

                $booking->court_fee = $newCourtFee;
                $booking->total_amount -= $totalRefundToDeposit;

                if ($booking->order) {
                    $booking->order->update([
                        'subtotal' => max(0, $booking->order->subtotal - $refundCalc['subtotal']),
                        'tax_amount' => max(0, $booking->order->tax_amount - $refundCalc['tax_amount']),
                        'grand_total' => max(0, $booking->order->grand_total - $totalRefundToDeposit),
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

            // Pasang distributed cache lock baru untuk jadwal baru (TTL 24 jam / 86.400 detik)
            $currNew = $newStartDt->copy();
            while ($currNew->lt($newEndDt)) {
                Cache::put("padel_lock:{$newCourt->id}:{$dateStr}:" . $currNew->format('Hi'), $booking->user_id, 86400);
                $currNew->addHour();
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
        return $this->adminSettleCashierPayment($bookingId, $paymentMethod, 0, $adminUser);
    }

    /**
     * Pelunasan pembayaran oleh kasir/admin di meja frontdesk (Cashier Settle Module).
     * Dapat melunasi booking berstatus PENDING_PAYMENT maupun tagihan sisa LOCKED.
     */
    public function adminSettleCashierPayment(
        string $bookingId,
        string $paymentMethod,
        float $amountReceived,
        User $cashierUser
    ): array {
        // 100% Cashless: pembayaran tunai tidak diperbolehkan sama sekali untuk pelunasan kasir.
        if (in_array(strtoupper($paymentMethod), ['CASH', 'TUNAI'])) {
            throw new HttpException(422, 'Pembayaran tunai (CASH) tidak diperbolehkan. Venue Club 61 beroperasi 100% Cashless.');
        }

        return DB::transaction(function () use ($bookingId, $paymentMethod, $amountReceived, $cashierUser) {
            $booking = PadelBooking::with(['order', 'court', 'user'])
                ->where('id', $bookingId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($booking->status, ['PENDING_PAYMENT', 'LOCKED', 'PENDING'])) {
                throw new HttpException(400, "Booking dengan status {$booking->status} tidak dapat dilunasi via kasir.");
            }

            $order = $this->ensureBookingOrder($booking);

            $gateway = strtoupper($paymentMethod);
            $settleAmount = $amountReceived > 0
                ? $amountReceived 
                : (float) ($order->grand_total ?: $booking->total_amount);

            $orchestrator = app(\App\Services\Payment\PaymentOrchestratorService::class);
            $orchestrator->markOrderAsPaid($order, [
                'payment_gateway' => 'CASHIER_POS',
                'counter' => 'PADEL_FRONTDESK',
                'payment_method' => $gateway,
                'amount' => $settleAmount,
                'payload_log' => [
                    'settled_by' => $cashierUser->id,
                    'settled_by_name' => $cashierUser->name,
                    'settled_at' => now()->toIso8601String(),
                    'channel' => 'FRONTDESK_CASHIER',
                ],
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

                $maxRefund = (float) ($order->grand_total ?: $booking->total_amount);
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
}
