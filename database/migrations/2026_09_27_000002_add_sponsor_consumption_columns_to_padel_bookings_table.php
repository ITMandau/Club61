<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('padel_bookings', function (Blueprint $table) {
            // Mirror member_discount_court/member_hours_consumed, tapi untuk voucher jam sponsor
            // corporate (bukan membership individual) — dipakai saat reversal (pembatalan booking
            // mengembalikan jam ke voucher terkait) dan buat tampilan invoice/riwayat booking.
            $table->decimal('sponsor_discount_court', 12, 2)->nullable()->after('sponsor_member_voucher_id');
            $table->decimal('sponsor_hours_consumed', 8, 2)->nullable()->after('sponsor_discount_court');
        });
    }

    public function down(): void
    {
        Schema::table('padel_bookings', function (Blueprint $table) {
            $table->dropColumn(['sponsor_discount_court', 'sponsor_hours_consumed']);
        });
    }
};
