<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modul 17 §4.2 — perbaikan data untuk Buku Transaksi:
 *  1. payments.paid_at: saat uang diterima (status jadi SUCCESS / DUPLICATE). Dulu riwayat memakai updated_at,
 *     yang ikut berubah setiap kali baris pembayaran disentuh. Data lama diisi dari updated_at.
 *  2. Item sewa alat (raket/bola/handuk) di order padel dulu tercatat item_type PADEL — tidak bisa dipisah dari
 *     sewa lapangan. Item PADEL yang reference_id-nya alat di court_equipments diubah jadi EQUIPMENT.
 *     Aktivasi booking (PadelFulfillmentHandler) membaca order.padelBookings, bukan item_type — tidak terpengaruh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dateTime('paid_at')->nullable()->after('status')->index();
        });

        DB::table('payments')
            ->whereIn('status', ['SUCCESS', 'DUPLICATE'])
            ->whereNull('paid_at')
            ->update(['paid_at' => DB::raw('updated_at')]);

        DB::table('order_items')
            ->where('item_type', 'PADEL')
            ->whereIn('reference_id', DB::table('court_equipments')->select('id'))
            ->update(['item_type' => 'EQUIPMENT']);
    }

    public function down(): void
    {
        DB::table('order_items')->where('item_type', 'EQUIPMENT')->update(['item_type' => 'PADEL']);

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['paid_at']);
            $table->dropColumn('paid_at');
        });
    }
};
