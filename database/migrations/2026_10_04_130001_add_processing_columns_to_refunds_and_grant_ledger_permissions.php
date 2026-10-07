<?php

use App\Services\Permission\Club61PermissionMatrix;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul 17 Fase 2:
 *  - Antrian Refund: refund PENDING diproses dari panel admin — catat cara uang dikembalikan, nomor referensi
 *    (transfer / void EDC / refund Midtrans), siapa yang memproses / menolak, dan alasan penolakan.
 *  - Izin Buku Transaksi (View:BukuTransaksi, export_ledger, view_ledger_invoice, process_refund_queue) dibuat dan
 *    diberikan hanya ke role yang preset-nya memuatnya (super_admin). Izin lain yang diubah manual tidak disentuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->string('refund_method', 30)->nullable()->after('status');
            $table->string('refund_reference', 120)->nullable()->after('refund_method');
            $table->foreignUlid('processed_by_id')->nullable()->after('processed_at')->constrained('users')->nullOnDelete();
            $table->text('admin_notes')->nullable()->after('processed_by_id');
            $table->index(['status', 'created_at']);
        });

        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            Club61PermissionMatrix::grantNewPermissionsToPresetRoles('web');
        }
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropConstrainedForeignId('processed_by_id');
            $table->dropColumn(['refund_method', 'refund_reference', 'admin_notes']);
        });
        // Izin sengaja tidak dihapus: bisa sudah dicentang manual ke role lain.
    }
};
