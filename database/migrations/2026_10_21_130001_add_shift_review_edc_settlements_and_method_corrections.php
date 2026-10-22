<?php

use App\Services\Permission\Club61PermissionMatrix;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tutup shift kasir Fase 1 — hanya MENAMBAH, data lama tidak diubah:
 *   - pos_cashier_shifts: angka settlement yang diketik kasir (blind, dibekukan di percobaan pertama) & status review
 *     (MATCHED / NEEDS_REVIEW / APPROVED / REJECTED). Shift lama = null (sebelum fitur ini).
 *   - pos_edc_settlements: kapan mesin EDC tiap loket terakhir di-settle → angka sistem EDC = semua transaksi mesin itu
 *     sejak settlement terakhir (bisa lintas shift kalau ganti shift tanpa settle).
 *   - ledger_entries: tanda koreksi metode bayar oleh admin (baris tetap satu; metode & bukti asli disimpan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_cashier_shifts', function (Blueprint $table) {
            $table->json('closing_inputs')->nullable()->after('settlement_difference');
            $table->string('review_status', 20)->nullable()->index()->after('closing_inputs');
            $table->foreignUlid('reviewed_by_id')->nullable()->after('review_status')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_id');
            $table->text('review_notes')->nullable()->after('reviewed_at');
        });

        Schema::create('pos_edc_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('counter', 30);
            $table->string('terminal', 30);
            $table->foreignUlid('pos_shift_id')->nullable()->constrained('pos_cashier_shifts')->nullOnDelete();
            $table->timestamp('settled_at');
            $table->foreignUlid('settled_by_id')->nullable()->constrained('users')->nullOnDelete();
            // CLOSING = dicatat saat tutup shift (struk settlement dicocokkan); OPENING = kasir menyatakan sudah settle saat buka shift.
            $table->string('source', 20);
            $table->timestamps();
            $table->index(['counter', 'terminal', 'settled_at'], 'idx_edc_settlement_lookup');
        });

        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->timestamp('method_corrected_at')->nullable()->after('payment_reference');
            $table->string('method_corrected_by_id', 26)->nullable()->after('method_corrected_at');
            $table->string('method_corrected_by_name', 150)->nullable()->after('method_corrected_by_id');
            $table->string('original_payment_method', 40)->nullable()->after('method_corrected_by_name');
            $table->string('original_payment_method_label', 120)->nullable()->after('original_payment_method');
            $table->string('original_payment_reference', 120)->nullable()->after('original_payment_method_label');
        });

        // Izin baru approve_shift_closing dibuat & diberikan ke role yang preset-nya memuatnya (admin). Izin lain tidak disentuh.
        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            Club61PermissionMatrix::grantNewPermissionsToPresetRoles('web');
        }
    }

    public function down(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->dropColumn(['method_corrected_at', 'method_corrected_by_id', 'method_corrected_by_name', 'original_payment_method', 'original_payment_method_label', 'original_payment_reference']);
        });

        Schema::dropIfExists('pos_edc_settlements');

        Schema::table('pos_cashier_shifts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by_id');
            $table->dropColumn(['closing_inputs', 'review_status', 'reviewed_at', 'review_notes']);
        });
    }
};
