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
     * Webhook Handler for Midtrans.
     * Didelegasikan terpusat ke PaymentWebhookController via PaymentOrchestratorService.
     */
    public function webhook(Request $request): JsonResponse
    {
        return app(\App\Http\Controllers\Api\V1\Payment\PaymentWebhookController::class)->handle('midtrans', $request);
    }

    /**
     * Developer Simulator for Local Testing (Flutter testing without real money).
     */
    public function simulate(Request $request): JsonResponse
    {
        // Guardrail Keamanan: Cegah eksekusi simulator pada lingkungan produksi
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
