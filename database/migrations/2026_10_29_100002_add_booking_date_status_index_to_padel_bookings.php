<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelola Pemesanan memfilter booking per periode (tanggal main) + status. Index ini menjaga daftar & angka tab tetap
 * ringan walau data booking bertahun-tahun — data tidak dihapus (dipakai Buku Transaksi, refund, laporan).
 */
return new class extends Migration
{
    private const INDEX = 'idx_padel_booking_date_status';

    public function up(): void
    {
        if (! Schema::hasIndex('padel_bookings', self::INDEX)) {
            Schema::table('padel_bookings', fn (Blueprint $t) => $t->index(['booking_date', 'status'], self::INDEX));
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('padel_bookings', self::INDEX)) {
            Schema::table('padel_bookings', fn (Blueprint $t) => $t->dropIndex(self::INDEX));
        }
    }
};
