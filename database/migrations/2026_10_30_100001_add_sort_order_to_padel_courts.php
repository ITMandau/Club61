<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Urutan tampil lapangan (Master Data → Edit Lapangan → "Urutan Tampil"). Hanya menambah kolom: default 0 untuk semua
 * lapangan lama, sehingga sebelum diisi urutan tetap per abjad nama (scope PadelCourt::ordered()).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('padel_courts', 'sort_order')) {
            Schema::table('padel_courts', fn (Blueprint $t) => $t->unsignedSmallInteger('sort_order')->default(0)->after('is_active'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('padel_courts', 'sort_order')) {
            Schema::table('padel_courts', fn (Blueprint $t) => $t->dropColumn('sort_order'));
        }
    }
};
