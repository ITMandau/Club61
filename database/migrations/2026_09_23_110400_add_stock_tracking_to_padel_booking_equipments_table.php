<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('padel_booking_equipments', function (Blueprint $table) {
            // Kapan stock_quantity milik equipment ini beneran dipotong. BALL (consumable, "dibeli habis")
            // dipotong seketika saat checkout; RACKET/TOWEL (disewa, dipinjamkan fisik) baru dipotong saat
            // check-in (serah-terima alat di frontdesk) — lihat ManagesCheckInAndTurnstile::checkIn().
            $table->dateTime('stock_deducted_at')->nullable()->after('subtotal');

            // Kapan alat RACKET/TOWEL ini ditandai admin/kasir sudah dikembalikan fisik ke frontdesk,
            // memicu stock_quantity di-restock (+qty). BALL tidak pernah punya nilai di sini (tidak
            // pernah "dikembalikan" karena sudah dibeli/consumed).
            $table->dateTime('returned_at')->nullable()->after('stock_deducted_at');
        });
    }

    public function down(): void
    {
        Schema::table('padel_booking_equipments', function (Blueprint $table) {
            $table->dropColumn(['stock_deducted_at', 'returned_at']);
        });
    }
};
