<?php

namespace App\Http\Controllers\Api\V1\Padel;

use App\Http\Controllers\Controller;
use App\Models\Padel\PadelBooking;
use App\Services\Padel\PadelBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PadelBookingController extends Controller
{
    protected PadelBookingService $bookingService;

    public function __construct(PadelBookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    public function hold(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'court_id' => ['required', 'string'],
            'booking_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'coach_id' => ['nullable', 'string'],
        ]);

        $booking = $this->bookingService->holdSlot(
            $validated['court_id'],
            $validated['booking_date'],
            $validated['start_time'],
            $validated['end_time'],
            $request->user(),
            $validated['coach_id'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Slot lapangan berhasil di-hold selama 5 menit. Silakan selesaikan pembayaran.',
            'data' => $booking,
        ], 201);
    }

    public function myBookings(Request $request): JsonResponse
    {
        $bookings = PadelBooking::with(['court', 'coach'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar riwayat booking padel saya.',
            'data' => $bookings,
        ]);
    }
}
