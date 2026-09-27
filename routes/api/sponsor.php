<?php

use App\Http\Controllers\Api\V1\Sponsor\SponsorMemberVoucherController;
use App\Http\Controllers\Api\V1\Sponsor\SponsorOrganizationController;
use Illuminate\Support\Facades\Route;

// Portal PIC (Person In Charge) Corporate/Sponsor Account — PRD Modul 12.
// Ini BUKAN panel staf: PIC adalah customer eksternal, digerbangi murni lewat
// SponsorOrganizationPolicy + kepemilikan sponsor_admin_user_id, tanpa jalur apapun
// ke Filament Shield /admin. Jadwal akses lapangan (time window per sponsor) SENGAJA
// tidak ada di sini — itu dikontrol staf venue lewat panel admin (SponsorScheduleService),
// PIC hanya bisa MELIHAT jadwalnya lewat endpoint /organization di bawah.
Route::prefix('v1/sponsor')->middleware(['auth:sanctum,web'])->group(function () {
    Route::get('/organization', [SponsorOrganizationController::class, 'show']);

    Route::get('/organization/members', [SponsorOrganizationController::class, 'members']);
    Route::post('/organization/members', [SponsorOrganizationController::class, 'addMember']);
    Route::post('/organization/members/import-csv', [SponsorOrganizationController::class, 'importCsv']);
    Route::post('/organization/members/{member}/release-voucher', [SponsorOrganizationController::class, 'releaseVoucher']);
    Route::post('/organization/members/bulk-release-voucher', [SponsorOrganizationController::class, 'bulkReleaseVoucher']);
    Route::post('/organization/members/{member}/revoke', [SponsorOrganizationController::class, 'revokeMember']);
    Route::post('/organization/members/{member}/reactivate', [SponsorOrganizationController::class, 'reactivateMember']);
    Route::patch('/organization/vouchers/{voucher}', [SponsorOrganizationController::class, 'updateVoucher']);
    Route::delete('/organization/vouchers/{voucher}', [SponsorOrganizationController::class, 'deleteVoucher']);

    // Endpoint KARYAWAN (bukan PIC) — voucher milik diri sendiri.
    Route::post('/my-vouchers/{voucher}/acknowledge', [SponsorMemberVoucherController::class, 'acknowledge']);
});
