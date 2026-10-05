<?php

use App\Services\Permission\Club61PermissionMatrix;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Halaman Daftar Voucher (Modul 21): izin View:DaftarVoucher dibuat & diberikan hanya ke role yang preset-nya
 * memuatnya (super_admin). Izin lain yang diubah manual tidak disentuh.
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
        // Izin sengaja tidak dihapus: bisa sudah dicentang manual ke role lain.
    }
};
