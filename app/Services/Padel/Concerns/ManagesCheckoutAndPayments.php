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
            $orderNumber = 'ORD-PAD-' . strtoupper(Str::random(10));
            $isStaff = $user->isStaff();
            $isCash = strtoupper($paymentMethod) === 'CASH';

            // Proses Sewa Peralatan (Raket, Bola, Handuk)
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

                    $equipmentItems[] = [
                        'equipment_id' => $eq->id,
                        'name' => $eq->name,
                        'quantity' => $qty,
                        'unit_price' => (float)$eq->rental_price,
                        'subtotal' => (float)$subtotal,
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

            // Biaya Layanan Gateway
            $gatewayFee = match (strtoupper($paymentMethod)) {
                'CASH', 'EDC_BCA', 'EDC_MANDIRI', 'QRIS_STATIS' => 0,
                'QRIS' => 2800,
                default => 4440,
            };
            $grandTotal = max(0, $courtTotal + $equipmentTotal + $gatewayFee - $discountAmount);

            // Eager Order Creation
            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => $user->id,
                'cashier_id' => ($isStaff && $isCash) ? $user->id : null,
                'order_type' => 'ONLINE_BOOKING',
                'subtotal' => $courtTotal + $equipmentTotal,
                'discount_amount' => $discountAmount,
                'voucher_code' => $appliedVoucherCode,
                'service_charge' => $gatewayFee,
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
                    'id' => 'DISC-' . substr($appliedVoucherCode ?? 'PROMO', 0, 10),
                    'price' => -(int) $discountAmount,
                    'quantity' => 1,
                    'name' => 'Voucher Diskon',
                ];
            }

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

            // Petakan Order ID dan Order Number ke ID Bookings di Cache selama 24 Jam
            Cache::put("order_bookings:{$orderNumber}", $bookings->pluck('id')->toArray(), 86400);
            Cache::put("order_bookings:{$order->id}", $bookings->pluck('id')->toArray(), 86400);

            if ($appliedVoucherCode) {
                Cache::put("order_voucher:{$orderNumber}", $appliedVoucherCode, 86400);
                Cache::put("order_voucher:{$order->id}", $appliedVoucherCode, 86400);
            }

            // Tentukan status awal transaksi
            if ($isCash && ! $isStaff) {
                // Customer checkout tunai dari web/mobile wajib PENDING_PAYMENT
                $initialStatus = 'PENDING_PAYMENT';
            } else {
                // Tunai hanya langsung PAID jika diproses oleh staf kasir di meja POS, atau gateway mock aktif (non-CASH)
                $initialStatus = ($paymentResult['is_mock'] || ($isCash && $isStaff)) ? 'PAID' : 'PENDING_PAYMENT';
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
                    'payment_gateway' => $paymentResult['is_mock'] ? 'MOCK' : ($isCash ? 'CASH' : 'MIDTRANS'),
                    'transaction_id' => $orderNumber,
                    'payment_method' => strtoupper($paymentMethod),
                    'amount' => (float) $grandTotal,
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

            // Hitung Biaya Layanan Gateway Baru
            $newGatewayFee = match (strtoupper($paymentMethod)) {
                'CASH' => 0,
                'QRIS' => 2800,
                default => 4440,
            };

            $isCash = strtoupper($paymentMethod) === 'CASH';

            if ($isSupplementalDelta) {
                // HANYA menagih nominal selisih (delta), bukan menagih ulang seluruh order
                $deltaAmount = (float) $pendingSupplementalPayment->amount;
                $chargeTotal = max(0, $deltaAmount + $newGatewayFee);

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
            $grandTotal = max(0, $baseAmount + $newGatewayFee);

            $order->update([
                'subtotal' => $baseAmount,
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
            if ($newGatewayFee > 0) {
                $midtransItems[] = [
                    'id' => 'FEE-GATEWAY',
                    'price' => (int) $newGatewayFee,
                    'quantity' => 1,
                    'name' => 'Biaya Layanan Gerbang',
                ];
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
                'gateway_fee' => $newGatewayFee,
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
            'EDC_BCA' => 'Debit/Kartu EDC BCA',
            'EDC_MANDIRI' => 'Debit/Kartu EDC Mandiri',
            'QRIS_STATIS' => 'QRIS Kasir Frontdesk',
            'CREDIT_CARD' => 'Kartu Kredit',
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

        return User::create([
            'name' => trim($name),
            'phone' => $cleanPhone,
            'email' => $fallbackEmail,
            'password' => Hash::make(Str::random(32)),
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
        bool $autoCheckIn = false
    ): array {
        // 1. Hold batch slots untuk customer (melempar SlotConflictException jika tabrakan)
        $holdResult = $this->holdBatchSlots($slots, $bookingDate, $customer);
        $bookingIds = collect($holdResult['bookings'])->pluck('id')->toArray();

        return DB::transaction(function () use ($customer, $bookingIds, $equipments, $paymentMethod, $cashier, $autoCheckIn) {
            $bookings = PadelBooking::with('court')
                ->whereIn('id', $bookingIds)
                ->where('user_id', $customer->id)
                ->where('status', 'LOCKED')
                ->get();

            if ($bookings->count() !== count($bookingIds)) {
                throw new HttpException(422, 'Satu atau lebih slot booking tidak valid atau masa kuncian telah kedaluwarsa.');
            }

            $courtTotal = (float) $bookings->sum('court_fee');
            $equipmentTotal = 0;
            $equipmentItems = [];
            $orderNumber = 'ORD-PAD-' . strtoupper(Str::random(10));

            // Sewa Alat (Raket, Bola, Handuk) - Item Type: PADEL
            if (! empty($equipments)) {
                $primaryBooking = $bookings->first();

                foreach ($equipments as $item) {
                    $eq = CourtEquipment::find($item['equipment_id']);
                    if (! $eq) {
                        continue;
                    }

                    $qty = max(1, (int)$item['quantity']);
                    $subtotal = (float) $eq->rental_price * $qty;
                    $equipmentTotal += $subtotal;

                    $equipmentItems[] = [
                        'equipment_id' => $eq->id,
                        'name' => $eq->name,
                        'quantity' => $qty,
                        'unit_price' => (float) $eq->rental_price,
                        'subtotal' => (float) $subtotal,
                    ];
                }

                if ($equipmentTotal > 0) {
                    $primaryBooking->increment('equipment_fee', $equipmentTotal);
                    $primaryBooking->increment('total_amount', $equipmentTotal);
                }
            }

            $grandTotal = $courtTotal + $equipmentTotal;

            // Buat Order resmi dengan order_type = 'WALK_IN' dan cashier_id terisi
            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => $customer->id,
                'cashier_id' => $cashier->id,
                'order_type' => 'WALK_IN',
                'subtotal' => $grandTotal,
                'discount_amount' => 0.00,
                'voucher_code' => null,
                'service_charge' => 0.00,
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

            // Eksekusi pelunasan langsung via PaymentOrchestratorService
            $orchestrator = app(\App\Services\Payment\PaymentOrchestratorService::class);
            $orchestrator->markOrderAsPaid($order, [
                'payment_gateway' => 'CASHIER_POS',
                'transaction_id' => $orderNumber,
                'payment_method' => strtoupper($paymentMethod),
                'amount' => (float) $grandTotal,
                'cashier_id' => $cashier->id,
                'payload_log' => [
                    'cashier_id' => $cashier->id,
                    'cashier_name' => $cashier->name,
                    'source' => 'WALK_IN_OFFLINE',
                    'payment_method' => strtoupper($paymentMethod),
                ],
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
}
