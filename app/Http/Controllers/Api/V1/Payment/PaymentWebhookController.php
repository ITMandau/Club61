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
        $paymentType = $request->input('payment_type', strtoupper($driver));

        Log::info("Payment Webhook verified for Driver [{$driver}], Order {$incomingOrderId} (Real: {$realOrderId}): Status = {$status}");

        // 1. Temukan Order dari Database (Universal Order Lookup)
        $order = Order::where('order_number', $realOrderId)
            ->orWhere('id', $realOrderId)
            ->orWhere('order_number', $incomingOrderId)
            ->first();

        if (! $order) {
            $matchedPayment = Payment::where('transaction_id', $incomingOrderId)
                ->orWhere('transaction_id', $realOrderId)
                ->first();
            if ($matchedPayment && $matchedPayment->order) {
                $order = $matchedPayment->order;
            }
        }

        // 2. Fallback untuk transaksi lama pra-migrasi jika record Order belum ada
        if (! $order) {
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
                $primaryBooking = $bookings->first();
                $order = Order::create([
                    'order_number' => $realOrderId,
                    'user_id' => $primaryBooking->user_id,
                    'order_type' => 'ONLINE_BOOKING',
                    'subtotal' => $bookings->sum('total_amount'),
                    'grand_total' => (float) ($grossAmount ?: $bookings->sum('total_amount')),
                    'voucher_code' => Cache::get("order_voucher:{$realOrderId}") ?? Cache::get("order_voucher:{$incomingOrderId}"),
                    'payment_status' => 'UNPAID',
                ]);

                foreach ($bookings as $b) {
                    $b->update(['order_id' => $order->id]);
                }
            }
        }

        if ($order) {
            $orchestrator = app(\App\Services\Payment\PaymentOrchestratorService::class);

            if ($status === 'PAID') {
                // Defense-in-depth: signature webhook sudah diverifikasi valid (hash_equals di
                // driver masing-masing), tapi itu cuma membuktikan notifikasi ini datang dari
                // gateway asli — bukan berarti gross_amount yang diklaim webhook otomatis cocok
                // dengan grand_total order di database kita. Selisih besar di sini janggal (order
                // rusak, notifikasi basi/duplikat untuk order yang sudah berubah, dsb.) dan wajib
                // masuk log supaya kelihatan di monitoring, walau proses pelunasan tetap lanjut
                // (grand_total milik kita sendiri yang tetap jadi acuan `payment_status`, bukan
                // klaim gross_amount dari webhook — lihat markOrderAsPaid()).
                if ($order->grand_total !== null && abs((float) $grossAmount - (float) $order->grand_total) > 1) {
                    Log::warning("[ALERT] Webhook gross_amount tidak cocok dengan grand_total order [{$order->order_number}]", [
                        'order_id' => $order->id,
                        'gross_amount_from_webhook' => $grossAmount,
                        'grand_total_in_db' => (float) $order->grand_total,
                        'driver' => $driver,
                    ]);
                }

                $orchestrator->markOrderAsPaid($order, [
                    'payment_gateway' => strtoupper($driver),
                    'transaction_id' => $incomingOrderId,
                    'payment_method' => strtoupper((string) $paymentType),
                    'amount' => (float) ($grossAmount ?: $order->grand_total),
                    'payload_log' => $request->all(),
                ]);
            } elseif ($status === 'CANCELLED') {
                $order->update(['payment_status' => 'CANCELLED']);

                Payment::where('order_id', $order->id)
                    ->where('status', 'PENDING')
                    ->update([
                        'status' => 'FAILED',
                        'payload_log' => $request->all(),
                    ]);

                foreach ($order->padelBookings as $booking) {
                    if (in_array($booking->status, ['PENDING_PAYMENT', 'LOCKED', 'PENDING'], true)) {
                        $booking->update(['status' => 'CANCELLED']);
                    }
                }
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
