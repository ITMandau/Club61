<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('company_profile_settings', function (Blueprint $table) {
            $table->id();

            // Konten hero halaman depan publik (welcome.blade.php) — "compro" yang staf edit.
            $table->string('hero_badge_text', 150)->default('Medan Flagship Venue • Gedung Indosat • Open Daily');
            $table->string('hero_headline_line1', 80)->default('The Sanctuary for');
            $table->string('hero_headline_highlight', 80)->default('Padel Athletes');
            $table->string('hero_headline_line2', 80)->default('in Medan.');
            $table->text('hero_subtitle')->nullable();

            // Fakta venue — SATU-SATUNYA sumber, dipakai ulang di welcome.blade.php DAN panel
            // kiri auth/login.blade.php supaya tidak pernah drift lagi seperti insiden "4 vs 3
            // lapangan" yang memicu modul ini.
            $table->unsignedTinyInteger('court_count')->default(3);
            $table->json('facility_cards');
            $table->string('address_line', 200)->default('Gedung Indosat, Jl. Perintis Kemerdekaan No. 39, Medan, Sumatera Utara.');
            $table->string('operating_hours_text', 60)->default('Open 06:00 – 23:00');
            $table->string('portal_domain_text', 60)->default('portal.club61padel.com');

            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Inisialisasi row default singleton ID = 1, berisi konten yang SEDANG live saat ini —
        // supaya deploy modul ini tidak pernah membuat halaman publik tiba-tiba kosong.
        DB::table('company_profile_settings')->insert([
            'id' => 1,
            'hero_badge_text' => 'Medan Flagship Venue • Gedung Indosat • Open Daily',
            'hero_headline_line1' => 'The Sanctuary for',
            'hero_headline_highlight' => 'Padel Athletes',
            'hero_headline_line2' => 'in Medan.',
            'hero_subtitle' => 'Fasilitas terpadu berstandar internasional di Gedung Indosat Medan: {court_count} Lapangan Padel Panoramic Full Indoor ber-AC, Thermal Wellness Recovery (Sauna & Ice Bath 4°C).',
            'court_count' => 3,
            'facility_cards' => json_encode([
                ['icon_key' => 'arena', 'title' => 'Padel Arena', 'subtitle' => '+ Panoramic Courts'],
                ['icon_key' => 'wellness', 'title' => 'Wellness Suite', 'subtitle' => 'Sauna & Ice Plunge'],
                ['icon_key' => 'lounge', 'title' => 'Social Lounge', 'subtitle' => 'Artisan Cafe & Bar'],
            ]),
            'address_line' => 'Gedung Indosat, Jl. Perintis Kemerdekaan No. 39, Medan, Sumatera Utara.',
            'operating_hours_text' => 'Open 06:00 – 23:00',
            'portal_domain_text' => 'portal.club61padel.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_profile_settings');
    }
};
