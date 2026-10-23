<?php

use App\Services\Permission\Club61PermissionMatrix;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Modul 24 Opsi B Tahap 2 — profil coach untuk customer (informasi saja) + halaman link bio Instagram. Hanya menambah
 * kolom / tabel; data Tahap 1 tidak diubah. Coach lama TIDAK otomatis tampil (show_on_profile = false) sampai admin
 * melengkapi profil & menyalakannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coaches', function (Blueprint $table) {
            $table->string('photo_path', 255)->nullable()->after('email');
            $table->string('sport', 50)->default('Padel')->after('photo_path');
            $table->json('highlights')->nullable()->after('sport'); // poin keunggulan, list teks pendek
            $table->boolean('show_on_profile')->default(false)->after('is_active');
        });

        // Tombol halaman link bio. Tipe otomatis (BOOK_COURT, LOCATION, COACHING_WA, CLUB_WA, COACHES) mengambil
        // tujuannya dari data yang sudah ada; IMAGE = gambar unggahan (mis. pricelist); URL = link bebas (http/https).
        Schema::create('link_bio_links', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('label', 60);
            $table->string('type', 20);
            $table->string('url', 300)->nullable();
            $table->string('image_path', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        $rows = [
            ['label' => 'Book Court', 'type' => 'BOOK_COURT', 'is_active' => true],
            ['label' => 'Lokasi', 'type' => 'LOCATION', 'is_active' => true],
            ['label' => 'Admin Coaching', 'type' => 'COACHING_WA', 'is_active' => true],
            // Pricelist menunggu gambar dari klien — nonaktif sampai admin mengunggahnya.
            ['label' => 'Pricelist', 'type' => 'IMAGE', 'is_active' => false],
            ['label' => 'Admin Club 61', 'type' => 'CLUB_WA', 'is_active' => true],
        ];
        DB::table('link_bio_links')->insert(array_map(fn (array $row, int $i) => $row + [
            'id' => (string) Str::ulid(),
            'sort_order' => ($i + 1) * 10,
            'created_at' => $now,
            'updated_at' => $now,
        ], $rows, array_keys($rows)));

        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            Club61PermissionMatrix::grantNewPermissionsToPresetRoles('web');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('link_bio_links');
        Schema::table('coaches', function (Blueprint $table) {
            $table->dropColumn(['photo_path', 'sport', 'highlights', 'show_on_profile']);
        });
    }
};
