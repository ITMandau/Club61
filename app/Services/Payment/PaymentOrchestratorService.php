<?php

namespace App\Services\Payment;

use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\Pos\PosCashierShift;
use App\Models\Pos\Refund;
use App\Models\Pos\Voucher;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentOrchestratorService
{
    public function __construct(
        protected PaymentFulfillmentRegistry $registry
    ) {}

    /**
     * Memproses pelunasan pesanan secara terpusat, idempoten, dan mendelegasikan pemenuhan domain.
     */
    public function markOrderAsPaid(Order $order, array $paymentDetails = []): void
    {
        DB::transaction(function () use ($order, $paymentDetails) {
            $order = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();

            $transactionId = $paymentDetails['transaction_id'] ?? null;
            $paymentGateway = $paymentDetails['payment_gateway'] ?? 'MIDTRANS';
            $paymentMethod = $paymentDetails['payment_method'] ?? 'QRIS';
            $amount = isset($paymentDetails['amount']) ? (float) $paymentDetails['amount'] : (float) $order->grand_total;
            $payloadLog = $paymentDetails['payload_log'] ?? null;

            // Validasi sesi shift kasir jika pembayaran dilakukan via loket kasir POS atau pembayaran tunai staf
            $posShiftId = null;
            $isPosGateway = strtoupper($paymentGateway) === 'CASHIER_POS' || (strtoupper($paymentGateway) === 'CASH' && isset($paymentDetails['counter']));
            if ($isPosGateway) {
                $counter = $paymentDetails['counter'] ?? 'PADEL_FRONTDESK';
                $activeShift = PosCashierShift::getActiveShift($counter);
                $userCandidate = auth()->user() ?? ($paymentDetails['user'] ?? ($paymentDetails['cashier_user'] ?? ($paymentDetails['admin_user'] ?? null)));
                $isSuperAdmin = $userCandidate && method_exists($userCandidate, 'hasRole') && $userCandidate->hasRole('super_admin');

                if (! $activeShift && ! $isSuperAdmin) {
                    throw new \Exception("Tidak ada shift kasir yang aktif untuk loket [{$counter}]. Silakan buka shift terlebih dahulu.");
                }

                $posShiftId = $activeShift?->id;
            }

            // 1. Transaction-Level Idempotency Guard
            $payment = null;
            if ($transactionId) {
                $payment = Payment::where('transaction_id', $transactionId)->first();
            }

            if (! $payment) {
                $payment = Payment::where('order_id', $order->id)
                    ->where('status', 'PENDING')
                    ->latest()
                    ->first();
            }

            // Jika transaksi spesifik ini sudah berstatus SUCCESS, berarti notifikasi/request ini duplikat
            if ($payment && $payment->status === 'SUCCESS') {
                return;
            }

            // Anti-race: settle call TANPA transaction_id (kasir "Settle Cash" / walk-in, bukan webhook
            // Midtrans atau supplemental delta reschedule yang selalu bawa transaction_id unik) yang sampai
            // titik ini artinya TIDAK menemukan payment PENDING untuk dilunasi. Kalau order sudah PAID,
            // ini pasti settle duplikat dari request lain yang barusan commit duluan (mis. 2 kasir menekan
            // "Settle Cash" hampir bersamaan untuk 2 booking berbeda yang kebetulan satu order yang sama) —
            // BUKAN pembayaran baru yang sah, karena pembayaran baru yang sah selalu datang lewat salah satu
            // dari dua jalur: transaction_id unik (webhook/delta reschedule), atau match payment PENDING
            // yang sudah ada (checkout awal / supplemental payment). Tanpa guard ini request kedua akan
            // membuat Payment SUCCESS baru dari nol (double revenue record) dan memotong kuota voucher lagi.
            if (! $transactionId && ! $payment && $order->payment_status === 'PAID') {
                Log::info("Duplicate settle attempt diabaikan untuk order [{$order->order_number}]: tidak ada payment PENDING tersisa dan order sudah PAID.");
                return;
            }

            // 2. Proteksi Late Settlement: Jika pesanan sebelumnya telah dibatalkan (CANCELLED)
            if ($order->payment_status === 'CANCELLED') {
                $paymentUpdates = [
                    'payment_gateway' => strtoupper($paymentGateway),
                    'transaction_id' => $transactionId ?: ($payment ? $payment->transaction_id : null),
                    'payment_method' => strtoupper($paymentMethod),
                    'amount' => $amount ?: (float) ($payment ? $payment->amount : 0),
                    'status' => 'SUCCESS',
                    'payload_log' => is_array($payloadLog)
                        ? array_merge($payment?->payload_log ?? [], $payloadLog)
                        : ($payloadLog ?: $payment?->payload_log),
                ];
                if ($posShiftId) {
                    $paymentUpdates['pos_shift_id'] = $posShiftId;
                }

                if ($payment) {
                    $payment->update($paymentUpdates);
                } else {
                    $paymentData = [
                        'order_id' => $order->id,
                        'payment_gateway' => strtoupper($paymentGateway),
                        'transaction_id' => $transactionId ?: ('POS-' . strtoupper($paymentGateway) . '-' . strtoupper(Str::random(10))),
                        'payment_method' => strtoupper($paymentMethod),
                        'amount' => $amount,
                        'status' => 'SUCCESS',
                        'payload_log' => $payloadLog,
                    ];
                    if ($posShiftId) {
                        $paymentData['pos_shift_id'] = $posShiftId;
                    }
                    $payment = Payment::create($paymentData);
                }

                if ($posShiftId && ! $order->pos_shift_id) {
                    $order->update(['pos_shift_id' => $posShiftId]);
                }

                // Set order payment_status = PAID sesuai fakta finansial bahwa uang sah diterima
                $order->update(['payment_status' => 'PAID']);

                // Buat entri refund resmi berstatus PENDING agar kasir/admin dapat memproses pengembalian
                Refund::create([
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                    'refund_amount' => $amount ?: (float) $payment->amount,
                    'reason' => 'Late payment settlement on cancelled order. Court slot was already released.',
                    'status' => 'PENDING',
                ]);

                Log::warning("Late settlement diterima untuk pesanan yang telah dibatalkan [{$order->order_number}]. Dana dicatat dan dibuatkan entri Refund PENDING tanpa aktivasi slot lapangan.");
                $this->logPayment($order, $payment, $paymentDetails, lateOnCancelledOrder: true);
                return;
            }

            // 2. Update atau create record pembayaran
            $paymentUpdates = [
                'payment_gateway' => strtoupper($paymentGateway),
                'transaction_id' => $transactionId ?: ($payment ? $payment->transaction_id : null),
                'payment_method' => strtoupper($paymentMethod),
                'amount' => $amount ?: (float) ($payment ? $payment->amount : 0),
                'status' => 'SUCCESS',
                'payload_log' => is_array($payloadLog)
                    ? array_merge($payment?->payload_log ?? [], $payloadLog)
                    : ($payloadLog ?: $payment?->payload_log),
            ];
            if ($posShiftId) {
                $paymentUpdates['pos_shift_id'] = $posShiftId;
            }

            $paidBefore = (float) $order->payments()->where('status', 'SUCCESS')->sum('amount');

            if ($payment) {
                $payment->update($paymentUpdates);
            } else {
                $paymentData = [
                    'order_id' => $order->id,
                    'payment_gateway' => strtoupper($paymentGateway),
                    'transaction_id' => $transactionId ?: ('POS-' . strtoupper($paymentGateway) . '-' . strtoupper(Str::random(10))),
                    'payment_method' => strtoupper($paymentMethod),
                    'amount' => $amount,
                    'status' => 'SUCCESS',
                    'payload_log' => $payloadLog,
                ];
                if ($posShiftId) {
                    $paymentData['pos_shift_id'] = $posShiftId;
                }
                $payment = Payment::create($paymentData);
            }

            if ($posShiftId && ! $order->pos_shift_id) {
                $order->update(['pos_shift_id' => $posShiftId]);
            }

            // 3. Evaluasi status finansial Order (PAID vs PARTIALLY_PAID)
            $totalPaid = (float) $order->payments()->where('status', 'SUCCESS')->sum('amount');
            $grandTotal = (float) $order->grand_total;

            // Pembayaran yang masuk padahal order SUDAH lunas (mis. customer bayar online lalu juga dibayar di
            // kasir, atau webhook telat) = kelebihan bayar. Catat refund PENDING + log KRITIS supaya uangnya
            // dikembalikan — jangan diam-diam jadi omzet.
            $overpaid = round(min((float) $payment->amount, $totalPaid - $grandTotal), 2);
            if ($grandTotal > 0 && $paidBefore >= $grandTotal - 1 && $overpaid > 1) {
                Refund::create([
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                    'refund_amount' => $overpaid,
                    'reason' => 'Kelebihan bayar: pembayaran '.strtoupper($paymentGateway).' ('.($payment->transaction_id ?? '-').') masuk saat order sudah lunas. Kembalikan ke customer.',
                    'status' => 'PENDING',
                ]);

                ActivityLogger::record(
                    module: 'FINANCE',
                    event: 'payment.overpaid',
                    description: 'KELEBIHAN BAYAR '.ActivityLogger::rupiah($overpaid)." pada order {$order->order_number} (dibayar dua kali) — refund PENDING dibuat",
                    subject: $order,
                    meta: [
                        'no_order' => $order->order_number,
                        'kelebihan' => $overpaid,
                        'gateway' => $paymentGateway,
                        'id_transaksi' => $payment->transaction_id,
                        'total_order' => $grandTotal,
                        'total_diterima' => $totalPaid,
                    ],
                    severity: ActivityLogger::CRITICAL,
                );
            }

            if ($totalPaid >= $grandTotal) {
                $order->update(['payment_status' => 'PAID']);
            } elseif ($totalPaid > 0) {
                $order->update(['payment_status' => 'PARTIALLY_PAID']);
            }

            // 4. Atomic decrement kuota voucher jika terpasang
            if ($order->voucher_code) {
                Voucher::where('code', $order->voucher_code)
                    ->where(function ($q) {
                        $q->whereNull('quota')->orWhere('quota', '>', 0);
                    })
                    ->decrement('quota');

                Voucher::where('code', $order->voucher_code)->increment('used_count');
            }

            // 5. Delegasi pemenuhan domain secara dinamis via registry
            $itemsByType = $order->items->groupBy('item_type');

            foreach ($itemsByType as $itemType => $items) {
                if ($this->registry->hasHandler($itemType)) {
                    $handler = $this->registry->getHandler($itemType);
                    $handler->fulfill($order, $items);
                }
            }

            // Fallback pemenuhan jika pesanan terhubung langsung ke PadelBooking (misal: online booking / lazy order)
            if (! $itemsByType->has('PADEL') && $this->registry->hasHandler('PADEL')) {
                if ($order->padelBookings()->exists() || \App\Models\Padel\PadelBooking::where('order_id', $order->id)->orWhere('order_id', $order->order_number)->exists()) {
                    $this->registry->getHandler('PADEL')->fulfill($order, collect());
                }
            }

            // 6. Bersihkan cache transien terkait
            Cache::forget("order_voucher:{$order->order_number}");
            Cache::forget("order_voucher:{$order->id}");
            Cache::forget("order_bookings:{$order->order_number}");
            Cache::forget("order_bookings:{$order->id}");
            Cache::forget('kelola_pemesanan_tab_counts');

            // 7. Jejak audit — SATU titik untuk semua transaksi lunas di semua modul (walk-in padel,
            // booking online, F&B, membership, pelunasan kasir, rekonsiliasi Midtrans). Di dalam
            // transaksi yang sama: kalau pelunasan gagal & di-rollback, log-nya ikut hilang.
            $this->logPayment($order, $payment, $paymentDetails);
        });
    }

    private const ITEM_TYPE_LABELS = [
        'FNB' => 'F&B',
        'PADEL' => 'Padel',
        'EQUIPMENT' => 'Sewa Alat',
        'MEMBERSHIP' => 'Membership',
        'WELLNESS' => 'Wellness',
        'GYM' => 'Gym',
        'MERCH' => 'Merchandise',
    ];

    private const COUNTER_LABELS = [
        'PADEL_FRONTDESK' => 'Frontdesk Padel',
        'FNB_COUNTER' => 'Kasir F&B',
        'MEMBERSHIP_DESK' => 'Meja Membership',
        'ONLINE_PORTAL' => 'Portal Online',
    ];

    private function logPayment(Order $order, Payment $payment, array $details, bool $lateOnCancelledOrder = false): void
    {
        $order->loadMissing(['items', 'user']);
        $payload = is_array($details['payload_log'] ?? null) ? $details['payload_log'] : [];

        $types = $order->items->pluck('item_type')->unique()
            ->map(fn ($type) => self::ITEM_TYPE_LABELS[$type] ?? $type)->implode(' + ');
        $kind = $types !== '' ? $types : match ($order->order_type) {
            'ONLINE_BOOKING' => 'Booking Padel Online',
            'WALK_IN' => 'Walk-in Padel',
            default => (string) $order->order_type,
        };

        $bookingCodes = \App\Models\Padel\PadelBooking::where('order_id', $order->id)->limit(20)->pluck('booking_code')->all();
        $counter = $details['counter'] ?? null;
        $reconciled = $payload['reconciled_via'] ?? null;
        $amount = (float) $payment->amount;
        $method = (string) $payment->payment_method;
        $isFullyPaid = $order->payment_status === 'PAID';

        $description = $lateOnCancelledOrder
            ? "Uang masuk untuk order {$order->order_number} yang SUDAH DIBATALKAN (".ActivityLogger::rupiah($amount)." via {$method}) — refund PENDING dibuat"
            : 'Transaksi '.$kind.' '.$order->order_number.($isFullyPaid ? ' lunas ' : ' dibayar sebagian ').ActivityLogger::rupiah($amount).' via '.$method
                .($counter ? ' di '.(self::COUNTER_LABELS[$counter] ?? $counter) : '');

        $severity = match (true) {
            $lateOnCancelledOrder => ActivityLogger::CRITICAL,
            $reconciled !== null, strtoupper((string) $payment->payment_gateway) === 'MOCK' => ActivityLogger::WARNING,
            default => ActivityLogger::INFO,
        };

        ActivityLogger::record(
            module: 'FINANCE',
            event: $lateOnCancelledOrder ? 'payment.late_settlement_refund' : ($reconciled ? 'payment.paid_via_reconcile' : 'payment.paid'),
            description: $description,
            subject: $order,
            meta: array_filter([
                'no_order' => $order->order_number,
                'jenis' => $kind,
                'tipe_order' => $order->order_type,
                'loket' => $counter,
                'gateway' => $payment->payment_gateway,
                'metode_bayar' => $method,
                'nominal_dibayar' => $amount,
                'total_order' => (float) $order->grand_total,
                'status_order' => $order->payment_status,
                'id_transaksi' => $payment->transaction_id,
                'pelanggan' => $order->user?->name ?? $order->customer_name,
                'meja' => $order->table_number,
                'no_antrian' => $order->queue_number,
                'kode_booking' => $bookingCodes ?: null,
                'item' => $order->items->take(30)->map(fn ($i) => $i->item_name.' x'.$i->quantity.' = '.ActivityLogger::rupiah((float) $i->subtotal))->values()->all() ?: null,
                'shift_kasir' => $payment->pos_shift_id,
                'dilunasi_lewat' => $reconciled ? 'Cek status Midtrans (webhook tidak masuk)' : null,
            ], fn ($v) => $v !== null && $v !== ''),
            severity: $severity,
        );
    }
}
