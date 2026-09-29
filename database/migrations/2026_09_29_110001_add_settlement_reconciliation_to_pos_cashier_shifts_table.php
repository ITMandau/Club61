<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rekonsiliasi closing per kategori pembayaran (mis. "Kartu Debit — EDC BCA",
        // "QRIS — BCA"): angka yang tercatat sistem POS vs angka di struk settlement mesin
        // EDC / mutasi QRIS, beserta selisihnya. Disimpan sebagai snapshot saat shift ditutup.
        Schema::table('pos_cashier_shifts', function (Blueprint $table) {
            $table->json('settlement_reconciliation')->nullable()->after('total_transactions');
            $table->decimal('settlement_difference', 14, 2)->default(0)->after('settlement_reconciliation');
        });
    }

    public function down(): void
    {
        Schema::table('pos_cashier_shifts', function (Blueprint $table) {
            $table->dropColumn(['settlement_reconciliation', 'settlement_difference']);
        });
    }
};
