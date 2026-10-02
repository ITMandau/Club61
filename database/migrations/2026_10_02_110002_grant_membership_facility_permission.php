<?php

use App\Services\Permission\Club61PermissionMatrix;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Izin Master Fasilitas Membership (manage_membership_facilities) belum ada di
 * database server yang sudah berjalan — di menu "Roles & Hak Akses" izin itu tidak tercentang di role mana pun,
 * termasuk super_admin. Migration ini hanya MENAMBAH izin yang belum ada ke role yang preset-nya memuatnya;
 * izin yang sudah ada (termasuk yang diubah manual) tidak disentuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        Club61PermissionMatrix::grantNewPermissionsToPresetRoles('web');
    }

    public function down(): void
    {
        // Sengaja tidak menghapus izin: bisa sudah dicentang manual ke role lain setelah migrate.
    }
};
