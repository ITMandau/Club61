<?php

namespace App\Http\Controllers\Api\V1\Salon;

use App\Http\Controllers\Controller;
use App\Models\Salon\SalonService;
use App\Models\Staff\StaffProfile;
use Illuminate\Http\JsonResponse;

class SalonController extends Controller
{
    public function services(): JsonResponse
    {
        $services = SalonService::where('is_active', true)->get();
        $stylists = StaffProfile::with('user')->where('profession', 'STYLIST')->where('is_available', true)->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar layanan salon dan stylist aktif.',
            'data' => [
                'services' => $services,
                'stylists' => $stylists,
            ],
        ]);
    }
}
