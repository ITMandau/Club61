<?php

namespace App\Http\Controllers\Api\V1\Sponsor;

use App\Http\Controllers\Controller;
use App\Models\Sponsor\SponsorMemberVoucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint KARYAWAN (bukan PIC) — akses ke voucher milik DIRINYA SENDIRI saja. Terpisah dari
 * SponsorOrganizationController yang khusus PIC mengelola roster/voucher tim-nya.
 */
class SponsorMemberVoucherController extends Controller
{
    /**
     * Tandai voucher sudah dilihat/diklaim karyawan — murni penanda UI (popup klaim di
     * dashboard tidak muncul lagi buat voucher ini), TIDAK mempengaruhi kapan voucher bisa
     * dipakai (voucher sudah otomatis aktif sejak diterbitkan).
     */
    public function acknowledge(Request $request, string $voucher): JsonResponse
    {
        $voucherModel = SponsorMemberVoucher::where('id', $voucher)
            ->whereHas('member', fn ($q) => $q->where('user_id', $request->user()->id))
            ->first();

        if (! $voucherModel) {
            abort(404, 'Voucher tidak ditemukan.');
        }

        if (! $voucherModel->isAcknowledged()) {
            $voucherModel->update(['acknowledged_at' => now()]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Voucher berhasil diklaim.',
            'data' => $voucherModel,
        ]);
    }
}
