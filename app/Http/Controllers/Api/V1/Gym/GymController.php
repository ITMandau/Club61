<?php

namespace App\Http\Controllers\Api\V1\Gym;

use App\Http\Controllers\Controller;
use App\Models\Gym\GymPackage;
use Illuminate\Http\JsonResponse;

class GymController extends Controller
{
    public function packages(): JsonResponse
    {
        $packages = GymPackage::all();
        return response()->json([
            'success' => true,
            'message' => 'Daftar paket membership gym.',
            'data' => $packages,
        ]);
    }
}
