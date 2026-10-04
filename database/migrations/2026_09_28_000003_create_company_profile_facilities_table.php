<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_profile_facilities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('title', 80);
            $table->text('description')->nullable();
            $table->json('amenities')->nullable();
            // Path relatif di disk 'public' (storage/app/public/...) — foto SELALU diproses ulang
            // (re-encode + resize via GD) sebelum disimpan di sini, tidak pernah file upload mentah
            // langsung. Lihat KelolaKontenWebsite::processFacilityPhotoUpload().
            $table->string('photo_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed 3 fasilitas default dari kartu yang sudah live, supaya "Facilities Showcase"
        // section publik tidak pernah kosong begitu modul ini deploy.
        $now = now();
        DB::table('company_profile_facilities')->insert([
            [
                'id' => (string) Str::ulid(),
                'title' => 'Padel Arena',
                'description' => 'Lapangan padel panoramic full indoor ber-AC, standar internasional.',
                'amenities' => json_encode(['Lantai profesional', 'Pencahayaan LED standar turnamen', 'Kaca panoramic']),
                'photo_path' => null,
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => (string) Str::ulid(),
                'title' => 'Wellness Suite',
                'description' => 'Ruang pemulihan pasca-main dengan sauna dan ice bath.',
                'amenities' => json_encode(['Sauna', 'Ice bath 4°C', 'Ruang ganti privat']),
                'photo_path' => null,
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => (string) Str::ulid(),
                'title' => 'Social Lounge',
                'description' => 'Area santai dan cafe artisan untuk sebelum/sesudah bermain.',
                'amenities' => json_encode(['Specialty coffee', 'Menu F&B', 'Area duduk santai']),
                'photo_path' => null,
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('company_profile_facilities');
    }
};
