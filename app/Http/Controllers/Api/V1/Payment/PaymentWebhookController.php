<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use App\Models\Padel\PadelBooking;
use App\Services\Payment\PaymentManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    protected PaymentManager $paymentManager;

    public function __construct(PaymentManager $paymentManager)
    {
        $this->paymentManager = $paymentManager;
    }

    /**
     * Handler webhook universal untuk driver pembayaran (midtrans, xendit, mock).
     */
    public function handle(string $driver, Request $request): JsonResponse
    {
        try {
            $gateway = $this->paymentManager->driver($driver);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }

        $result = $gateway->verifyWebhook($request);

        if (! $result['is_valid']) {
            Log::warning("🚨 PAYMENT WEBHOOK SPOOFING DETECTED [{$driver}]: {$result['message']}", [
                'ip' => $request->ip(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }

        $orderId = $result['order_id'];
        $status = $result['status'];

        Log::info("Payment Webhook verified for Driver [{$driver}], Order {$orderId}: Status = {$status}");

        // Temukan booking dari Database berdasarkan order_id, Cache, atau booking_code
        $bookings = PadelBooking::where('order_id', $orderId)->get();

        if ($bookings->isEmpty()) {
            $bookingIds = Cache::get("order_bookings:{$orderId}");
            if (! empty($bookingIds) && is_array($bookingIds)) {
                $bookings = PadelBooking::whereIn('id', $bookingIds)->get();
            } else {
                $bookings = PadelBooking::where('booking_code', $orderId)->get();
            }
        }

        if ($bookings->isNotEmpty()) {
            $newStatus = match ($status) {
                'PAID' => 'PAID',
                'PENDING' => 'PENDING_PAYMENT',
                'CANCELLED' => 'CANCELLED',
                default => null,
            };

            if ($newStatus) {
                foreach ($bookings as $booking) {
                    $updateData = ['status' => $newStatus];
                    if ($newStatus === 'PAID' && empty($booking->qr_code_hash)) {
                        $updateData['qr_code_hash'] = 'VNT-TICKET-' . strtoupper(bin2hex(random_bytes(16)));
                    }
                    $booking->update($updateData);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Webhook {$driver} diproses dengan sukses.",
            'data' => [
                'driver' => $driver,
                'order_id' => $orderId,
                'status' => $status,
            ],
        ]);
    }
}
