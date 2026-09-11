<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use App\Models\Padel\PadelBooking;
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

        if ($bookings->isEmpty()) {
            Log::warning("Midtrans Webhook: No bookings found for order {$orderId}");
            return response()->json([
                'success' => true,
                'message' => 'Webhook diterima, tetapi data pesanan tidak ditemukan.',
            ]);
        }

        // Status State Machine Midtrans
        if ($transactionStatus === 'capture') {
            if ($fraudStatus === 'challenge') {
                $this->updateBookingsStatus($bookings, 'PENDING_PAYMENT');
            } elseif ($fraudStatus === 'accept') {
                $this->updateBookingsStatus($bookings, 'PAID');
            }
        } elseif ($transactionStatus === 'settlement') {
            // Lunas (QRIS, VA Transfer Sukses)
            $this->updateBookingsStatus($bookings, 'PAID');
        } elseif ($transactionStatus === 'pending') {
            // Menunggu pembayaran (VA / QRIS baru di-generate)
            $this->updateBookingsStatus($bookings, 'PENDING_PAYMENT');
        } elseif (in_array($transactionStatus, ['deny', 'expire', 'cancel'])) {
            // Batal / Kedaluwarsa
            $this->updateBookingsStatus($bookings, 'CANCELLED');
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

            // Jika lunas, pastikan QR Code turnstile terisi
            if ($status === 'PAID' && empty($booking->qr_code_hash)) {
                $booking->update([
                    'qr_code_hash' => 'VNT-TICKET-' . strtoupper(bin2hex(random_bytes(16))),
                ]);
            }
        }
    }
}
