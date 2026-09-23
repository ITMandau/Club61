<?php

namespace App\Services\Padel\Concerns;

use App\Models\Padel\CourtEquipment;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelBookingEquipment;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait ManagesCheckoutAndPayments
{
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
        User $user,
        ?string $membershipBalanceId = null
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

        return DB::transaction(function () use ($bookings, $equipments, $voucherCode, $paymentMethod, $user, $cacheIdempotencyKey, $membershipBalanceId) {
            // Terapkan kuota atau diskon membership jika ada
            $this->applyMembershipBenefitToCourtBookings($bookings, $user, $membershipBalanceId);

            $courtTotal = (float) $bookings->sum('court_fee');
            $equipmentTotal = 0;
            $equipmentItems = [];
            $orderNumber = 'ORD-PAD-' . strtoupper(Str::random(10));
            $isStaff = $user->isStaff();
            $isCash = strtoupper($paymentMethod) === 'CASH';

            // Proses Sewa Peralatan (Raket, Bola, Handuk)
            if (! empty($equipments)) {
                $primaryBooking = $bookings->first();

                foreach ($equipments as $item) {
                    // Lock row equipment SEBELUM baca stock_quantity — anti-race kalau 2 checkout
                    // bersamaan rebutan sisa stok BALL yang sama.
                    $eq = CourtEquipment::where('id', $item['equipment_id'])->lockForUpdate()->first();
                    if (! $eq || ! $eq->is_active) {
                        continue;
                    }

                    $qty = max(1, (int)$item['quantity']);

                    // BALL = consumable (dibeli habis, bukan disewa) -> stok dipotong SEKARANG saat checkout.
                    // RACKET/TOWEL = disewa (dipinjamkan fisik) -> stok baru dipotong nanti saat check-in
                    // (lihat ManagesCheckInAndTurnstile::checkIn()), dan bisa di-restock manual lewat retur alat.
                    $isConsumable = strtoupper($eq->type) === 'BALL';
                    if ($isConsumable) {
                        if ((int) $eq->stock_quantity < $qty) {
                            throw new HttpException(422, "Stok {$eq->name} tidak cukup (sisa {$eq->stock_quantity}, diminta {$qty}).");
                        }
                        $eq->decrement('stock_quantity', $qty);
                    }

                    $subtotal = $eq->rental_price * $qty;
                    $equipmentTotal += $subtotal;

                    $equipmentItems[] = [
                        'equipment_id' => $eq->id,
                        'name' => $eq->name,
                        'quantity' => $qty,
                        'unit_price' => (float)$eq->rental_price,
                        'subtotal' => (float)$subtotal,
                        'stock_deducted' => $isConsumable,
                    ];
                }

                $primaryBooking->increment('equipment_fee', $equipmentTotal);
                $primaryBooking->increment('total_amount', $equipmentTotal);
            }

            // Validasi Voucher Diskon berbasis Database
            $discountAmount = 0;
            $appliedVoucherCode = null;
            if ($voucherCode) {
                $code = strtoupper(trim($voucherCode));
                $voucher = \App\Models\Pos\Voucher::where('code', $code)
                    ->where('is_active', true)
                    ->where(function ($q) {
                        $q->whereNull('valid_until')->orWhere('valid_until', '>=', now());
                    })
                    ->first();

                if ($voucher) {
                    $orderAmount = $courtTotal + $equipmentTotal;
                    $minOrder = (float) ($voucher->min_order_amount ?? 0);
                    $hasQuota = ($voucher->quota === null || $voucher->quota > 0);

                    if ($orderAmount >= $minOrder && $hasQuota) {
                        if ($voucher->discount_type === 'PERCENT') {
                            $calc = $orderAmount * ((float) $voucher->discount_value / 100);
                            $discountAmount = $voucher->max_discount_amount ? min($calc, (float) $voucher->max_discount_amount) : $calc;
                        } else {
                            $discountAmount = (float) $voucher->discount_value;
                        }
                        $discountAmount = min($discountAmount, $orderAmount);
                        $appliedVoucherCode = $voucher->code;
                    }
                }
            }

            // Hitung Pajak & Biaya Admin via Mesin Terpusat TaxAndFeeService (Tunduk pada Menu Pengaturan Biaya & Pajak)
            $financeCalc = app(\App\Services\Finance\TaxAndFeeService::class)->calculate(
                subtotal: $courtTotal + $equipmentTotal,
                discountAmount: $discountAmount,
                channel: 'ONLINE',
                module: 'PADEL'
            );

            $taxAmount = $financeCalc['tax_amount'];
            $adminFee = $financeCalc['admin_fee_amount'];
            $totalServiceCharge = $adminFee;
            $grandTotal = max(0, $financeCalc['taxable_amount'] + $taxAmount + $totalServiceCharge);

            // Eager Order Creation
            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => $user->id,
                'cashier_id' => ($isStaff && $isCash) ? $user->id : null,
                'order_type' => 'ONLINE_BOOKING',
                'subtotal' => $financeCalc['subtotal'],
                'discount_amount' => $financeCalc['discount_amount'],
                'voucher_code' => $appliedVoucherCode,
                'tax_amount' => $taxAmount,
                'service_charge' => $totalServiceCharge,
                'grand_total' => $grandTotal,
                'payment_status' => 'UNPAID',
            ]);

            // Catat PadelBookingEquipment dengan foreign key order_id = order->id
            if (! empty($equipments)) {
                $primaryBooking = $bookings->first();
                foreach ($equipmentItems as $eqItem) {
                    PadelBookingEquipment::create([
                        'order_id' => $order->id,
                        'booking_id' => $primaryBooking->id,
                        'equipment_id' => $eqItem['equipment_id'],
                        'quantity' => $eqItem['quantity'],
                        'unit_price' => $eqItem['unit_price'],
                        'subtotal' => $eqItem['subtotal'],
                        'stock_deducted_at' => ! empty($eqItem['stock_deducted']) ? now() : null,
                    ]);
                }
            }

            // Catat order_items untuk slot lapangan dan peralatan sewa (semua item_type = 'PADEL')
            foreach ($bookings as $b) {
                $order->items()->create([
                    'item_type' => 'PADEL',
                    'reference_id' => $b->id,
                    'item_name' => 'Sewa ' . ($b->court ? $b->court->name : 'Court Padel'),
                    'quantity' => 1,
                    'unit_price' => $b->court_fee,
                    'subtotal' => $b->court_fee,
                ]);
            }

            foreach ($equipmentItems as $eqItem) {
                $order->items()->create([
                    'item_type' => 'PADEL',
                    'reference_id' => $eqItem['equipment_id'],
                    'item_name' => $eqItem['name'],
                    'quantity' => $eqItem['quantity'],
                    'unit_price' => $eqItem['unit_price'],
                    'subtotal' => $eqItem['subtotal'],
                ]);
            }

            // Susun Item Details Persis Sama dengan Gross Amount untuk Midtrans
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
            if ($taxAmount > 0) {
                $midtransItems[] = [
                    'id' => 'TAX-FEE',
                    'price' => (int) $taxAmount,
                    'quantity' => 1,
                    'name' => substr($financeCalc['tax_name'] ?: 'Pajak Daerah PB1', 0, 50),
                ];
            }
            if ($adminFee > 0) {
                $midtransItems[] = [
                    'id' => 'ADMIN-FEE',
                    'price' => (int) $adminFee,
                    'quantity' => 1,
                    'name' => substr($financeCalc['admin_fee_name'] ?: 'Biaya Layanan', 0, 50),
                ];
            }
            if ($discountAmount > 0) {
                $midtransItems[] = [
                    'id' => 'DISC-' . substr($appliedVoucherCode ?? 'PROMO', 0, 10),
                    'price' => -(int) $discountAmount,
                    'quantity' => 1,
                    'name' => 'Voucher Diskon',
                ];
            }

            // QA DEFENSE 3: Targeted 1-Rupiah Auto-Reconciliation
            $sumItems = 0;
            foreach ($midtransItems as $item) {
                $sumItems += (int) $item['price'] * (int) $item['quantity'];
            }
            $diff = (int) $grandTotal - $sumItems;
            if ($diff !== 0 && ! empty($midtransItems)) {
                $targetIdx = null;
                foreach ($midtransItems as $idx => $item) {
                    if ($item['id'] === 'TAX-FEE') {
                        $targetIdx = $idx;
                        break;
                    }
                }
                if ($targetIdx === null) {
                    foreach ($midtransItems as $idx => $item) {
                        if ($item['id'] === 'ADMIN-FEE') {
                            $targetIdx = $idx;
                            break;
                        }
                    }
                }
                if ($targetIdx === null) {
                    $targetIdx = count($midtransItems) - 1;
                }
                $midtransItems[$targetIdx]['price'] += $diff;
            }

            if ($grandTotal <= 0) {
                $paymentResult = [
                    'is_mock' => true,
                    'driver' => 'MEMBERSHIP_QUOTA',
                    'snap_token' => null,
                    'payment_url' => null,
                    'redirect_url' => null,
                    'checkout_mode' => 'NONE',
                    'raw' => ['covered_by' => 'MEMBERSHIP_QUOTA'],
                ];
                $initialStatus = 'PAID';
            } else {
                // Panggil Payment Manager (Midtrans & Mock Simulator)
                $paymentManager = app(\App\Services\Payment\PaymentManager::class);
                $paymentResult = $paymentManager->createPayment([
                    'order_id' => $orderNumber,
                    'gross_amount' => (int) $grandTotal,
                    'item_details' => $midtransItems,
                    'customer_details' => [
                        'first_name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone ?? '081261617233',
                    ],
                    'payment_method' => $paymentMethod,
                ]);

                // Tentukan status awal transaksi
                if ($isCash && ! $isStaff) {
                    // Customer checkout tunai dari web/mobile wajib PENDING_PAYMENT
                    $initialStatus = 'PENDING_PAYMENT';
                } else {
                    // Tunai hanya langsung PAID jika diproses oleh staf kasir di meja POS, atau gateway mock aktif (non-CASH)
                    $initialStatus = ($paymentResult['is_mock'] || ($isCash && $isStaff)) ? 'PAID' : 'PENDING_PAYMENT';
                }
            }

            // Petakan Order ID dan Order Number ke ID Bookings di Cache selama 24 Jam
            Cache::put("order_bookings:{$orderNumber}", $bookings->pluck('id')->toArray(), 86400);
            Cache::put("order_bookings:{$order->id}", $bookings->pluck('id')->toArray(), 86400);

            if ($appliedVoucherCode) {
                Cache::put("order_voucher:{$orderNumber}", $appliedVoucherCode, 86400);
                Cache::put("order_voucher:{$order->id}", $appliedVoucherCode, 86400);
            }

            // Update semua booking dengan order_id dan simpan hash tiket QR
            foreach ($bookings as $booking) {
                $booking->update([
                    'order_id' => $order->id,
                    'status' => $initialStatus,
                    'qr_code_hash' => $initialStatus === 'PAID'
                        ? ('VNT-TICKET-' . strtoupper(bin2hex(random_bytes(16))))
                        : null,
                ]);
            }

            $primaryBooking = $bookings->first();
            $isConfirmed = $initialStatus === 'PAID';

            // Sentralisasi pemenuhan via PaymentOrchestratorService
            $orchestrator = app(\App\Services\Payment\PaymentOrchestratorService::class);
            if ($isConfirmed) {
                $orchestrator->markOrderAsPaid($order, [
                    'payment_gateway' => $grandTotal <= 0 ? 'MEMBERSHIP_QUOTA' : ($isCash ? 'CASHIER_POS' : ($paymentResult['is_mock'] ? 'MOCK' : 'MIDTRANS')),
                    'counter' => 'PADEL_FRONTDESK',
                    'transaction_id' => $orderNumber,
                    'payment_method' => $grandTotal <= 0 ? 'MEMBERSHIP_QUOTA' : strtoupper($paymentMethod),
                    'amount' => (float) $grandTotal,
                    'user' => $user,
                    'payload_log' => $paymentResult['raw'] ?? null,
                ]);
            } else {
                // Simpan record pembayaran awal PENDING dengan transaction_id = orderNumber
                Payment::create([
                    'order_id' => $order->id,
                    'payment_gateway' => strtoupper($paymentMethod === 'CASH' ? 'CASH' : 'MIDTRANS'),
                    'transaction_id' => $orderNumber,
                    'snap_token' => $paymentResult['snap_token'] ?? null,
                    'payment_url' => $paymentResult['payment_url'] ?? $paymentResult['redirect_url'] ?? null,
                    'amount' => (float) $grandTotal,
                    'payment_method' => strtoupper($paymentMethod),
                    'status' => 'PENDING',
                    'payload_log' => $paymentResult['raw'] ?? null,
                ]);
            }

            $response = [
                'success' => true,
                'message' => $isConfirmed
                    ? 'Pembayaran berhasil dikonfirmasi. E-Tiket aktif.'
                    : ($isCash ? 'Reservasi berhasil dibuat. Silakan selesaikan pembayaran tunai di kasir venue.' : 'Sesi transaksi pembayaran berhasil dibuat. Silakan selesaikan pembayaran.'),
                'data' => [
                    'driver' => $paymentResult['driver'] ?? $paymentManager->getDefaultDriver(),
                    'order_id' => $orderNumber,
                    'booking_id' => $primaryBooking->id,
                    'snap_token' => $paymentResult['snap_token'] ?? null,
                    'payment_url' => $paymentResult['payment_url'] ?? $paymentResult['redirect_url'] ?? null,
                    'redirect_url' => $paymentResult['redirect_url'] ?? null,
                    'checkout_mode' => $paymentResult['checkout_mode'] ?? 'POPUP',
                    'is_mock' => $paymentResult['is_mock'],
                    'payment_method' => strtoupper($paymentMethod),
                    'payment_status' => $initialStatus,
                    'court_fee' => (float) $courtTotal,
                    'equipment_fee' => (float) $equipmentTotal,
                    'tax_amount' => (float) $taxAmount,
                    'admin_fee' => (float) $adminFee,
                    'gateway_fee' => (float) $totalServiceCharge,
                    'service_charge' => (float) $totalServiceCharge,
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
     * Regenerasi pembayaran untuk booking yang pending (Ganti Metode Bayar via On-the-Fly Suffix).
     */
    public function retryPayment(string $bookingId, string $paymentMethod, User $user): array
    {
        return DB::transaction(function () use ($bookingId, $paymentMethod, $user) {
            $order = Order::where('order_number', $bookingId)
                ->orWhere('id', $bookingId)
                ->first();
            $orderId = $order?->id;

            $booking = PadelBooking::with(['court', 'user', 'order'])
                ->where(function ($q) use ($bookingId, $orderId) {
                    $q->where('id', $bookingId)
                        ->orWhere('booking_code', $bookingId)
                        ->orWhere('order_id', $bookingId);
                    if ($orderId) {
                        $q->orWhere('order_id', $orderId);
                    }
                })
                ->lockForUpdate()
                ->firstOrFail();

            // Proteksi Otorisasi: Pastikan user hanya bisa retry booking miliknya sendiri
            $isStaff = $user->isStaff();
            if (! $isStaff && $booking->user_id !== $user->id) {
                throw new HttpException(403, 'Anda tidak memiliki otoritas untuk memproses pembayaran booking ini.');
            }

            if (! in_array($booking->status, ['PENDING_PAYMENT', 'LOCKED', 'PENDING'])) {
                throw new HttpException(400, "Booking dengan status {$booking->status} tidak dapat diproses ulang pembayarannya.");
            }

            $order = $this->ensureBookingOrder($booking);

            // Ambil seluruh booking yang tergabung dalam order yang sama
            $bookings = PadelBooking::with(['court', 'equipments.equipment'])
                ->where('order_id', $order->id)
                ->orWhere('order_id', $order->order_number)
                ->get();

            if ($bookings->isEmpty()) {
                $bookings = collect([$booking]);
            }

            // Deteksi apakah ini pelunasan tagihan sisa (Supplemental / Delta Reschedule)
            $successfulPayments = Payment::where('order_id', $order->id)
                ->where('status', 'SUCCESS')
                ->get();
            $totalPaid = (float) $successfulPayments->sum('amount');

            $pendingSupplementalPayment = Payment::where('order_id', $order->id)
                ->where('status', 'PENDING')
                ->latest()
                ->first();

            $isSupplementalDelta = ($totalPaid > 0 && $pendingSupplementalPayment);

            $isCash = strtoupper($paymentMethod) === 'CASH';

            if ($isSupplementalDelta) {
                // HANYA menagih nominal selisih (delta), bukan menagih ulang seluruh order
                $deltaAmount = (float) $pendingSupplementalPayment->amount;
                $chargeTotal = max(0, $deltaAmount);

                if ($isCash) {
                    $pendingSupplementalPayment->update([
                        'payment_gateway' => 'CASH',
                        'payment_method' => 'CASH',
                    ]);

                    return [
                        'success' => true,
                        'is_cash' => true,
                        'order_id' => $order->order_number,
                        'booking_code' => $booking->booking_code,
                        'grand_total' => $deltaAmount,
                        'payment_method' => 'CASH',
                        'message' => 'Metode pembayaran tagihan sisa diubah ke Tunai di Kasir. Silakan tunjukkan Kode Booking ke kasir venue Club61.',
                    ];
                }

                // Midtrans Snap untuk Pelunasan Delta
                $orderNumber = $order->order_number ?: ('ORD-PAD-' . strtoupper(Str::random(8)));
                $suffixedOrderId = $orderNumber . '_DELTA_' . time();

                $midtransItems = [
                    [
                        'id' => substr($pendingSupplementalPayment->transaction_id ?: 'DELTA-RESCHEDULE', 0, 50),
                        'price' => (int) $deltaAmount,
                        'quantity' => 1,
                        'name' => substr("Pelunasan Selisih Reschedule ({$booking->booking_code})", 0, 50),
                    ],
                ];
                if ($newGatewayFee > 0) {
                    $midtransItems[] = [
                        'id' => 'FEE-GATEWAY',
                        'price' => (int) $newGatewayFee,
                        'quantity' => 1,
                        'name' => 'Biaya Layanan Gerbang',
                    ];
                }

                $paymentManager = app(\App\Services\Payment\PaymentManager::class);
                $paymentResult = $paymentManager->createPayment([
                    'order_id' => $suffixedOrderId,
                    'gross_amount' => (int) $chargeTotal,
                    'item_details' => $midtransItems,
                    'customer_details' => [
                        'first_name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone ?? '081261617233',
                    ],
                    'payment_method' => $paymentMethod,
                ]);

                Cache::put("order_bookings:{$suffixedOrderId}", $bookings->pluck('id')->toArray(), 86400);

                $pendingSupplementalPayment->update([
                    'payment_gateway' => 'MIDTRANS',
                    'payment_method' => $paymentMethod,
                    'payload_log' => array_merge($pendingSupplementalPayment->payload_log ?? [], [
                        'midtrans_order_id' => $suffixedOrderId,
                        'snap_token' => $paymentResult['snap_token'] ?? null,
                        'gateway_fee' => $newGatewayFee,
                    ]),
                ]);

                return [
                    'success' => true,
                    'is_cash' => false,
                    'order_id' => $order->order_number,
                    'suffixed_order_id' => $suffixedOrderId,
                    'snap_token' => $paymentResult['snap_token'] ?? null,
                    'redirect_url' => $paymentResult['redirect_url'] ?? null,
                    'grand_total' => $chargeTotal,
                    'gateway_fee' => $newGatewayFee,
                    'payment_method' => $paymentMethod,
                    'message' => 'Token pembayaran tagihan sisa berhasil dibuat.',
                ];
            }

            // Normal Flow: Retry Pembayaran Penuh untuk Cart Baru
            $courtTotal = $bookings->sum('court_fee');
            $equipmentTotal = $bookings->sum('equipment_fee');
            $baseAmount = $courtTotal + $equipmentTotal;

            $retryFinanceCalc = app(\App\Services\Finance\TaxAndFeeService::class)->calculate(
                subtotal: $baseAmount,
                discountAmount: (float) ($order->discount_amount ?? 0),
                channel: 'ONLINE',
                module: 'PADEL'
            );
            $taxAmount = $retryFinanceCalc['tax_amount'];
            $adminFee = $retryFinanceCalc['admin_fee_amount'];
            $totalServiceCharge = $adminFee;
            $grandTotal = max(0, $retryFinanceCalc['taxable_amount'] + $taxAmount + $totalServiceCharge);

            $order->update([
                'subtotal' => $retryFinanceCalc['subtotal'],
                'tax_amount' => $taxAmount,
                'service_charge' => $totalServiceCharge,
                'grand_total' => $grandTotal,
            ]);

            if ($isCash) {
                return [
                    'success' => true,
                    'is_cash' => true,
                    'order_id' => $order->order_number,
                    'booking_code' => $booking->booking_code,
                    'grand_total' => $grandTotal,
                    'payment_method' => 'CASH',
                    'message' => 'Metode pembayaran diubah ke Tunai di Kasir. Silakan tunjukkan Kode Booking ke kasir venue Club61.',
                ];
            }

            // On-the-Fly Suffix Logic untuk Midtrans Snap
            $orderNumber = $order->order_number ?: ('ORD-PAD-' . strtoupper(Str::random(8)));
            $suffixedOrderId = $orderNumber . '_' . time();

            // Susun item details Midtrans yang presisi
            $midtransItems = [];
            foreach ($bookings as $b) {
                $midtransItems[] = [
                    'id' => substr($b->id, 0, 50),
                    'price' => (int) $b->court_fee,
                    'quantity' => 1,
                    'name' => substr('Sewa ' . ($b->court?->name ?? 'Lapangan'), 0, 50),
                ];
            }
            if ($equipmentTotal > 0) {
                $midtransItems[] = [
                    'id' => 'EQ-RENTAL',
                    'price' => (int) $equipmentTotal,
                    'quantity' => 1,
                    'name' => 'Sewa Peralatan',
                ];
            }
            if ($taxAmount > 0) {
                $midtransItems[] = [
                    'id' => 'TAX-FEE',
                    'price' => (int) $taxAmount,
                    'quantity' => 1,
                    'name' => substr($retryFinanceCalc['tax_name'] ?: 'Pajak Daerah PB1', 0, 50),
                ];
            }
            if ($adminFee > 0) {
                $midtransItems[] = [
                    'id' => 'ADMIN-FEE',
                    'price' => (int) $adminFee,
                    'quantity' => 1,
                    'name' => substr($retryFinanceCalc['admin_fee_name'] ?: 'Biaya Layanan', 0, 50),
                ];
            }

            // QA DEFENSE 3: Targeted 1-Rupiah Auto-Reconciliation
            $sumItems = 0;
            foreach ($midtransItems as $item) {
                $sumItems += (int) $item['price'] * (int) $item['quantity'];
            }
            $diff = (int) $grandTotal - $sumItems;
            if ($diff !== 0 && ! empty($midtransItems)) {
                $targetIdx = null;
                foreach ($midtransItems as $idx => $item) {
                    if ($item['id'] === 'TAX-FEE') {
                        $targetIdx = $idx;
                        break;
                    }
                }
                if ($targetIdx === null) {
                    foreach ($midtransItems as $idx => $item) {
                        if ($item['id'] === 'ADMIN-FEE') {
                            $targetIdx = $idx;
                            break;
                        }
                    }
                }
                if ($targetIdx === null) {
                    $targetIdx = count($midtransItems) - 1;
                }
                $midtransItems[$targetIdx]['price'] += $diff;
            }

            // Panggil Payment Manager
            $paymentManager = app(\App\Services\Payment\PaymentManager::class);
            $paymentResult = $paymentManager->createPayment([
                'order_id' => $suffixedOrderId,
                'gross_amount' => (int) $grandTotal,
                'item_details' => $midtransItems,
                'customer_details' => [
                    'first_name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone ?? '081261617233',
                ],
                'payment_method' => $paymentMethod,
            ]);

            // Petakan suffixed order id ke bookings di Cache selama 24 jam
            Cache::put("order_bookings:{$suffixedOrderId}", $bookings->pluck('id')->toArray(), 86400);

            return [
                'success' => true,
                'is_cash' => false,
                'order_id' => $order->order_number,
                'suffixed_order_id' => $suffixedOrderId,
                'snap_token' => $paymentResult['snap_token'] ?? null,
                'redirect_url' => $paymentResult['redirect_url'] ?? null,
                'grand_total' => $grandTotal,
                'service_charge' => $totalServiceCharge,
                'gateway_fee' => $totalServiceCharge,
                'payment_method' => $paymentMethod,
                'message' => 'Token pembayaran baru berhasil dibuat.',
            ];
        });
    }

    /**
     * Memformat label metode pembayaran agar ramah dibaca di invoice & e-tiket.
     */
    public function formatPaymentMethodLabel(?string $method, ?array $payload = null): string
    {
        if (!empty($payload['payment_type'])) {
            $pt = strtolower((string) $payload['payment_type']);
            if ($pt === 'bank_transfer' && !empty($payload['va_numbers'][0]['bank'])) {
                $bank = strtoupper($payload['va_numbers'][0]['bank']);
                return "{$bank} Virtual Account";
            }
            if ($pt === 'echannel') {
                return 'Mandiri Virtual Account';
            }
            if (in_array($pt, ['qris', 'gopay', 'shopeepay'])) {
                return 'QRIS Instan (GoPay/OVO/BCA)';
            }
        }

        return match (strtoupper((string) $method)) {
            'BCA_VA' => 'BCA Virtual Account',
            'MANDIRI_VA' => 'Mandiri Virtual Account',
            'BRI_VA' => 'BRI Virtual Account',
            'BNI_VA' => 'BNI Virtual Account',
            'CIMB_VA' => 'CIMB Virtual Account',
            'BSI_VA' => 'BSI Virtual Account',
            'QRIS' => 'QRIS Instan (GoPay/OVO/BCA)',
            'CASH' => 'Tunai di Kasir (CASH)',
            'DEBIT_CARD', 'DEBIT' => 'Kartu Debit (EDC)',
            'CREDIT_CARD', 'CREDIT' => 'Kartu Kredit (EDC)',
            'EDC_BCA' => 'Debit/Kartu EDC BCA',
            'EDC_MANDIRI' => 'Debit/Kartu EDC Mandiri',
            'QRIS_STATIS' => 'QRIS Kasir Frontdesk',
            'BANK_TRANSFER' => 'Transfer Bank (VA)',
            default => $method ?: 'QRIS Instan (GoPay/OVO/BCA)',
        };
    }

    /**
     * Normalisasi nomor telepon ke format lokal Indonesia (08...).
     */
    public function normalizePhoneNumber(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', trim($phone));
        if (str_starts_with($phone, '+62')) {
            $phone = '0' . substr($phone, 3);
        } elseif (str_starts_with($phone, '62')) {
            $phone = '0' . substr($phone, 2);
        }

        return $phone;
    }

    /**
     * Cari pelanggan walk-in berdasarkan nomor telepon atau buat akun baru otomatis (Smart Deduplication).
     */
    public function findOrCreateWalkInCustomer(string $name, string $phone, ?string $email = null): User
    {
        $cleanPhone = $this->normalizePhoneNumber($phone);
        $customer = User::where('phone', $cleanPhone)->first();

        if ($customer) {
            return $customer;
        }

        $ulid = strtolower((string) Str::ulid());
        $fallbackEmail = ! empty($email) ? trim($email) : "walkin-{$ulid}@walkin.club61.internal";

        if (User::where('email', $fallbackEmail)->exists()) {
            $fallbackEmail = "walkin-{$ulid}@walkin.club61.internal";
        }

        $rawPassword = strlen($cleanPhone) >= 6
            ? substr($cleanPhone, -6)
            : (empty($cleanPhone) ? '123456' : str_pad($cleanPhone, 6, '0', STR_PAD_LEFT));

        return User::create([
            'name' => trim($name),
            'phone' => $cleanPhone,
            'email' => $fallbackEmail,
            'password' => Hash::make($rawPassword),
            'role' => 'CUSTOMER',
            'registration_source' => 'WALK_IN',
            'is_active' => true,
        ]);
    }

    /**
     * Proses checkout dan pelunasan instan untuk reservasi walk-in kasir frontdesk.
     * Menerapkan pemisahan 3 lapis data (registration_source, order_type, payment_gateway)
     * dan direct POS settlement tanpa Snap Midtrans.
     */
    public function processWalkInCheckout(
        User $customer,
        array $slots,
        string $bookingDate,
        array $equipments,
        string $paymentMethod,
        User $cashier,
        bool $autoCheckIn = false,
        array $paymentMeta = [],
        ?string $membershipBalanceId = null
    ): array {
        // 1. Hold batch slots untuk customer (melempar SlotConflictException jika tabrakan)
        $holdResult = $this->holdBatchSlots($slots, $bookingDate, $customer);
        $bookingIds = collect($holdResult['bookings'])->pluck('id')->toArray();

        return DB::transaction(function () use ($customer, $bookingIds, $equipments, $paymentMethod, $cashier, $autoCheckIn, $paymentMeta, $membershipBalanceId) {
            $bookings = PadelBooking::with('court')
                ->whereIn('id', $bookingIds)
                ->where('user_id', $customer->id)
                ->where('status', 'LOCKED')
                ->get();

            if ($bookings->count() !== count($bookingIds)) {
                throw new HttpException(422, 'Satu atau lebih slot booking tidak valid atau masa kuncian telah kedaluwarsa.');
            }

            // Terapkan kuota atau diskon membership jika ada
            $this->applyMembershipBenefitToCourtBookings($bookings, $customer, $membershipBalanceId);

            $courtTotal = (float) $bookings->sum('court_fee');
            $equipmentTotal = 0;
            $equipmentItems = [];
            $orderNumber = 'ORD-PAD-' . strtoupper(Str::random(10));

            // Sewa Alat (Raket, Bola, Handuk) - Item Type: PADEL
            if (! empty($equipments)) {
                $primaryBooking = $bookings->first();

                foreach ($equipments as $item) {
                    // Lock row equipment SEBELUM baca stock_quantity — anti-race kalau 2 transaksi kasir
                    // bersamaan rebutan sisa stok BALL yang sama.
                    $eq = CourtEquipment::where('id', $item['equipment_id'])->lockForUpdate()->first();
                    if (! $eq || ! $eq->is_active) {
                        continue;
                    }

                    $qty = max(1, (int)$item['quantity']);

                    // BALL = consumable (dibeli habis, bukan disewa) -> stok dipotong SEKARANG saat checkout.
                    // RACKET/TOWEL = disewa (dipinjamkan fisik) -> stok baru dipotong nanti saat check-in
                    // (lihat ManagesCheckInAndTurnstile::checkIn()), dan bisa di-restock manual lewat retur alat.
                    $isConsumable = strtoupper($eq->type) === 'BALL';
                    if ($isConsumable) {
                        if ((int) $eq->stock_quantity < $qty) {
                            throw new HttpException(422, "Stok {$eq->name} tidak cukup (sisa {$eq->stock_quantity}, diminta {$qty}).");
                        }
                        $eq->decrement('stock_quantity', $qty);
                    }

                    $subtotal = (float) $eq->rental_price * $qty;
                    $equipmentTotal += $subtotal;

                    $equipmentItems[] = [
                        'equipment_id' => $eq->id,
                        'name' => $eq->name,
                        'quantity' => $qty,
                        'unit_price' => (float) $eq->rental_price,
                        'subtotal' => (float) $subtotal,
                        'stock_deducted' => $isConsumable,
                    ];
                }

                if ($equipmentTotal > 0) {
                    $primaryBooking->increment('equipment_fee', $equipmentTotal);
                    $primaryBooking->increment('total_amount', $equipmentTotal);
                }
            }

            $walkInFinanceCalc = app(\App\Services\Finance\TaxAndFeeService::class)->calculate(
                subtotal: $courtTotal + $equipmentTotal,
                discountAmount: 0,
                channel: 'POS_WALKIN',
                module: 'PADEL'
            );

            $grandTotal = $walkInFinanceCalc['grand_total'];

            // Buat Order resmi dengan order_type = 'WALK_IN' dan cashier_id terisi
            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => $customer->id,
                'cashier_id' => $cashier->id,
                'order_type' => 'WALK_IN',
                'subtotal' => $walkInFinanceCalc['subtotal'],
                'discount_amount' => 0.00,
                'voucher_code' => null,
                'tax_amount' => $walkInFinanceCalc['tax_amount'],
                'service_charge' => $walkInFinanceCalc['admin_fee_amount'],
                'grand_total' => $grandTotal,
                'payment_status' => 'UNPAID',
            ]);

            // Catat data sewa peralatan
            if (! empty($equipmentItems)) {
                $primaryBooking = $bookings->first();
                foreach ($equipmentItems as $eqItem) {
                    PadelBookingEquipment::create([
                        'order_id' => $order->id,
                        'booking_id' => $primaryBooking->id,
                        'equipment_id' => $eqItem['equipment_id'],
                        'quantity' => $eqItem['quantity'],
                        'unit_price' => $eqItem['unit_price'],
                        'subtotal' => $eqItem['subtotal'],
                        'stock_deducted_at' => ! empty($eqItem['stock_deducted']) ? now() : null,
                    ]);
                }
            }

            // Catat order_items (semua item_type = 'PADEL')
            foreach ($bookings as $b) {
                $order->items()->create([
                    'item_type' => 'PADEL',
                    'reference_id' => $b->id,
                    'item_name' => 'Sewa ' . ($b->court ? $b->court->name : 'Court Padel'),
                    'quantity' => 1,
                    'unit_price' => $b->court_fee,
                    'subtotal' => $b->court_fee,
                ]);
            }

            foreach ($equipmentItems as $eqItem) {
                $order->items()->create([
                    'item_type' => 'PADEL',
                    'reference_id' => $eqItem['equipment_id'],
                    'item_name' => $eqItem['name'],
                    'quantity' => $eqItem['quantity'],
                    'unit_price' => $eqItem['unit_price'],
                    'subtotal' => $eqItem['subtotal'],
                ]);
            }

            // Kaitkan booking dengan Order dan update status ke PENDING_PAYMENT
            foreach ($bookings as $b) {
                $b->update([
                    'order_id' => $order->id,
                    'status' => 'PENDING_PAYMENT',
                ]);
            }

            // Susun payload_log audit finansial
            $payloadLog = [
                'cashier_id' => $cashier->id,
                'cashier_name' => $cashier->name,
                'source' => 'WALK_IN_OFFLINE',
                'payment_method' => strtoupper($paymentMethod),
            ];

            if (in_array(strtoupper($paymentMethod), ['DEBIT_CARD', 'CREDIT_CARD', 'EDC_BCA', 'EDC_MANDIRI', 'DEBIT', 'CREDIT'])) {
                $cardType = $paymentMeta['card_type'] ?? (str_contains(strtoupper($paymentMethod), 'CREDIT') ? 'CREDIT' : 'DEBIT');
                $terminal = $paymentMeta['terminal'] ?? ($paymentMethod === 'EDC_MANDIRI' ? 'EDC_MANDIRI' : 'EDC_BCA');

                $payloadLog['edc_details'] = [
                    'terminal' => $terminal,
                    'card_type' => $cardType,
                    'card_network' => $paymentMeta['card_network'] ?? null,
                    'card_issuer' => $paymentMeta['card_issuer'] ?? 'BCA',
                    'card_last_4' => $paymentMeta['card_last_4'] ?? null,
                    'approval_code' => $paymentMeta['approval_code'] ?? null,
                    'trace_number' => $paymentMeta['trace_number'] ?? null,
                    'charged_amount' => isset($paymentMeta['charged_amount']) ? (float) $paymentMeta['charged_amount'] : (float) $grandTotal,
                ];
            } elseif (in_array(strtoupper($paymentMethod), ['QRIS_STATIS', 'QRIS'])) {
                $payloadLog['qris_details'] = [
                    'provider' => $paymentMeta['qris_provider'] ?? 'BCA_QRIS',
                    'rrn' => $paymentMeta['qris_rrn'] ?? null,
                    'sender_name' => $paymentMeta['qris_sender_name'] ?? null,
                ];
            } elseif (in_array(strtoupper($paymentMethod), ['CASH', 'TUNAI'])) {
                $payloadLog['cash_details'] = [
                    'cash_received' => isset($paymentMeta['cash_received']) ? (float) $paymentMeta['cash_received'] : (float) $grandTotal,
                    'cash_change' => isset($paymentMeta['cash_change']) ? (float) $paymentMeta['cash_change'] : 0.00,
                ];
            }

            // Eksekusi pelunasan langsung via PaymentOrchestratorService sebagai single writer
            $orchestrator = app(\App\Services\Payment\PaymentOrchestratorService::class);
            $orchestrator->markOrderAsPaid($order, [
                'payment_gateway' => 'CASHIER_POS',
                'counter' => 'PADEL_FRONTDESK',
                'transaction_id' => $orderNumber,
                'payment_method' => strtoupper($paymentMethod),
                'amount' => (float) $grandTotal,
                'cashier_id' => $cashier->id,
                'payload_log' => $payloadLog,
            ]);

            // Opsi Auto Check-In Software
            if ($autoCheckIn) {
                PadelBooking::where('order_id', $order->id)->update([
                    'status' => 'CHECKED_IN',
                    'checked_in_at' => now(),
                ]);
            }

            $updatedBookings = PadelBooking::with(['court', 'equipments.equipment'])
                ->where('order_id', $order->id)
                ->get();

            return [
                'success' => true,
                'message' => 'Reservasi walk-in berhasil diproses dan lunas.',
                'order' => $order->fresh(['items', 'payments']),
                'bookings' => $updatedBookings,
                'grand_total' => (float) $grandTotal,
                'payment_method' => strtoupper($paymentMethod),
                'customer' => $customer->fresh(),
                'auto_checked_in' => $autoCheckIn,
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

    /**
     * Terapkan kuota atau diskon membership pada koleksi booking lapangan padel.
     *
     * @param \Illuminate\Support\Collection $bookings
     * @param User $user
     * @param string|null $membershipBalanceId
     * @return array
     */
    protected function applyMembershipBenefitToCourtBookings(
        iterable $bookings,
        User $user,
        ?string $membershipBalanceId = null,
        bool $commit = true
    ): array {
        $noBenefit = [
            'applied_balance' => null,
            'hours_consumed' => 0.0,
            'total_court_discount' => 0.0,
            'benefit_type' => 'NONE',
            'plan_name' => null,
            'discount_percent' => 0.0,
            'remaining_quota_after' => null,
            'rejected_reason' => null,
        ];

        if ($membershipBalanceId === 'none' || $membershipBalanceId === 'NONE') {
            return $noBenefit;
        }

        // Mode preview (dipanggil dari endpoint "lihat dulu sebelum bayar") tidak perlu row-lock karena
        // tidak menulis apa-apa ke database — locking cukup dilakukan saat commit (checkout beneran).
        $balance = null;
        if ($membershipBalanceId) {
            $query = \App\Models\Membership\UserMembershipBalance::where('id', $membershipBalanceId);
            $balance = $commit ? $query->lockForUpdate()->first() : $query->first();
        } else {
            // Auto-detect active membership Padel balance
            $activeMembership = \App\Models\Membership\UserMembership::where('user_id', $user->id)
                ->where('status', 'ACTIVE')
                ->where(function ($q) {
                    $q->whereNull('end_date')->orWhere('end_date', '>=', now()->toDateString());
                })
                ->whereHas('balances', function ($q) {
                    $q->where('facility', 'PADEL');
                })
                // Tie-break: kartu yang paling cepat kedaluwarsa dipakai lebih dulu (jam tidak hangus sia-sia
                // jika user punya >1 membership aktif); kartu tanpa end_date (unlimited) diprioritaskan paling akhir.
                ->orderByRaw('end_date IS NULL, end_date ASC')
                ->first();

            if ($activeMembership) {
                $query = \App\Models\Membership\UserMembershipBalance::where('user_membership_id', $activeMembership->id)
                    ->where('facility', 'PADEL');
                $balance = $commit ? $query->lockForUpdate()->first() : $query->first();
            }
        }

        if (! $balance) {
            return $noBenefit;
        }

        $membership = $balance->membership;
        if (! $membership || ! $membership->isActive() || $membership->user_id !== $user->id) {
            return $noBenefit;
        }

        if ($balance->facility !== 'PADEL') {
            return $noBenefit;
        }

        // Time window check — SELURUH rentang booking (mulai s.d. selesai) wajib di dalam jendela waktu paket,
        // bukan cuma jam mulainya saja (all-or-nothing, tidak ada benefit parsial untuk booking yang nyerempet keluar jendela).
        if ($balance->time_window_start && $balance->time_window_end) {
            foreach ($bookings as $b) {
                $bookingStart = \Carbon\Carbon::parse($b->start_time)->format('H:i:s');
                $bookingEnd = \Carbon\Carbon::parse($b->end_time)->format('H:i:s');
                if ($bookingStart < $balance->time_window_start || $bookingEnd > $balance->time_window_end) {
                    if ($commit) {
                        throw new HttpException(422, "Sesi booking di luar jam akses paket membership ({$balance->time_window_start} - {$balance->time_window_end}). Seluruh durasi booking harus berada di dalam jendela waktu paket.");
                    }

                    // Mode preview: jangan gagalkan halaman, cukup laporkan benefit tidak berlaku + alasannya.
                    return array_merge($noBenefit, ['rejected_reason' => 'OUTSIDE_TIME_WINDOW']);
                }
            }
        }

        $balanceService = app(\App\Services\Membership\MembershipBalanceService::class);
        $totalHoursConsumed = 0.0;
        $totalCourtDiscount = 0.0;
        $benefitType = 'NONE';
        // Simulasi sisa kuota berjalan (dipakai di mode preview agar booking ke-2/ke-3 dalam 1 keranjang tetap
        // konsisten dengan mode commit tanpa perlu benar-benar mendekremen apa pun ke database).
        $simulatedRemaining = (float) $balance->remaining_quota;

        foreach ($bookings as $booking) {
            $durationMinutes = \Carbon\Carbon::parse($booking->start_time)->diffInMinutes(\Carbon\Carbon::parse($booking->end_time));
            $hoursNeeded = max(0.5, round($durationMinutes / 60, 2));

            if ($balance->quota_type === 'HOURS' && $simulatedRemaining >= $hoursNeeded) {
                // Kuota jam mencukupi: menanggung 100% biaya sewa lapangan
                $discount = (float) $booking->court_fee;

                if ($commit) {
                    $balanceService->adjustQuota(
                        balanceId: $balance->id,
                        changeType: 'DECREMENT',
                        quantity: $hoursNeeded,
                        notes: 'Pemakaian jam main Padel booking ' . $booking->booking_code,
                        relatedType: PadelBooking::class,
                        relatedId: $booking->id
                    );

                    $booking->membership_balance_id = $balance->id;
                    $booking->member_hours_consumed = $hoursNeeded;
                    $booking->member_discount_court = $discount;
                    $booking->court_fee = 0.00;
                    $booking->total_amount = max(0, (float) $booking->total_amount - $discount);
                    $booking->save();

                    $balance->refresh();
                    $simulatedRemaining = (float) $balance->remaining_quota;
                } else {
                    $simulatedRemaining -= $hoursNeeded;
                }

                $totalHoursConsumed += $hoursNeeded;
                $totalCourtDiscount += $discount;
                $benefitType = 'HOURS';
            } elseif ((float) $balance->discount_percent > 0) {
                // Kuota jam habis atau quota_type NONE: diskon persentase flat
                $courtFee = (float) $booking->court_fee;
                $discount = round($courtFee * ((float) $balance->discount_percent / 100), 2);

                if ($commit) {
                    $booking->membership_balance_id = $balance->id;
                    $booking->member_hours_consumed = 0.00;
                    $booking->member_discount_court = $discount;
                    $booking->court_fee = max(0, $courtFee - $discount);
                    $booking->total_amount = max(0, (float) $booking->total_amount - $discount);
                    $booking->save();
                }

                $totalCourtDiscount += $discount;
                $benefitType = $benefitType === 'NONE' ? 'DISCOUNT_PERCENT' : $benefitType;
            }
        }

        return [
            'applied_balance' => $balance,
            'hours_consumed' => $totalHoursConsumed,
            'total_court_discount' => $totalCourtDiscount,
            'benefit_type' => $benefitType,
            'plan_name' => $membership->plan->name ?? null,
            'discount_percent' => (float) $balance->discount_percent,
            'remaining_quota_after' => $simulatedRemaining,
            'rejected_reason' => null,
        ];
    }

    /**
     * Preview (read-only, tanpa efek samping) benefit membership untuk booking yang sudah di-hold,
     * dipakai halaman "Payment Details & Checkout" (online) maupun POS walk-in agar customer/kasir
     * tahu ada potongan SEBELUM menekan tombol bayar — bukan baru ketahuan setelah checkout dieksekusi.
     */
    public function previewMembershipBenefit(array $bookingIds, User $user, ?string $membershipBalanceId = null): array
    {
        $bookings = PadelBooking::with('court')
            ->whereIn('id', $bookingIds)
            ->where('user_id', $user->id)
            ->where('status', 'LOCKED')
            ->get();

        if ($bookings->isEmpty()) {
            throw new HttpException(422, 'Satu atau lebih slot booking tidak valid atau masa kuncian telah kedaluwarsa.');
        }

        $result = $this->applyMembershipBenefitToCourtBookings($bookings, $user, $membershipBalanceId, commit: false);

        $courtSubtotal = (float) $bookings->sum('court_fee');
        $projectedCourtTotal = max(0, $courtSubtotal - $result['total_court_discount']);

        return [
            'has_benefit' => $result['applied_balance'] !== null,
            'benefit_type' => $result['benefit_type'], // NONE | HOURS | DISCOUNT_PERCENT
            'plan_name' => $result['plan_name'],
            'discount_percent' => $result['discount_percent'],
            'hours_to_consume' => $result['hours_consumed'],
            'remaining_quota_after' => $result['remaining_quota_after'],
            'court_discount_amount' => $result['total_court_discount'],
            'court_subtotal' => $courtSubtotal,
            'projected_court_total' => $projectedCourtTotal,
            'rejected_reason' => $result['rejected_reason'],
        ];
    }
}
