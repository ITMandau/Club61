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
        ?string $membershipBalanceId = null,
        ?string $sponsorVoucherId = null
    ): array {
        // 100% Cashless: pembayaran tunai tidak diperbolehkan sama sekali di jalur checkout online.
        if (in_array(strtoupper($paymentMethod), ['CASH', 'TUNAI'])) {
            throw new HttpException(422, 'Pembayaran tunai (CASH) tidak diperbolehkan. Venue Club 61 beroperasi 100% Cashless.');
        }

        $cacheIdempotencyKey = "idempotency:padel:checkout:{$idempotencyKey}";

        // Cek apakah request ini sudah pernah berhasil diproses dalam 24 jam terakhir
        if ($cachedResponse = Cache::get($cacheIdempotencyKey)) {
            return $cachedResponse;
        }

        return DB::transaction(function () use ($bookingIds, $equipments, $voucherCode, $paymentMethod, $user, $cacheIdempotencyKey, $membershipBalanceId, $sponsorVoucherId) {
            // lockForUpdate() DI DALAM transaksi, bukan SELECT biasa sebelum transaksi dimulai —
            // tanpa ini, dua request checkout bersamaan (klik ganda / retry) untuk booking yang
            // sama-sama masih 'LOCKED' bisa lolos cek status ini dua-duanya sebelum salah satunya
            // sempat commit, lalu dua-duanya lanjut memotong kuota membership/voucher sponsor dan
            // membuat 2 Order terpisah untuk 1 slot yang sama.
            $bookings = PadelBooking::with('court')
                ->whereIn('id', $bookingIds)
                ->where('user_id', $user->id)
                ->where('status', 'LOCKED')
                ->lockForUpdate()
                ->get();

            $bookingTimes = app(\App\Services\Padel\BookingTimeService::class);
            $holdExpired = $bookings->contains(fn (PadelBooking $b) => ($b->expires_at ?? $b->created_at->copy()->addMinutes($bookingTimes->holdMinutes()))->isPast());

            // Hold yang waktunya sudah habis tapi belum sempat dibersihkan tidak boleh di-checkout — slotnya sedang/akan
            // dilepas pembersih otomatis (dulu bisa balapan: checkout menang lalu bookingnya tetap ditimpa EXPIRED).
            if ($bookings->count() !== count($bookingIds) || $holdExpired) {
                throw new HttpException(422, 'Satu atau lebih slot booking tidak valid atau masa kuncian ('.$bookingTimes->holdMinutes().' menit) telah kedaluwarsa. Silakan pilih slot lagi.');
            }

            // Terapkan kuota atau diskon membership jika ada
            $membershipBenefitResult = $this->applyMembershipBenefitToCourtBookings($bookings, $user, $membershipBalanceId);

            // Voucher jam sponsor corporate HANYA dipakai untuk booking yang belum ter-cover 100%
            // oleh benefit membership individual di atas — mencegah 1 booking "gratis dobel" dari
            // dua sumber benefit sekaligus. Mayoritas kasus: customer cuma punya salah satu.
            $sponsorBenefitResult = $this->applySponsorVoucherBenefitToCourtBookings(
                $bookings->filter(fn ($b) => (float) $b->court_fee > 0),
                $user,
                $sponsorVoucherId
            );

            $courtTotal = (float) $bookings->sum('court_fee');
            $equipmentTotal = 0;
            $equipmentItems = [];
            $orderNumber = 'ORD-PAD-' . strtoupper(Str::random(10));

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
                // lockForUpdate() men-serialize baris voucher ini antar checkout yang konkuren.
                // Kuota (kolom `quota`) sendiri baru benar-benar dipotong permanen saat pembayaran
                // lunas (lihat PaymentOrchestratorService::markOrderAsPaid) — dipertahankan seperti
                // itu karena jalur lain (settle tunai POS, dsb.) juga bergantung ke situ. Tapi kalau
                // eligibility DI SINI cuma dicek terhadap `quota` yang belum berkurang itu, order
                // yang statusnya masih UNPAID/PENDING_PAYMENT (belum lunas) tidak ikut kehitung —
                // jadi kalau kuota tinggal 1, checkout paralel/berurutan yang sama-sama belum bayar
                // bisa semua lolos dapat diskon sebelum salah satunya lunas duluan. Makanya di sini
                // kita hitung juga order yang SUDAH mengklaim kode ini tapi belum lunas ("reserved
                // in-flight"), dan kurangi itu dari quota yang tersisa sebelum memutuskan eligible.
                $voucher = \App\Models\Pos\Voucher::where('code', $code)
                    ->where('is_active', true)
                    ->where(function ($q) {
                        $q->whereNull('valid_until')->orWhere('valid_until', '>=', now());
                    })
                    ->lockForUpdate()
                    ->first();

                if ($voucher) {
                    $orderAmount = $courtTotal + $equipmentTotal;
                    $minOrder = (float) ($voucher->min_order_amount ?? 0);

                    $hasQuota = true;
                    if ($voucher->quota !== null) {
                        $reservedInFlight = Order::where('voucher_code', $voucher->code)
                            ->whereIn('payment_status', ['UNPAID', 'PENDING_PAYMENT', 'PARTIALLY_PAID'])
                            ->count();
                        $hasQuota = ($voucher->quota - $reservedInFlight) > 0;
                    }

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
                'cashier_id' => null,
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

            $paymentDeadline = null;
            if ($grandTotal <= 0) {
                // Tentukan sumber sebenarnya yang menutup 100% biaya ini — dulu selalu di-hardcode
                // 'MEMBERSHIP_QUOTA' walau yang benar-benar menutupnya voucher jam sponsor corporate
                // (atau kombinasi keduanya), bikin invoice/payment method salah label.
                $coveredBy = match (true) {
                    $membershipBenefitResult['benefit_type'] !== 'NONE' && $sponsorBenefitResult['benefit_type'] !== 'NONE' => 'MEMBERSHIP_AND_SPONSOR_VOUCHER',
                    $sponsorBenefitResult['benefit_type'] !== 'NONE' => 'SPONSOR_VOUCHER',
                    $membershipBenefitResult['benefit_type'] !== 'NONE' => 'MEMBERSHIP_QUOTA',
                    default => 'PROMO_VOUCHER',
                };

                $paymentResult = [
                    'is_mock' => true,
                    'driver' => $coveredBy,
                    'snap_token' => null,
                    'payment_url' => null,
                    'redirect_url' => null,
                    'checkout_mode' => 'NONE',
                    'raw' => ['covered_by' => $coveredBy],
                ];
                $initialStatus = 'PAID';
            } else {
                // Metode harus aktif & sesuai batas nominal (mis. QRIS maks Rp10 juta) — dicek di server, bukan cuma
                // disaring di halaman. Gagal di sini = seluruh checkout di-rollback (booking tetap LOCKED, bisa dicoba lagi).
                $paymentMethod = app(\App\Services\Payment\OnlinePaymentMethodService::class)->assertSelectable($paymentMethod, (float) $grandTotal);

                // Batas bayar dihitung SEJAK KLIK BAYAR dan dipakai dua-duanya: expiry sesi Midtrans & pelepasan slot.
                $bookingTimes = app(\App\Services\Padel\BookingTimeService::class);
                $paymentDeadline = $bookingTimes->paymentExpiresAt();

                // Panggil Payment Manager (Midtrans & Mock Simulator)
                $paymentManager = app(\App\Services\Payment\PaymentManager::class);
                $paymentResult = $paymentManager->createPayment([
                    'order_id' => $orderNumber,
                    'expiry_minutes' => $bookingTimes->paymentWindowMinutes(),
                    'gross_amount' => (int) $grandTotal,
                    'item_details' => $midtransItems,
                    'customer_details' => [
                        'first_name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone ?? '081261617233',
                    ],
                    'payment_method' => $paymentMethod,
                ]);

                // Tentukan status awal transaksi: PAID seketika hanya kalau gateway mock aktif (simulator),
                // selain itu selalu PENDING_PAYMENT sampai konfirmasi sungguhan dari gateway (Midtrans webhook).
                $initialStatus = $paymentResult['is_mock'] ? 'PAID' : 'PENDING_PAYMENT';
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
                    'expires_at' => $initialStatus === 'PENDING_PAYMENT' ? $paymentDeadline : null,
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
                    'payment_gateway' => $grandTotal <= 0 ? $paymentResult['driver'] : ($paymentResult['is_mock'] ? 'MOCK' : 'MIDTRANS'),
                    'counter' => 'PADEL_FRONTDESK',
                    'transaction_id' => $orderNumber,
                    'payment_method' => $grandTotal <= 0 ? $paymentResult['driver'] : strtoupper($paymentMethod),
                    'amount' => (float) $grandTotal,
                    'user' => $user,
                    'payload_log' => $paymentResult['raw'] ?? null,
                ]);
            } else {
                // Simpan record pembayaran awal PENDING dengan transaction_id = orderNumber
                Payment::create([
                    'order_id' => $order->id,
                    'payment_gateway' => 'MIDTRANS',
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
                    : 'Sesi transaksi pembayaran berhasil dibuat. Silakan selesaikan pembayaran.',
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
        // 100% Cashless: pembayaran tunai tidak diperbolehkan sama sekali di jalur retry pembayaran.
        if (in_array(strtoupper($paymentMethod), ['CASH', 'TUNAI'])) {
            throw new HttpException(422, 'Pembayaran tunai (CASH) tidak diperbolehkan. Venue Club 61 beroperasi 100% Cashless.');
        }

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
                // Dicari per order: utamakan booking yang memang masih punya tagihan (bukan booking lunas pertama).
                ->orderByRaw("CASE WHEN status IN ('LOCKED', 'PENDING_PAYMENT', 'PENDING') THEN 0 ELSE 1 END")
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

            // Tagihan MILIK booking ini (order bisa punya beberapa tagihan selisih untuk booking berbeda).
            $booking->setRelation('order', $order);
            $pendingSupplementalPayment = $this->pendingBillForBooking($booking, lock: true);

            // Order yang SUDAH pernah dibayar tapi tagihan PENDING-nya hilang (mis. ditutup rekonsiliasi)
            // tidak boleh jatuh ke "retry pembayaran penuh" di bawah — itu menagih ulang seluruh order
            // yang sudah lunas. Buat ulang tagihan sisa (grand_total - sudah dibayar), atau tolak kalau lunas.
            if ($totalPaid > 0 && ! $pendingSupplementalPayment) {
                $remaining = round((float) $order->grand_total - $totalPaid, 2);
                if ($remaining <= 0) {
                    throw new HttpException(422, 'Pesanan ini sudah lunas, tidak ada tagihan yang perlu dibayar.');
                }

                $pendingSupplementalPayment = Payment::create([
                    'order_id' => $order->id,
                    'payment_gateway' => 'CASHIER_POS',
                    'transaction_id' => 'SUPP-'.strtoupper(Str::random(12)),
                    'amount' => $remaining,
                    'payment_method' => 'MENUNGGU_PEMBAYARAN',
                    'status' => 'PENDING',
                    'payload_log' => ['type' => 'RESCHEDULE_PRICE_DELTA', 'booking_id' => $booking->id, 'recreated_from_remaining_balance' => true],
                ]);
            }

            // Tagihan selisih reschedule ditentukan dari TAGIHANNYA, bukan dari "sudah pernah bayar": booking yang dulu
            // 100% ditanggung kuota member / voucher punya pembayaran Rp0, tapi selisih reschedule-nya tetap selisih —
            // dulu jatuh ke "bayar ulang penuh" yang menghitung ulang seluruh order & menimpa nominal tagihan kasir.
            $isRescheduleBill = $pendingSupplementalPayment
                && (($pendingSupplementalPayment->payload_log['type'] ?? null) === 'RESCHEDULE_PRICE_DELTA' || (int) $booking->reschedule_count > 0);
            $isSupplementalDelta = $pendingSupplementalPayment && ($totalPaid > 0 || $isRescheduleBill);

            if ($isSupplementalDelta) {
                // HANYA menagih nominal selisih (delta), bukan menagih ulang seluruh order.
                // Tidak ada biaya gateway tambahan di sini — delta sudah dihitung lengkap dengan
                // pajak/biaya layanan sejak adminRescheduleBooking() menetapkan nominal PENDING ini.
                $deltaAmount = (float) $pendingSupplementalPayment->amount;
                $chargeTotal = max(0, $deltaAmount);
                $newGatewayFee = 0;
                $paymentMethod = app(\App\Services\Payment\OnlinePaymentMethodService::class)->assertSelectable($paymentMethod, (float) $chargeTotal);

                // Midtrans Snap untuk Pelunasan Delta
                $orderNumber = $order->order_number ?: ('ORD-PAD-' . strtoupper(Str::random(8)));
                // + komponen acak: dua permintaan di detik yang sama dulu menghasilkan order_id kembar → Midtrans
                // menolak dan customer melihat pesan "gateway down" yang menyesatkan.
                $suffixedOrderId = $orderNumber . '_DELTA_' . time() . strtoupper(Str::random(4));

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
                        'midtrans_order_ids' => array_values(array_unique(array_merge(
                            (array) ($pendingSupplementalPayment->payload_log['midtrans_order_ids'] ?? []),
                            [$suffixedOrderId]
                        ))),
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

            // Bayar ulang / ganti metode TIDAK memperpanjang batas bayar: sesi Midtrans baru hanya diberi sisa waktu
            // sampai batas bayar booking (dihitung sejak klik bayar pertama). Booking yang masih LOCKED (belum pernah
            // checkout) = klik bayar pertama → batas bayar mulai dihitung sekarang.
            $bookingTimes = app(\App\Services\Padel\BookingTimeService::class);
            $unpaidBookings = $bookings->filter(fn (PadelBooking $b) => in_array($b->status, ['LOCKED', 'PENDING_PAYMENT', 'PENDING'], true) && (int) $b->reschedule_count === 0);
            if ($booking->status === 'LOCKED') {
                $paymentDeadline = $bookingTimes->paymentExpiresAt();
                foreach ($unpaidBookings as $b) {
                    $b->update(['status' => 'PENDING_PAYMENT', 'expires_at' => $paymentDeadline]);
                }
            } else {
                $paymentDeadline = $bookingTimes->paymentDeadlineFor($booking);
            }
            $expiryMinutes = $bookingTimes->remainingPaymentMinutes($paymentDeadline);
            if ($expiryMinutes === null) {
                throw new HttpException(422, 'Batas waktu pembayaran booking ini sudah habis, slot akan dilepas. Silakan buat booking baru.');
            }

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

            if ($grandTotal > 0) {
                $paymentMethod = app(\App\Services\Payment\OnlinePaymentMethodService::class)->assertSelectable($paymentMethod, (float) $grandTotal);
            }

            // On-the-Fly Suffix Logic untuk Midtrans Snap
            $orderNumber = $order->order_number ?: ('ORD-PAD-' . strtoupper(Str::random(8)));
            $suffixedOrderId = $orderNumber . '_' . time() . strtoupper(Str::random(4));

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
                'expiry_minutes' => $expiryMinutes,
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

            // Simpan PERMANEN order_id yang dikirim ke Midtrans — cache di atas hilang dalam 24 jam,
            // padahal rekonsiliasi (MidtransReconciliationService) butuh id persis ini untuk
            // menanyakan status pembayaran kalau webhook-nya tidak pernah sampai.
            $pendingPayment = Payment::where('order_id', $order->id)->where('status', 'PENDING')->latest()->lockForUpdate()->first();
            if ($pendingPayment) {
                $log = is_array($pendingPayment->payload_log) ? $pendingPayment->payload_log : [];
                $log['midtrans_order_id'] = $suffixedOrderId;
                $log['midtrans_order_ids'] = array_values(array_unique(array_merge((array) ($log['midtrans_order_ids'] ?? []), [$suffixedOrderId])));
                $log['snap_token'] = $paymentResult['snap_token'] ?? null;
                $updates = ['payload_log' => $log];
                if ($pendingPayment->payment_gateway === 'MIDTRANS') {
                    // Nominal ikut grand_total yang baru dihitung ulang di atas (pajak/biaya bisa berubah sejak checkout).
                    // Tagihan kasir TIDAK diubah nominalnya — itu yang ditagih kasir kalau customer batal bayar online.
                    $updates['amount'] = (float) $grandTotal;
                    // Metode yang SEKARANG dipilih customer — dulu tetap metode lama sampai webhook lunas masuk.
                    $updates['payment_method'] = $paymentMethod;
                    $updates['snap_token'] = $paymentResult['snap_token'] ?? $pendingPayment->snap_token;
                    $updates['payment_url'] = $paymentResult['payment_url'] ?? $paymentResult['redirect_url'] ?? $pendingPayment->payment_url;
                } else {
                    // Tagihan kasir (bayar nanti) tetap tagihan kasir; percobaan bayar online cukup dicatat.
                    $log['online_payment_method'] = $paymentMethod;
                    $updates['payload_log'] = $log;
                }
                $pendingPayment->update($updates);
            } else {
                // Tagihan lama sudah ditutup (mis. rekonsiliasi menandai FAILED) tapi booking masih menunggu bayar.
                // Dulu sesi Snap baru ini tidak tercatat di mana pun selain cache 24 jam → kalau webhook-nya hilang,
                // rekonsiliasi tidak pernah menanyakannya dan uang customer tidak terdeteksi.
                Payment::create([
                    'order_id' => $order->id,
                    'payment_gateway' => 'MIDTRANS',
                    'transaction_id' => $suffixedOrderId,
                    'snap_token' => $paymentResult['snap_token'] ?? null,
                    'payment_url' => $paymentResult['payment_url'] ?? $paymentResult['redirect_url'] ?? null,
                    'amount' => (float) $grandTotal,
                    'payment_method' => $paymentMethod,
                    'status' => 'PENDING',
                    'payload_log' => ['midtrans_order_id' => $suffixedOrderId, 'midtrans_order_ids' => [$suffixedOrderId], 'snap_token' => $paymentResult['snap_token'] ?? null],
                ]);
            }

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
        // 1. Metode yang BENAR-BENAR dipakai customer menurut Midtrans (webhook / rekonsiliasi).
        if (!empty($payload['payment_type'])) {
            $pt = strtolower((string) $payload['payment_type']);
            if ($pt === 'bank_transfer' && !empty($payload['va_numbers'][0]['bank'])) {
                $bank = strtoupper($payload['va_numbers'][0]['bank']);
                return "{$bank} Virtual Account";
            }
            if ($pt === 'bank_transfer' && !empty($payload['permata_va_number'])) {
                return 'Permata Virtual Account';
            }
            if ($pt === 'echannel') {
                return 'Mandiri Virtual Account';
            }
            if (in_array($pt, ['qris', 'gopay', 'shopeepay'])) {
                return 'QRIS Instan (GoPay/OVO/BCA)';
            }
            if ($pt === 'credit_card') {
                return 'Kartu Kredit / Debit Online';
            }
        }

        // 2. Metode online pilihan customer: nama dari pengaturan "Metode Pembayaran Online" / katalog resmi.
        //    Pembayaran kasir (ada bukti EDC/QRIS frontdesk) memakai kode yang sama (QRIS, CREDIT_CARD) tapi BUKAN
        //    pembayaran online — jangan diberi label online.
        $isCounterPayment = !empty($payload['edc_details']) || !empty($payload['qris_details']) || !empty($payload['cashier_id']);
        if ($method && ! $isCounterPayment && ($onlineLabel = app(\App\Services\Payment\OnlinePaymentMethodService::class)->label($method))) {
            return $onlineLabel;
        }

        return match (strtoupper((string) $method)) {
            'BCA_VA' => 'BCA Virtual Account',
            'MANDIRI_VA' => 'Mandiri Virtual Account',
            'BRI_VA' => 'BRI Virtual Account',
            'BNI_VA' => 'BNI Virtual Account',
            'CIMB_VA' => 'CIMB Virtual Account',
            'BSI_VA' => 'VA Bank Lain (Permata, BSI, dll.)',
            'QRIS' => 'QRIS Instan (GoPay/OVO/BCA)',
            'CASH' => 'Tunai di Kasir (CASH)',
            'DEBIT_CARD', 'DEBIT' => 'Kartu Debit (EDC)',
            'CREDIT_CARD', 'CREDIT' => 'Kartu Kredit (EDC)',
            'EDC_BCA' => 'Debit/Kartu EDC BCA',
            'EDC_MANDIRI' => 'Debit/Kartu EDC Mandiri',
            'QRIS_STATIS' => 'QRIS Kasir Frontdesk',
            'BANK_TRANSFER' => 'Transfer Bank (VA)',
            'TRANSFER_BANK' => 'Transfer Bank (Frontdesk)',
            'MENUNGGU_PEMBAYARAN' => 'Menunggu Pembayaran',
            'MEMBERSHIP_QUOTA' => 'Kuota Jam Membership (Gratis)',
            'SPONSOR_VOUCHER' => 'Voucher Jam Corporate (Gratis)',
            'MEMBERSHIP_AND_SPONSOR_VOUCHER' => 'Kuota Membership + Voucher Corporate (Gratis)',
            'PROMO_VOUCHER' => 'Kode Promo (Gratis)',
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
        // 100% Cashless: pembayaran tunai tidak diperbolehkan sama sekali di loket walk-in.
        if (in_array(strtoupper($paymentMethod), ['CASH', 'TUNAI'])) {
            throw new HttpException(422, 'Pembayaran tunai (CASH) tidak diperbolehkan. Venue Club 61 beroperasi 100% Cashless.');
        }

        // 1. Hold batch slots untuk customer (melempar SlotConflictException jika tabrakan)
        $holdResult = $this->holdBatchSlots($slots, $bookingDate, $customer);
        $bookingIds = collect($holdResult['bookings'])->pluck('id')->toArray();

        // Checkout gagal (bukti EDC dipakai ulang, stok habis, shift belum dibuka, dll.) → lepas lagi slot yang barusan
        // ditahan. Dulu slot tetap terkunci selama waktu tahan (sampai 30 menit) dan kasir yang mencoba ulang mendapat
        // "slot sedang di-hold pemain lain".
        try {
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
                }

                // Satu RRN / approval code hanya boleh melunasi satu transaksi (dulu walk-in tidak pernah dicek).
                \App\Services\Pos\PosPaymentProof::assertProofNotReused($payloadLog);

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
        } catch (\Throwable $e) {
            try {
                $this->releaseSlots($bookingIds, $customer, onlyLocked: true);
            } catch (\Throwable $releaseError) {
                report($releaseError); // jangan menutupi error checkout aslinya
            }

            throw $e;
        }
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
     * Terapkan voucher jam sponsor corporate (App\Models\Sponsor\SponsorMemberVoucher) pada
     * koleksi booking lapangan padel — mirip applyMembershipBenefitToCourtBookings() di atas,
     * tapi sumber jamnya voucher yang dirilis PIC ke karyawan (bukan kartu membership pribadi).
     *
     * Setiap voucher yang belum expired & masih ada sisa jam dianggap "usable", diurutkan yang
     * PALING CEPAT EXPIRED dipakai lebih dulu (FIFO) supaya jam tidak hangus sia-sia. 1 booking
     * BOLEH memotong dari lebih dari 1 voucher sekaligus kalau perlu (mis. voucher A sisa 0.5 jam,
     * voucher B sisa 5 jam, booking butuh 1.5 jam) — kolom sponsor_member_voucher_id di booking
     * cuma mencatat voucher PERTAMA yang kepotong untuk booking itu (buat tampilan/telusur, bukan
     * audit trail lengkap; total jam yang benar-benar terpakai tetap akurat di kolom
     * sponsor_hours_consumed & masing-masing voucher.hours_used).
     *
     * All-or-nothing per booking: kalau total sisa jam voucher TIDAK CUKUP buat nutup 1 booking
     * penuh, booking itu dilewati (court_fee tidak berubah) — tidak ada potongan diskon parsial
     * seperti membership individual (voucher sponsor tidak punya skema diskon persentase).
     */
    protected function applySponsorVoucherBenefitToCourtBookings(
        iterable $bookings,
        User $user,
        ?string $sponsorVoucherId = null,
        bool $commit = true
    ): array {
        $noBenefit = [
            'organization_name' => null,
            'plan_name' => null,
            'hours_consumed' => 0.0,
            'total_court_discount' => 0.0,
            'benefit_type' => 'NONE',
            'remaining_hours_after' => null,
            'rejected_reason' => null,
        ];

        if ($sponsorVoucherId === 'none' || $sponsorVoucherId === 'NONE') {
            return $noBenefit;
        }

        $member = \App\Models\Sponsor\SponsorOrganizationMember::where('user_id', $user->id)
            ->where('status', 'ACTIVE')
            ->first();

        if (! $member) {
            return $noBenefit;
        }

        $voucherQuery = \App\Models\Sponsor\SponsorMemberVoucher::where('sponsor_organization_member_id', $member->id)
            ->where('expires_at', '>', now())
            ->orderBy('expires_at');

        // Kalau customer secara eksplisit pilih 1 voucher tertentu (bukan auto-detect), batasi ke
        // voucher itu saja — mirip parameter membershipBalanceId di atas.
        if ($sponsorVoucherId) {
            $voucherQuery->where('id', $sponsorVoucherId);
        }

        $vouchers = $commit ? $voucherQuery->lockForUpdate()->get() : $voucherQuery->get();
        $usable = $vouchers->filter(fn ($v) => $v->remainingHours() > 0)->values();

        if ($usable->isEmpty()) {
            return $noBenefit;
        }

        $totalHoursConsumed = 0.0;
        $totalCourtDiscount = 0.0;
        $benefitType = 'NONE';

        foreach ($bookings as $booking) {
            $durationMinutes = \Carbon\Carbon::parse($booking->start_time)->diffInMinutes(\Carbon\Carbon::parse($booking->end_time));
            $hoursNeeded = max(0.5, round($durationMinutes / 60, 2));

            $totalAvailable = round((float) $usable->sum(fn ($v) => $v->remainingHours()), 2);
            if ($totalAvailable <= 0) {
                // Voucher sudah habis (dipakai booking lain dalam keranjang yang sama, atau memang
                // sisa 0) — booking ini dilewati sepenuhnya, bayar normal.
                continue;
            }

            // PARTIAL COVERAGE: kalau sisa jam voucher lebih SEDIKIT dari yang dibutuhkan booking
            // ini (mis. sisa 6 jam buat booking 7 jam), tetap pakai semampunya (6 jam gratis) dan
            // customer cuma bayar sisanya (1 jam) — bukan all-or-nothing seperti sebelumnya, yang
            // bikin voucher kelihatan "tidak aktif" padahal customer masih punya sisa jam.
            $coveredHours = min($totalAvailable, $hoursNeeded);

            $remainingToConsume = $coveredHours;
            $firstVoucherId = null;

            foreach ($usable as $voucher) {
                if ($remainingToConsume <= 0) {
                    break;
                }

                $availableHere = $voucher->remainingHours();
                if ($availableHere <= 0) {
                    continue;
                }

                $take = min($availableHere, $remainingToConsume);
                $voucher->hours_used = round((float) $voucher->hours_used + $take, 2);

                if ($commit) {
                    $voucher->save();
                }

                $remainingToConsume = round($remainingToConsume - $take, 2);
                $firstVoucherId ??= $voucher->id;
            }

            $originalCourtFee = (float) $booking->court_fee;
            // Proporsional terhadap durasi: voucher cuma nutup court_fee versi 1 jam kali jumlah
            // jam yang benar-benar ke-cover, sisanya (kalau ada) tetap ditagih normal.
            $discount = $coveredHours >= $hoursNeeded
                ? $originalCourtFee
                : min($originalCourtFee, round(($originalCourtFee / $hoursNeeded) * $coveredHours, 2));

            if ($commit) {
                $booking->sponsor_organization_id = $member->sponsor_organization_id;
                $booking->sponsor_member_voucher_id = $firstVoucherId;
                $booking->sponsor_hours_consumed = $coveredHours;
                $booking->sponsor_discount_court = $discount;
                $booking->court_fee = round($originalCourtFee - $discount, 2);
                $booking->total_amount = max(0, (float) $booking->total_amount - $discount);
                $booking->save();
            }

            $totalHoursConsumed += $coveredHours;
            $totalCourtDiscount += $discount;
            $benefitType = 'HOURS';
        }

        return [
            'organization_name' => $member->organization->name ?? null,
            'plan_name' => $member->organization->userMembership->plan->name ?? null,
            'hours_consumed' => $totalHoursConsumed,
            'total_court_discount' => $totalCourtDiscount,
            'benefit_type' => $benefitType,
            'remaining_hours_after' => round((float) $usable->sum(fn ($v) => $v->remainingHours()), 2),
            'rejected_reason' => null,
        ];
    }

    /**
     * Preview (read-only, tanpa efek samping) benefit membership untuk booking yang sudah di-hold,
     * dipakai halaman "Payment Details & Checkout" (online) maupun POS walk-in agar customer/kasir
     * tahu ada potongan SEBELUM menekan tombol bayar — bukan baru ketahuan setelah checkout dieksekusi.
     */
    public function previewMembershipBenefit(
        array $bookingIds,
        User $user,
        ?string $membershipBalanceId = null,
        ?string $sponsorVoucherId = null
    ): array {
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

        // Preview voucher sponsor dihitung SECARA TERPISAH (bukan berantai dari sisa hasil membership
        // di atas), karena mode preview tidak benar-benar memotong court_fee per booking (lihat
        // applyMembershipBenefitToCourtBookings, commit:false tidak menyentuh $booking->court_fee).
        // Edge case customer yang PUNYA KEDUANYA (membership pribadi + voucher sponsor) bisa sedikit
        // melebih-lebihkan potensi potongan di preview; commit sesungguhnya (checkout()) tetap benar
        // 100% karena berjalan berurutan terhadap court_fee yang sudah nyata berkurang.
        $sponsorResult = $this->applySponsorVoucherBenefitToCourtBookings($bookings, $user, $sponsorVoucherId, commit: false);
        $sponsorProjectedCourtTotal = max(0, $courtSubtotal - $sponsorResult['total_court_discount']);

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
            'sponsor_voucher_benefit' => [
                'has_benefit' => $sponsorResult['benefit_type'] !== 'NONE',
                'organization_name' => $sponsorResult['organization_name'],
                'plan_name' => $sponsorResult['plan_name'],
                'hours_to_consume' => $sponsorResult['hours_consumed'],
                'remaining_hours_after' => $sponsorResult['remaining_hours_after'],
                'court_discount_amount' => $sponsorResult['total_court_discount'],
                'court_subtotal' => $courtSubtotal,
                'projected_court_total' => $sponsorProjectedCourtTotal,
            ],
        ];
    }
}
