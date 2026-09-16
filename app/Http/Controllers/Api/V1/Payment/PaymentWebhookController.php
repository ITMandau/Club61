<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use App\Models\Padel\PadelBooking;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
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
     * Handler webhook universal untuk driver pembayaran (midtrans, mock).
     */
    public function handle(string $driver, Request $request): JsonResponse
    {
        if (strtolower($driver) === 'mock' && app()->environment('production')) {
            return response()->json([
                'success' => false,
                'message' => 'Driver simulasi mock dinonaktifkan pada environment production.',
            ], 403);
        }

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
            Log::warning("[ALERT] PAYMENT WEBHOOK SPOOFING DETECTED [{$driver}]: {$result['message']}", [
                'ip' => $request->ip(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }

        $incomingOrderId = $result['order_id'];
        $realOrderId = explode('_', $incomingOrderId)[0];
        $status = $result['status'];
        $grossAmount = $result['gross_amount'] ?? 0;

        Log::info("Payment Webhook verified for Driver [{$driver}], Order {$incomingOrderId} (Real: {$realOrderId}): Status = {$status}");

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

                if ($newStatus === 'PAID') {
                    $voucherCode = Cache::pull("order_voucher:{$realOrderId}") ?? Cache::pull("order_voucher:{$incomingOrderId}");
                    if ($voucherCode) {
                        \App\Models\Pos\Voucher::where('code', $voucherCode)
                            ->where(function ($q) {
                                $q->whereNull('quota')->orWhere('quota', '>', 0);
                            })
                            ->decrement('quota');

                        \App\Models\Pos\Voucher::where('code', $voucherCode)->increment('used_count');
                    }
                }

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
                    $targetStatus = ($newStatus === 'PAID') ? 'PAID' : 'PENDING';
                    if ($order->payment_status !== $targetStatus) {
                        $order->update(['payment_status' => $targetStatus]);
                    }
                }

                foreach ($bookings as $b) {
                    if ($b->order_id !== $order->id && $b->order_id !== $realOrderId) {
                        $b->update(['order_id' => $order->id]);
                    }
                }

                $paymentStatus = match ($newStatus) {
                    'PAID' => 'SUCCESS',
                    'CANCELLED' => 'FAILED',
                    default => 'PENDING',
                };

                Payment::updateOrCreate(
                    ['transaction_id' => $incomingOrderId],
                    [
                        'order_id' => $order->id,
                        'payment_gateway' => strtoupper($driver),
                        'amount' => (float) ($grossAmount ?: $bookings->sum('total_amount')),
                        'payment_method' => strtoupper($driver),
                        'status' => $paymentStatus,
                        'payload_log' => $request->all(),
                    ]
                );
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Webhook {$driver} diproses dengan sukses.",
            'data' => [
                'driver' => $driver,
                'order_id' => $incomingOrderId,
                'status' => $status,
            ],
        ]);
    }
}
