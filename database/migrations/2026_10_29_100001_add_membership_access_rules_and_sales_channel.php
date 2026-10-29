<?php

use App\Services\Permission\Club61PermissionMatrix;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul 28 Batch 2 (meeting 28 Okt 2026):
 *  - Aturan akses jam padel paket (pilihan "Waktu Berlaku"): hari berlaku (access_days, null = semua hari), "hanya di
 *    luar jam peak" (off_peak_only) & "hanya di jam peak" (peak_only), di benefit paket dan di salinan kartu
 *    (user_membership_balances) — sama dengan jam akses yang sudah ada.
 *  - Paket "Dijual lewat" (sales_channel): ALL = customer online & POS (perilaku lama), POS_ONLY = hanya kasir / admin.
 * Hanya menambah kolom; nilai bawaan tidak mengubah perilaku paket & kartu yang sudah ada.
 * Izin baru apply_membership_plan_rules (pintu belakang) hanya untuk super_admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['membership_plan_benefits', 'user_membership_balances'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'access_days')) {
                    $t->json('access_days')->nullable()->after('time_window_end');
                }
                if (! Schema::hasColumn($table, 'off_peak_only')) {
                    $t->boolean('off_peak_only')->default(false)->after('access_days');
                }
                if (! Schema::hasColumn($table, 'peak_only')) {
                    $t->boolean('peak_only')->default(false)->after('off_peak_only');
                }
            });
        }

        if (! Schema::hasColumn('membership_plans', 'sales_channel')) {
            Schema::table('membership_plans', function (Blueprint $t) {
                $t->string('sales_channel', 20)->default('ALL')->after('is_active');
            });
        }

        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            Club61PermissionMatrix::grantNewPermissionsToPresetRoles('web');
        }
    }

    public function down(): void
    {
        foreach (['membership_plan_benefits', 'user_membership_balances'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                foreach (['peak_only', 'off_peak_only', 'access_days'] as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $t->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasColumn('membership_plans', 'sales_channel')) {
            Schema::table('membership_plans', fn (Blueprint $t) => $t->dropColumn('sales_channel'));
        }
    }
};
