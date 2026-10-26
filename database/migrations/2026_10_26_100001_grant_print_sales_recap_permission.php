<?php

use App\Services\Permission\Club61PermissionMatrix;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Rekap Penjualan Harian (X-Report) di POS: izin baru print_sales_recap dibuat & diberikan ke role yang preset-nya
 * memuatnya (kasir, resepsionis, admin). Izin lain & centangan manual di "Roles & Hak Akses" tidak disentuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            Club61PermissionMatrix::grantNewPermissionsToPresetRoles('web');
        }
    }

    public function down(): void
    {
        // Izin dibiarkan: menghapusnya juga mencabut centangan yang mungkin sudah diatur admin.
    }
};
