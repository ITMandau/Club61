<?php

namespace App\Http\Controllers\Api\V1\Padel;

use App\Http\Controllers\Controller;
use App\Models\Padel\CourtEquipment;
use App\Models\Padel\PadelCourt;
use Illuminate\Http\JsonResponse;

class PadelCourtController extends Controller
{
    public function index(): JsonResponse
    {
        $courts = PadelCourt::where('is_active', true)->get();
        $equipments = CourtEquipment::where('is_active', true)->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar lapangan dan alat sewa padel.',
            'data' => [
                'courts' => $courts,
                'equipments' => $equipments,
            ],
        ]);
    }
}
