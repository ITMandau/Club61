<?php

namespace App\Http\Controllers\Api\V1\Gym;

use App\Http\Controllers\Controller;
use App\Models\Membership\MembershipPlan;
use Illuminate\Http\JsonResponse;

class GymController extends Controller
{
    public function packages(): JsonResponse
    {
        $packages = MembershipPlan::with('benefits')
            ->where('is_active', true)
            ->whereHas('benefits', function ($query) {
                $query->where('facility', 'GYM');
            })
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar paket membership gym.',
            'data' => $packages,
        ]);
    }
}
