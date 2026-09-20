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
            $table->string('description', 100)->nullable()->after('type');
        });

        // Set deskripsi bawaan untuk data yang sudah ada
        DB::table('padel_courts')->where('type', 'INDOOR')->update(['description' => 'Indoor • Central AC']);
        DB::table('padel_courts')->where('type', 'OUTDOOR')->update(['description' => 'Outdoor • Open Air Court']);
    }

    public function down(): void
    {
        Schema::table('padel_courts', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
