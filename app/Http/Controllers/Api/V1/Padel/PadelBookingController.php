<?php

namespace App\Http\Controllers\Api\V1\Padel;

use App\Http\Controllers\Controller;
use App\Services\Padel\PadelBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PadelBookingController extends Controller
{
    protected PadelBookingService $bookingService;

    public function __construct(PadelBookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    /**
     * Matriks Jadwal Lapangan (Publik, Timezone-Aware).
     */
    public function schedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'timezone' => ['nullable', 'string'],
        ]);

        $date = $validated['date'] ?? now()->format('Y-m-d');
        $timezone = $validated['timezone'] ?? 'Asia/Jakarta';

        $matrix = $this->bookingService->getScheduleMatrix($date, $timezone);

        return response()->json([
            'success' => true,
            'message' => 'Jadwal lapangan padel berhasil diambil.',
            'data' => $matrix,
        ]);
    }

    /**
     * Katalog Sewa Peralatan & Add-ons (Publik).
     */
    public function equipments(): JsonResponse
    {
        $equipments = $this->bookingService->getEquipments();

        return response()->json([
            'success' => true,
            'message' => 'Katalog alat dan add-on padel berhasil diambil.',
            'data' => $equipments,
        ]);
    }

    /**
     * Hold Slot Lapangan (Multi-Slot Atomic All-or-Nothing).
     */
    public function hold(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'booking_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'slots' => ['required', 'array', 'min:1'],
            'slots.*.court_id' => ['required', 'string'],
            'slots.*.start_time' => ['required', 'date_format:H:i'],
            'slots.*.end_time' => ['required', 'date_format:H:i'],
            'coach_id' => ['nullable', 'string'],
        ]);

        $holdData = $this->bookingService->holdBatchSlots(
            $validated['slots'],
            $validated['booking_date'],
            $request->user(),
            $validated['coach_id'] ?? null
        );

        $slotCount = count($holdData['bookings']);

        return response()->json([
            'success' => true,
            'message' => "{$slotCount} slot lapangan berhasil di-hold selama 10 menit. Silakan selesaikan checkout.",
            'data' => $holdData,
        ], 201);
    }

    /**
     * Melepaskan Kunci Slot (Batal dari Keranjang).
     */
    public function release(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'booking_ids' => ['required', 'array', 'min:1'],
            'booking_ids.*' => ['required', 'string'],
        ]);

        $releasedCount = $this->bookingService->releaseSlots($validated['booking_ids'], $request->user());

        return response()->json([
            'success' => true,
            'message' => "{$releasedCount} slot booking berhasil dibatalkan dan dilepas kembali ke publik.",
        ]);
    }

    /**
     * Checkout Pembayaran (Idempotent 24 Jam).
     */
    public function checkout(Request $request): JsonResponse
    {
        $idempotencyKey = $request->header('X-Idempotency-Key');
        if (! $idempotencyKey) {
            throw new HttpException(400, 'Header X-Idempotency-Key (UUID) wajib disertakan untuk mencegah debit ganda.');
        }

        $validated = $request->validate([
            'booking_ids' => ['required', 'array', 'min:1'],
            'booking_ids.*' => ['required', 'string'],
            'equipments' => ['nullable', 'array'],
            'equipments.*.equipment_id' => ['required', 'string'],
            'equipments.*.quantity' => ['required', 'integer', 'min:1'],
            'voucher_code' => ['nullable', 'string'],
            'payment_method' => ['required', 'string', 'in:QRIS,BCA_VA,MANDIRI_VA,BRI_VA,BNI_VA,CIMB_VA,BSI_VA,CASH'],
        ]);

        $checkoutResult = $this->bookingService->checkout(
            $validated['booking_ids'],
            $validated['equipments'] ?? [],
            $validated['voucher_code'] ?? null,
            $validated['payment_method'],
            $idempotencyKey,
            $request->user()
        );

        return response()->json($checkoutResult, 200);
    }

    /**
     * Ganti Metode Pembayaran / Regenerasi Snap Token (Pending Payment Retry).
     */
    public function retryPayment(string $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payment_method' => ['required', 'string', 'in:QRIS,BCA_VA,MANDIRI_VA,BRI_VA,BNI_VA,CIMB_VA,BSI_VA,CREDIT_CARD,CASH'],
        ]);

        $result = $this->bookingService->retryPayment(
            $id,
            $validated['payment_method'],
            $request->user()
        );

        return response()->json($result, 200);
    }

    /**
     * Riwayat Booking Saya.
     */
    public function myBookings(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $bookings = $this->bookingService->myBookings($request->user(), $status);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat booking padel saya berhasil diambil.',
            'data' => $bookings,
        ]);
    }

    /**
     * Detail E-Tiket & Payload QR Code.
     */
    public function ticket(string $id, Request $request): JsonResponse
    {
        $booking = $this->bookingService->getTicket($id, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Detail e-tiket berhasil diambil.',
            'data' => $booking,
        ]);
    }

    /**
     * Check-in Turnstile / Gate di Venue (Kasir & Staff).
     */
    public function checkIn(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! in_array($user->role, ['SUPER_ADMIN', 'ADMIN', 'CASHIER'])) {
            throw new HttpException(403, 'Akses Ditolak: Hanya staf kasir atau admin yang dapat memverifikasi check-in pemain.');
        }

        $validated = $request->validate([
            'qr_code_hash' => ['required', 'string'],
        ]);

        $checkInData = $this->bookingService->checkIn($validated['qr_code_hash'], $user);

        return response()->json([
            'success' => true,
            'message' => 'Check-in berhasil! Akses gate dibuka. Selamat bertanding.',
            'data' => $checkInData,
        ]);
    }

    /**
     * Pengajuan Pembatalan Refund Resmi (H-24).
     */
    public function refund(string $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $booking = $this->bookingService->requestRefund($id, $validated['reason'], $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan refund berhasil diajukan. Status telah diubah menjadi REFUND_PENDING.',
            'data' => $booking,
        ]);
    }
}
