<?php

namespace App\Http\Controllers\Api\V1\Pos;

use App\Http\Controllers\Controller;
use App\Models\Padel\PadelBooking;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Services\Payment\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    /**
     * Webhook Handler for Midtrans / Xendit.
     * Idempotent & secure with Signature Key.
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
        if (! MidtransService::verifySignature($orderId, $statusCode, $grossAmount, $signature)) {
            Log::warning("🚨 POS WEBHOOK SPOOFING ATTEMPT REJECTED: Invalid signature for Order {$orderId}", [
                'ip' => $request->ip(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak: Signature Key tidak valid (Spoofing rejected).',
            ], 400);
        }

        // Update payment log and transition status
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

        return response()->json(['success' => true, 'message' => 'Webhook received and processed.']);
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
