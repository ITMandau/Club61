<?php

namespace App\Http\Controllers\Api\V1\Pos;

use App\Http\Controllers\Controller;
use App\Models\Padel\PadelBooking;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Services\Payment\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    /**
     * Webhook Handler for Midtrans / Xendit.
     * Idempotent & secure with Signature Key.
     * Unified handler: Supports POS Orders & Padel Bookings.
     */
    public function webhook(Request $request): JsonResponse
    {
        $orderId = $request->input('order_id');
        $statusCode = $request->input('status_code');
        $grossAmount = $request->input('gross_amount');
        $signature = $request->input('signature_key');
        $transactionStatus = $request->input('transaction_status');

        if (! $orderId || ! $statusCode || ! $grossAmount || ! $signature) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter notifikasi webhook tidak lengkap.',
            ], 400);
        }

        // 🛡️ QA DEFENSE: Verifikasi SHA-512 Signature Anti-Spoofing via MidtransService (DRY)
        $isValidSignature = MidtransService::verifySignature($orderId, $statusCode, $grossAmount, $signature);

        // Izinkan test ping Midtrans dashboard jika pada sandbox/dev environment
        $isTestPing = (! config('services.midtrans.is_production', false))
            && (str_starts_with(strtolower($orderId), 'test') || str_contains(strtolower($orderId), 'sample'));

        if (! $isValidSignature && ! $isTestPing) {
            Log::warning("🚨 POS WEBHOOK SPOOFING ATTEMPT REJECTED: Invalid signature for Order {$orderId}", [
                'ip' => $request->ip(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak: Signature Key tidak valid (Spoofing rejected).',
            ], 400);
        }

        // 1. Update POS payment & order if exists
        $payment = Payment::where('transaction_id', $orderId)->first();
        if ($payment) {
            $payment->update([
                'status' => in_array($transactionStatus, ['capture', 'settlement']) ? 'SUCCESS' : 'FAILED',
                'payload_log' => $request->all(),
            ]);

            if ($payment->order) {
                $payment->order->update([
                    'payment_status' => $payment->status === 'SUCCESS' ? 'PAID' : 'UNPAID',
                ]);
            }
        }

        $incomingOrderId = $orderId;
        $realOrderId = explode('_', $incomingOrderId)[0];

        // 2. Update Padel Booking if exists (Unified Gateway Support)
        $bookings = PadelBooking::where('order_id', $incomingOrderId)
            ->orWhere('order_id', $realOrderId)
            ->get();
        if ($bookings->isEmpty()) {
            $bookingIds = Cache::get("order_bookings:{$incomingOrderId}") ?? Cache::get("order_bookings:{$realOrderId}");
            if (! empty($bookingIds) && is_array($bookingIds)) {
                $bookings = PadelBooking::whereIn('id', $bookingIds)->get();
            } else {
                $bookings = PadelBooking::where('booking_code', $incomingOrderId)
                    ->orWhere('booking_code', $realOrderId)
                    ->get();
            }
        }

        if (! $bookings->isEmpty()) {
            $fraudStatus = $request->input('fraud_status', 'accept');
            $newStatus = match ($transactionStatus) {
                'capture' => ($fraudStatus === 'challenge') ? 'PENDING_PAYMENT' : 'PAID',
                'settlement' => 'PAID',
                'pending' => 'PENDING_PAYMENT',
                'deny', 'expire', 'cancel' => 'CANCELLED',
                default => null,
            };

            if ($newStatus) {
                foreach ($bookings as $booking) {
                    $booking->update(['status' => $newStatus]);

                    if ($newStatus === 'PAID' && empty($booking->qr_code_hash)) {
                        $booking->update([
                            'qr_code_hash' => 'VNT-TICKET-' . strtoupper(bin2hex(random_bytes(16))),
                        ]);
                    }
                }

                // 🛡️ QA DEFENSE: Idempotent Payment & Order Recording for Analytics & Financial Auditing
                $paymentStatus = match ($newStatus) {
                    'PAID' => 'SUCCESS',
                    'CANCELLED' => 'FAILED',
                    default => 'PENDING',
                };

                $primaryBooking = $bookings->first();
                $order = Order::where('order_number', $realOrderId)
                    ->orWhere('order_number', $incomingOrderId)
                    ->first();

                if (! $order) {
                    $order = Order::create([
                        'order_number' => $realOrderId,
                        'user_id' => $primaryBooking->user_id,
                        'order_type' => 'ONLINE_BOOKING',
                        'subtotal' => $bookings->sum('total_amount'),
                        'grand_total' => (float) ($grossAmount ?: $bookings->sum('total_amount')),
                        'payment_status' => ($newStatus === 'PAID') ? 'PAID' : 'PENDING',
                    ]);
                } else {
                    $targetPaymentStatus = ($newStatus === 'PAID') ? 'PAID' : 'PENDING';
                    if ($order->payment_status !== $targetPaymentStatus) {
                        $order->update(['payment_status' => $targetPaymentStatus]);
                    }
                }

                foreach ($bookings as $b) {
                    if ($b->order_id !== $order->id && $b->order_id !== $realOrderId) {
                        $b->update(['order_id' => $order->id]);
                    }
                }

                $paymentType = $request->input('payment_type', 'qris');
                $paymentMethod = match (strtolower((string) $paymentType)) {
                    'qris', 'gopay', 'shopeepay' => 'QRIS',
                    'bank_transfer', 'echannel' => 'BANK_TRANSFER',
                    'credit_card' => 'CREDIT_CARD',
                    'cst' => 'CASH',
                    default => strtoupper((string) $paymentType),
                };

                Payment::updateOrCreate(
                    ['transaction_id' => $incomingOrderId],
                    [
                        'order_id' => $order->id,
                        'payment_gateway' => 'MIDTRANS',
                        'amount' => (float) ($grossAmount ?: $bookings->sum('total_amount')),
                        'payment_method' => $paymentMethod,
                        'status' => $paymentStatus,
                        'payload_log' => $request->all(),
                    ]
                );
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Webhook received and processed.',
        ]);
    }

    /**
     * Developer Simulator for Local Testing (Flutter testing without real money).
     */
    public function simulate(Request $request): JsonResponse
    {
        // 🛡️ Guardrail Keamanan: Cegah eksekusi simulator pada lingkungan produksi
        if (! app()->environment('local', 'testing')) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak: Simulator endpoint dinonaktifkan pada server produksi demi keamanan.',
            ], 403);
        }

        $validated = $request->validate([
            'booking_id' => ['required_without:order_id', 'nullable', 'string'],
            'order_id' => ['required_without:booking_id', 'nullable', 'string'],
        ]);

        if (! empty($validated['booking_id'])) {
            $booking = PadelBooking::findOrFail($validated['booking_id']);
            $booking->update(['status' => 'PAID']);

            return response()->json([
                'success' => true,
                'message' => 'Simulasi pembayaran booking padel BERHASIL. Status kini PAID.',
                'data' => $booking,
            ]);
        }

        $order = Order::findOrFail($validated['order_id']);
        $order->update(['payment_status' => 'PAID']);

        return response()->json([
            'success' => true,
            'message' => 'Simulasi pembayaran order BERHASIL. Status kini PAID.',
            'data' => $order,
        ]);
    }
}
