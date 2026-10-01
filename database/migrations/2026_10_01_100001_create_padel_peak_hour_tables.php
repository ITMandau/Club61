<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jam peak (prime time) padel yang bisa diatur admin di Master Data — dulu hard-code di 7 tempat:
 * "akhir pekan seharian ATAU jam >= 17:00". Berlaku untuk SEMUA lapangan (tarif tetap per lapangan).
 *
 *   padel_peak_hour_rules : rentang jam peak per hari (0 = Minggu ... 6 = Sabtu, sama dengan Carbon::dayOfWeek).
 *                           Satu hari boleh punya beberapa rentang; hari tanpa rentang = reguler seharian.
 *   padel_holidays        : tanggal merah — memakai jam peak hari Minggu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('padel_peak_hour_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedTinyInteger('day_of_week'); // 0 = Minggu ... 6 = Sabtu
            $table->unsignedTinyInteger('start_hour');  // 0..23
            $table->unsignedTinyInteger('end_hour');    // 1..24 (eksklusif; 24 = tengah malam)
            $table->timestamps();

            $table->index('day_of_week');
        });

        Schema::create('padel_holidays', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->date('date')->unique();
            $table->string('name', 100);
            $table->timestamps();
        });

        // Default = aturan lama persis, supaya harga tidak berubah sampai admin mengubahnya sendiri:
        // Senin-Jumat 17:00-24:00, Sabtu & Minggu seharian.
        $now = now();
        $rows = [];
        foreach (range(0, 6) as $day) {
            $isWeekend = in_array($day, [0, 6], true);
            $rows[] = [
                'id' => (string) \Illuminate\Support\Str::ulid(),
                'day_of_week' => $day,
                'start_hour' => $isWeekend ? 0 : 17,
                'end_hour' => 24,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('padel_peak_hour_rules')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('padel_holidays');
        Schema::dropIfExists('padel_peak_hour_rules');
    }
};
