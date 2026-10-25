<?php

namespace App\Http\Controllers\Api\V1\Coaching;

use App\Http\Controllers\Controller;
use App\Services\Coaching\CoachDirectory;
use Illuminate\Http\JsonResponse;

/**
 * Profil coach untuk aplikasi (Modul 24 Opsi B Tahap 2) — publik, read-only, sama dengan halaman Coaching web.
 * Booking coaching tetap lewat admin coaching (WhatsApp) → resepsionis di POS.
 */
class CoachController extends Controller
{
    public function index(CoachDirectory $directory): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Daftar coach Club 61.',
            'data' => [
                'coaches' => array_map(fn (array $coach) => $coach + [
                    'whatsapp_url' => $directory->adminWhatsappUrl($coach['name']),
                ], $directory->coaches(absoluteUrls: true)),
                'admin_whatsapp_url' => $directory->adminWhatsappUrl(),
            ],
        ]);
    }
}
