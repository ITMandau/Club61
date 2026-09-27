<?php

namespace App\Services\Payment;

use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\Pos\PosCashierShift;
use App\Models\Pos\Refund;
use App\Models\Pos\Voucher;
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
        });
    }
}
