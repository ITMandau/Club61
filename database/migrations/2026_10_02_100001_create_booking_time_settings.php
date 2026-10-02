<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Waktu tahan slot & batas waktu bayar online dibuat bisa diatur admin (dulu tertulis 10 & 15 menit di kode),
 * dan batas waktunya disimpan PER BOOKING (`expires_at`). Dulu booking dihanguskan 15 menit sejak slot DITAHAN,
 * sementara sesi Midtrans berlaku 15 menit sejak KLIK BAYAR — customer yang bayar di menit ke-9 masih bisa bayar
 * di Midtrans padahal booking-nya sudah dilepas ke orang lain (berakhir refund).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_time_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('slot_hold_minutes')->default(10);
            $table->unsignedSmallInteger('payment_window_minutes')->default(15);
            $table->timestamps();
        });

        DB::table('booking_time_settings')->insert([
            'slot_hold_minutes' => 10,
            'payment_window_minutes' => 15,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('padel_bookings', function (Blueprint $table) {
            // Kapan slot dilepas kalau belum lunas: LOCKED = akhir waktu tahan; PENDING_PAYMENT = batas bayar
            // (dihitung dari klik bayar). Null = booking lama / buatan kasir → aturan lama berbasis created_at.
            $table->timestamp('expires_at')->nullable()->after('status');
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('padel_bookings', function (Blueprint $table) {
            $table->dropIndex(['status', 'expires_at']);
            $table->dropColumn('expires_at');
        });

        Schema::dropIfExists('booking_time_settings');
    }
};
