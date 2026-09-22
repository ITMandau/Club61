<?php

namespace App\Http\Controllers\Api\V1\Wellness;

use App\Http\Controllers\Controller;
use App\Models\Wellness\WellnessBooking;
use App\Models\Wellness\WellnessFacility;
use App\Models\Wellness\WellnessSlot;
use App\Services\Wellness\WellnessBookingService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WellnessController extends Controller
{
    public function __construct(
        protected WellnessBookingService $wellnessBookingService
    ) {}

    public function facilities(): JsonResponse
    {
        $facilities = WellnessFacility::with('slots')->get();
        return response()->json([
            'success' => true,
            'message' => 'Daftar fasilitas wellness (Ice Bath & Sauna).',
            'data' => $facilities,
        ]);
    }

    public function slots(Request $request): JsonResponse
    {
        $date = $request->query('date', now()->format('Y-m-d'));
        $slots = WellnessSlot::with('facility')
            ->where('session_date', $date)
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'success' => true,
            'message' => "Daftar slot wellness untuk tanggal $date.",
            'data' => $slots,
        ]);
    }

    public function book(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slot_id' => 'required|string|exists:wellness_slots,id',
            'num_persons' => 'nullable|integer|min:1',
            'membership_balance_id' => 'nullable|string|exists:user_membership_balances,id',
        ]);

        try {
            $booking = $this->wellnessBookingService->bookSlot(
                user: $request->user(),
                slotId: $validated['slot_id'],
                numPersons: $validated['num_persons'] ?? 1,
                membershipBalanceId: $validated['membership_balance_id'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Booking wellness berhasil dibuat.',
                'data' => $booking->load(['slot.facility', 'membershipBalance']),
            ], 201);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function cancel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'booking_id' => 'required|string|exists:wellness_bookings,id',
            'cancel_reason' => 'nullable|string|max:255',
        ]);

        $booking = WellnessBooking::where('id', $validated['booking_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        try {
            $cancelled = $this->wellnessBookingService->cancelBooking(
                booking: $booking,
                reason: $validated['cancel_reason'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Booking wellness berhasil dibatalkan.',
                'data' => $cancelled,
            ]);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
