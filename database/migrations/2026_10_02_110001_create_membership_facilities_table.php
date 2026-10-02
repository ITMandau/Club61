<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Master fasilitas membership. Dulu daftar fasilitas (PADEL / GYM / SAUNA) dan seluruh teks benefit di halaman
 * membership ditulis langsung di kode — admin tidak bisa menambah fasilitas baru, dan rincian hak akses yang
 * dilihat customer (mis. "3 panoramic courts WPT", "gym Technogym") tidak bisa diubah.
 *
 * Mode pemakaian menentukan bagaimana kuota fasilitas dipakai:
 *   PADEL_BOOKING    → dipotong saat booking lapangan padel (fasilitas sistem PADEL)
 *   WELLNESS_BOOKING → dipotong saat booking sesi wellness/sauna (fasilitas sistem SAUNA)
 *   CHECK_IN         → dipotong 1 kunjungan tiap check-in (GYM + fasilitas baru)
 *   INFO             → hanya ditampilkan sebagai benefit, tanpa kuota (mis. valet, diskon kafe)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_facilities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            // Sama panjang dengan membership_plan_benefits.facility / user_membership_balances.facility.
            $table->string('code', 10)->unique();
            $table->string('name', 100);
            $table->string('badge', 8);
            $table->string('description', 500)->nullable();
            $table->string('usage_mode', 20);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        foreach ([
            ['PADEL', 'Padel Court', 'PADEL', 'Reservasi lapangan padel Club 61.', 'PADEL_BOOKING'],
            ['GYM', 'Fitness & Gym', 'GYM', 'Akses area gym & fitness Club 61.', 'CHECK_IN'],
            ['SAUNA', 'Sauna & Ice Bath', 'SAUNA', 'Sesi sauna & ice bath Club 61.', 'WELLNESS_BOOKING'],
        ] as $i => [$code, $name, $badge, $description, $mode]) {
            DB::table('membership_facilities')->insert([
                'id' => (string) Str::ulid(), 'code' => $code, 'name' => $name, 'badge' => $badge, 'description' => $description,
                'usage_mode' => $mode, 'is_system' => true, 'is_active' => true, 'sort_order' => $i, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        Schema::table('membership_plans', function (Blueprint $table) {
            // Ringkasan paket untuk customer + daftar privilege tambahan (dulu kartu "PERKS" berisi teks dummy).
            $table->string('description', 500)->nullable()->after('name');
            $table->json('perks')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('membership_plans', function (Blueprint $table) {
            $table->dropColumn(['description', 'perks']);
        });

        Schema::dropIfExists('membership_facilities');
    }
};
