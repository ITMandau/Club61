<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use App\Models\Padel\PadelBooking;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Services\Payment\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MidtransWebhookController extends Controller
{
    protected MidtransService $midtransService;

    public function __construct(MidtransService $midtransService)
    {
        $this->midtransService = $midtransService;
    }

    /**
     * Handle incoming payment notification webhook from Midtrans.
     *
     * 🛡️ QA DEFENSE 1: Verifikasi Ketat Signature Key (Anti-Spoofing).
     */
    public function handle(Request $request): JsonResponse
    {
        $orderId = $request->input('order_id');
        $statusCode = $request->input('status_code');
        $grossAmount = $request->input('gross_amount');
        $incomingSignature = $request->input('signature_key');
        $transactionStatus = $request->input('transaction_status');
        $fraudStatus = $request->input('fraud_status');

        if (! $orderId || ! $statusCode || ! $grossAmount || ! $incomingSignature) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter notifikasi Midtrans tidak lengkap.',
            ], 400);
        }

        // 🛡️ QA DEFENSE 1: Validasi Signature Key SHA512 anti-spoofing
        if (! $this->midtransService->verifySignature($orderId, $statusCode, $grossAmount, $incomingSignature)) {
            Log::warning("🚨 MIDTRANS SPOOFING ATTEMPT DETECTED: Invalid signature for Order {$orderId}", [
                'ip' => $request->ip(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak: Signature Key tidak valid (Spoofing rejected).',
            ], 400);
        }

        Log::info("Midtrans Webhook verified for Order {$orderId}: Status = {$transactionStatus}");

        $incomingOrderId = $orderId;
        $realOrderId = explode('_', $incomingOrderId)[0];

        // Temukan booking dari Database berdasarkan incomingOrderId, realOrderId, Cache, atau booking_code
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

        if ($bookings->isEmpty()) {
            Log::warning("Midtrans Webhook: No bookings found for order {$incomingOrderId} (Real: {$realOrderId})");
            return response()->json([
                'success' => true,
                'message' => 'Webhook diterima, tetapi data pesanan tidak ditemukan.',
            ]);
        }

        // Status State Machine Midtrans
        $newBookingStatus = null;
        if ($transactionStatus === 'capture') {
            if ($fraudStatus === 'challenge') {
                $newBookingStatus = 'PENDING_PAYMENT';
            } elseif ($fraudStatus === 'accept') {
                $newBookingStatus = 'PAID';
            }
        } elseif ($transactionStatus === 'settlement') {
            // Lunas (QRIS, VA Transfer Sukses)
            $newBookingStatus = 'PAID';
        } elseif ($transactionStatus === 'pending') {
            // Menunggu pembayaran (VA / QRIS baru di-generate)
            $newBookingStatus = 'PENDING_PAYMENT';
        } elseif (in_array($transactionStatus, ['deny', 'expire', 'cancel'])) {
            // Batal / Kedaluwarsa
            $newBookingStatus = 'CANCELLED';
        }

        if ($newBookingStatus) {
            $this->updateBookingsStatus($bookings, $newBookingStatus);

            // 🛡️ QA DEFENSE: Idempotent Payment & Order Recording for Analytics & Financial Auditing
            $paymentStatus = match ($newBookingStatus) {
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
                    'payment_status' => ($newBookingStatus === 'PAID') ? 'PAID' : 'PENDING',
                ]);
            } else {
                $targetStatus = ($newBookingStatus === 'PAID') ? 'PAID' : 'PENDING';
                if ($order->payment_status !== $targetStatus) {
                    $order->update(['payment_status' => $targetStatus]);
                }
            }

            // Hubungkan seluruh booking ke Order murni
            foreach ($bookings as $b) {
                if ($b->order_id !== $order->id && $b->order_id !== $realOrderId) {
                    $b->update(['order_id' => $order->id]);
                }
            }

            $paymentType = $request->input('payment_type', 'qris');
            $paymentTypeLower = strtolower((string) $paymentType);

            if ($paymentTypeLower === 'bank_transfer') {
                $vaNumbers = $request->input('va_numbers', []);
                if (!empty($vaNumbers) && isset($vaNumbers[0]['bank'])) {
                    $bank = strtoupper($vaNumbers[0]['bank']);
                    $paymentMethod = "{$bank}_VA";
                } elseif ($request->filled('permata_va_number')) {
                    $paymentMethod = 'PERMATA_VA';
                } else {
                    $paymentMethod = 'BANK_TRANSFER';
                }
            } elseif ($paymentTypeLower === 'echannel') {
                $paymentMethod = 'MANDIRI_VA';
            } else {
                $paymentMethod = match ($paymentTypeLower) {
                    'qris', 'gopay', 'shopeepay' => 'QRIS',
                    'credit_card' => 'CREDIT_CARD',
                    'cst' => 'CASH',
                    default => strtoupper((string) $paymentType),
                };
            }

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

            // Jika pembayaran berhasil, selesaikan juga supplemental payment berstatus PENDING di bawah order ini
            if ($paymentStatus === 'SUCCESS') {
                Payment::where('order_id', $order->id)
                    ->where('status', 'PENDING')
                    ->update([
                        'status' => 'SUCCESS',
                        'payment_gateway' => 'MIDTRANS',
                        'payment_method' => $paymentMethod,
                    ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Status pesanan berhasil disinkronkan dari Midtrans.',
        ]);
    }

    protected function updateBookingsStatus($bookings, string $status): void
    {
        foreach ($bookings as $booking) {
            $booking->update([
                'status' => $status,
            ]);

            // Jika lunas, pastikan QR Code turnstile terisi dengan HMAC valid
            if ($status === 'PAID' && empty($booking->qr_code_hash)) {
                $hash = hash_hmac(
                    'sha256',
                    $booking->booking_code . $booking->user_id . $booking->court_id . ($booking->start_time ? $booking->start_time->toISOString() : ''),
                    config('app.key')
                );
                $booking->update([
                    'qr_code_hash' => $hash ?: ('VNT-TICKET-' . strtoupper(bin2hex(random_bytes(16)))),
                ]);
            }
        }
    }
}
