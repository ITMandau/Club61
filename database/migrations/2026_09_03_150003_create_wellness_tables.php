<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wellness_facilities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 50); // Cold Plunge / Ice Bath, Finnish Sauna
            $table->integer('max_capacity_per_slot')->default(6);
            $table->integer('duration_minutes')->default(45);
            $table->decimal('price_per_person', 12, 2);
            $table->timestamps();
        });

        Schema::create('wellness_slots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('facility_id')->constrained('wellness_facilities')->cascadeOnDelete();
            $table->date('session_date');
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->integer('max_capacity')->default(6);
            $table->integer('booked_count')->default(0);
            $table->string('status', 20)->default('AVAILABLE'); // AVAILABLE, FULL, CLOSED
            $table->timestamps();

            $table->index(['facility_id', 'session_date', 'start_time'], 'idx_wellness_facility_schedule');
        });

        Schema::create('wellness_bookings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('booking_code', 30)->unique();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('slot_id')->constrained('wellness_slots')->cascadeOnDelete();
            $table->integer('num_persons')->default(1);
            $table->decimal('total_amount', 12, 2);
            $table->string('status', 20)->default('PENDING'); // PENDING, PAID, CHECKED_IN, CANCELLED
            $table->string('qr_code_hash', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('wellness_waitlists', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('slot_id')->constrained('wellness_slots')->cascadeOnDelete();
            $table->unsignedInteger('queue_number');
            $table->string('status', 20)->default('WAITING'); // WAITING, NOTIFIED, CLAIMED, EXPIRED
            $table->dateTime('priority_expires_at')->nullable();
            $table->timestamps();

            $table->index(['slot_id', 'status', 'queue_number'], 'idx_wellness_waitlist_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wellness_waitlists');
        Schema::dropIfExists('wellness_bookings');
        Schema::dropIfExists('wellness_slots');
        Schema::dropIfExists('wellness_facilities');
    }
};
