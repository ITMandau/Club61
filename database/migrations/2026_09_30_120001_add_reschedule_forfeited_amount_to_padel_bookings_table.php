<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kebijakan reschedule ke jam yang lebih murah: selisih TIDAK dikembalikan (hangus). Nominalnya
 * dicatat di sini supaya tetap transparan di invoice customer & laporan — court_fee tetap
 * menyimpan nominal yang sudah dibayar (bukan tarif jadwal baru), jadi pembukuan order tidak berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('padel_bookings', function (Blueprint $table) {
            $table->decimal('reschedule_forfeited_amount', 12, 2)->default(0)->after('reschedule_count');
        });
    }

    public function down(): void
    {
        Schema::table('padel_bookings', function (Blueprint $table) {
            $table->dropColumn('reschedule_forfeited_amount');
        });
    }
};
