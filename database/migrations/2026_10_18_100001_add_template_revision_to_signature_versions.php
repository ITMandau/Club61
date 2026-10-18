<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Digital Signage: versi baru hanya dibuat saat layar pindah TEMPLATE. Tayangkan dengan template yang sama memperbarui
 * versi yang sedang tayang (keputusan user 18 Okt 2026 — edit template / konten dulu selalu jadi v2, v3, …):
 *   - template_id   = template yang dipakai versi ini (null = versi lama → dicocokkan lewat nama template di payload);
 *   - revision      = naik setiap versi ini diperbarui; TV memuat ulang tampilan saat versi ATAU revisinya berubah;
 *   - updated_at / updated_by_id = terakhir diperbarui (null = belum pernah).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signature_versions', function (Blueprint $table) {
            $table->foreignUlid('template_id')->nullable()->after('screen_id')->constrained('signature_templates')->nullOnDelete();
            $table->unsignedInteger('revision')->default(1)->after('version');
            $table->dateTime('updated_at')->nullable()->after('created_at');
            $table->foreignUlid('updated_by_id')->nullable()->after('updated_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('signature_versions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('template_id');
            $table->dropConstrainedForeignId('updated_by_id');
            $table->dropColumn(['revision', 'updated_at']);
        });
    }
};
