<?php

use App\Services\Permission\Club61PermissionMatrix;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Modul 24 Opsi B Tahap 1 — coaching via academy (sewa lapangan harga khusus). Lihat
 * docs/PRD_MODUL_24_COACHING_OPSI_B_SEWA_LAPANGAN_ACADEMY.md. Hanya menambah tabel / kolom; kolom lama
 * padel_bookings.coach_id (→ staff_profiles) tidak disentuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coaching_academies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 100);
            $table->string('pic_name', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('coaches', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('academy_id')->nullable()->constrained('coaching_academies')->nullOnDelete();
            $table->string('name', 100);
            $table->string('title', 20)->default('COACH'); // HEAD_COACH, COACH
            $table->string('phone', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Aturan tarif setoran: hari (ISO 1=Senin … 7=Minggu) + jam mulai slot [start, end) + harga per jam.
        Schema::create('coaching_rates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 100);
            $table->json('days');
            $table->string('start_time', 5); // HH:MM
            $table->string('end_time', 5);   // HH:MM, "24:00" = tengah malam
            $table->decimal('price_per_hour', 12, 2);
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('coaching_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('extra_coach_fee_per_hour', 12, 2)->default(0);
            $table->string('admin_whatsapp', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('coaching_bookings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignUlid('head_coach_id')->constrained('coaches')->restrictOnDelete();
            $table->string('academy_name', 100)->nullable(); // snapshot
            $table->json('students')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('extra_coach_fee_per_hour', 12, 2)->default(0); // snapshot
            $table->json('pricing')->nullable(); // rincian tarif per slot saat dibuat
            $table->foreignUlid('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('coaching_booking_coaches', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('coaching_booking_id')->constrained('coaching_bookings')->cascadeOnDelete();
            $table->foreignUlid('coach_id')->constrained('coaches')->restrictOnDelete();
            $table->string('role', 10)->default('EXTRA'); // HEAD, EXTRA
            $table->unique(['coaching_booking_id', 'coach_id']);
            $table->index('coach_id');
        });

        Schema::table('padel_bookings', function (Blueprint $table) {
            $table->foreignUlid('coaching_booking_id')->nullable()->after('coach_id')->constrained('coaching_bookings')->nullOnDelete();
        });

        // Tarif awal sesuai daftar harga PM (bisa diubah admin). Pengaturan: biaya coach tambahan Rp0.
        $now = now();
        $everyDay = json_encode([1, 2, 3, 4, 5, 6, 7]);
        DB::table('coaching_rates')->insert([
            ['id' => (string) Str::ulid(), 'name' => 'Pagi – Sore', 'days' => $everyDay, 'start_time' => '06:00', 'end_time' => '17:00', 'price_per_hour' => 100000, 'priority' => 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => (string) Str::ulid(), 'name' => 'Malam', 'days' => $everyDay, 'start_time' => '17:00', 'end_time' => '24:00', 'price_per_hour' => 150000, 'priority' => 20, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('coaching_settings')->insert(['extra_coach_fee_per_hour' => 0, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('coaching_academies')->insert(['id' => (string) Str::ulid(), 'name' => 'SR Academy', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);

        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            Club61PermissionMatrix::grantNewPermissionsToPresetRoles('web');
        }
    }

    public function down(): void
    {
        Schema::table('padel_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coaching_booking_id');
        });
        Schema::dropIfExists('coaching_booking_coaches');
        Schema::dropIfExists('coaching_bookings');
        Schema::dropIfExists('coaching_settings');
        Schema::dropIfExists('coaching_rates');
        Schema::dropIfExists('coaches');
        Schema::dropIfExists('coaching_academies');
        // Izin sengaja tidak dihapus: bisa sudah dicentang manual ke role lain.
    }
};
