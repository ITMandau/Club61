<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('padel_bookings', function (Blueprint $table) {
            $table->string('order_id', 50)->nullable()->after('booking_code')->index();
        });

        Schema::table('padel_booking_equipments', function (Blueprint $table) {
            $table->string('order_id', 50)->nullable()->after('booking_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('padel_bookings', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
            $table->dropColumn('order_id');
        });

        Schema::table('padel_booking_equipments', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
            $table->dropColumn('order_id');
        });
    }
};
