<?php

namespace App\Http\Controllers\Api\V1\Wellness;

use App\Http\Controllers\Controller;
use App\Models\Wellness\WellnessFacility;
use App\Models\Wellness\WellnessSlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WellnessController extends Controller
{
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
}
