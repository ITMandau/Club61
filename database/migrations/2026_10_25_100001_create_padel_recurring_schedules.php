<?php

use App\Services\Permission\Club61PermissionMatrix;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jadwal Tetap Mingguan — booking rutin (mis. corporate tiap Kamis 19:00–22:00 di 3 lapangan) yang dibuat otomatis
 * oleh sistem dan dipotong dari kuota jam PADEL sebuah membership (corporate maupun individu). Batas reschedule jadi SATU
 * pengaturan global untuk semua booking (booking_time_settings.reschedule_cutoff_hours, bawaan 2 jam = aturan Modul 21).
 * Hanya menambah tabel / kolom — data lama tidak diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('padel_recurring_schedules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 100);
            $table->foreignUlid('user_membership_id')->constrained('user_memberships')->restrictOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // ISO: 1 = Senin … 7 = Minggu
            $table->string('start_time', 5); // "19:00"
            $table->string('end_time', 5);   // "22:00" / "24:00"
            $table->json('court_ids');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->unsignedTinyInteger('weeks_ahead')->default(5);
            $table->string('status', 10)->default('ACTIVE'); // ACTIVE | PAUSED
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Satu baris per (jadwal, lapangan, tanggal asli): BOOKED / SKIPPED / FAILED. Kunci unik = generator tidak
        // pernah membuat booking ganda, dan booking yang di-reschedule ke tanggal lain tidak dibuat ulang.
        Schema::create('padel_recurring_occurrences', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('recurring_schedule_id')->constrained('padel_recurring_schedules')->cascadeOnDelete();
            $table->foreignUlid('court_id')->constrained('padel_courts')->cascadeOnDelete();
            $table->date('occurrence_date');
            $table->foreignUlid('booking_id')->nullable()->constrained('padel_bookings')->nullOnDelete();
            $table->string('status', 10); // PENDING | BOOKED | SKIPPED | FAILED
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['recurring_schedule_id', 'court_id', 'occurrence_date'], 'uq_recurring_occurrence');
        });

        Schema::table('padel_bookings', function (Blueprint $table) {
            $table->foreignUlid('recurring_schedule_id')->nullable()->after('coaching_booking_id')
                ->constrained('padel_recurring_schedules')->nullOnDelete();
        });

        // hasColumn: aman di database yang sempat menjalankan versi awal migration ini.
        if (! Schema::hasColumn('booking_time_settings', 'reschedule_cutoff_hours')) {
            Schema::table('booking_time_settings', function (Blueprint $table) {
                $table->unsignedSmallInteger('reschedule_cutoff_hours')->default(2)->after('payment_window_minutes');
            });
        }

        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            Club61PermissionMatrix::grantNewPermissionsToPresetRoles('web');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('booking_time_settings', 'reschedule_cutoff_hours')) {
            Schema::table('booking_time_settings', function (Blueprint $table) {
                $table->dropColumn('reschedule_cutoff_hours');
            });
        }
        Schema::table('padel_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recurring_schedule_id');
        });
        Schema::dropIfExists('padel_recurring_occurrences');
        Schema::dropIfExists('padel_recurring_schedules');
    }
};
