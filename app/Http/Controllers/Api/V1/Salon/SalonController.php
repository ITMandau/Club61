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

        // Endpoint publik (tanpa auth) — jangan pernah kirim balik data internal staff
        // (hourly_rate/commission_rate) atau kontak pribadi (email/phone), cukup yang
        // memang perlu ditampilkan ke customer buat milih stylist.
        $stylists = StaffProfile::with('user:id,name,avatar_url')
            ->where('profession', 'STYLIST')
            ->where('is_available', true)
            ->get()
            ->map(fn (StaffProfile $staff) => [
                'id' => $staff->id,
                'bio' => $staff->bio,
                'user' => [
                    'id' => $staff->user?->id,
                    'name' => $staff->user?->name,
                    'avatar_url' => $staff->user?->avatar_url,
                ],
            ])
            ->values();

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
