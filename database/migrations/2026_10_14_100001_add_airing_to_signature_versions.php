<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Digital Signage: "Kembalikan versi" sekarang memindahkan tanda Tayang ke versi lama (bukan menyalinnya jadi versi
 * baru), jadi masa tayang dicatat per versi:
 *   - aired_at / aired_by_id = terakhir kali versi ini mulai tayang (dan oleh siapa);
 *   - aired_until            = terakhir kali versi ini berhenti tayang (null = sedang tayang / belum pernah berhenti).
 * aired_until dipakai aturan file "dihapus setelah 7 hari tidak tayang" (SignatureFileJanitor).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signature_versions', function (Blueprint $table) {
            $table->dateTime('aired_at')->nullable()->after('published_by_id');
            $table->foreignUlid('aired_by_id')->nullable()->after('aired_at')->constrained('users')->nullOnDelete();
            $table->dateTime('aired_until')->nullable()->after('aired_by_id');
        });

        // Data lama: setiap versi dulu tayang sejak dibuat sampai versi berikutnya di layar yang sama dibuat.
        $published = DB::table('signature_screens')->whereNotNull('published_version_id')->pluck('published_version_id')->flip();
        $versions = DB::table('signature_versions')->orderBy('screen_id')->orderBy('version')->get(['id', 'screen_id', 'published_by_id', 'created_at']);
        foreach ($versions as $i => $version) {
            $next = $versions[$i + 1] ?? null;
            DB::table('signature_versions')->where('id', $version->id)->update([
                'aired_at' => $version->created_at,
                'aired_by_id' => $version->published_by_id,
                'aired_until' => isset($published[$version->id]) ? null : ($next && $next->screen_id === $version->screen_id ? $next->created_at : $version->created_at),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('signature_versions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('aired_by_id');
            $table->dropColumn(['aired_at', 'aired_until']);
        });
    }
};
