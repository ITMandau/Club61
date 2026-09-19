<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('padel_courts', function (Blueprint $table) {
            $table->string('open_time', 5)->default('06:00')->nullable()->after('description');
            $table->string('close_time', 5)->default('23:00')->nullable()->after('open_time');
        });

        // Set jam operasional bawaan untuk data lapangan yang sudah ada
        DB::table('padel_courts')->update([
            'open_time' => '06:00',
            'close_time' => '23:00',
        ]);
    }

    public function down(): void
    {
        Schema::table('padel_courts', function (Blueprint $table) {
            $table->dropColumn(['open_time', 'close_time']);
        });
    }
};
