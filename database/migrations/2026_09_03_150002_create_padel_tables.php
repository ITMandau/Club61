<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('padel_courts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 50);
            $table->string('type', 20)->default('INDOOR'); // INDOOR, OUTDOOR
            $table->decimal('hourly_rate_regular', 12, 2);
            $table->decimal('hourly_rate_prime', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('court_equipments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 100);
            $table->string('type', 30)->default('RACKET'); // RACKET, BALL, TOWEL
            $table->decimal('rental_price', 12, 2);
            $table->integer('stock_quantity')->default(0);
            $table->timestamps();
        });

        Schema::create('padel_bookings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('booking_code', 30)->unique();
            $table->string('order_id', 50)->nullable()->index();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('court_id')->constrained('padel_courts')->cascadeOnDelete();
            $table->foreignUlid('coach_id')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->date('booking_date');
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->decimal('court_fee', 12, 2);
            $table->decimal('coach_fee', 12, 2)->default(0.00);
            $table->decimal('equipment_fee', 12, 2)->default(0.00);
            $table->decimal('total_amount', 12, 2);
            $table->string('status', 20)->default('PENDING'); // PENDING, LOCKED, PAID, CHECKED_IN, COMPLETED, CANCELLED, EXPIRED
            $table->string('qr_code_hash', 100)->nullable();
            $table->dateTime('checked_in_at')->nullable();
            $table->unsignedInteger('reschedule_count')->default(0);
            $table->text('cancel_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['court_id', 'booking_date', 'start_time', 'end_time'], 'idx_padel_court_schedule');
            $table->index(['coach_id', 'booking_date', 'start_time', 'end_time'], 'idx_padel_coach_schedule');
        });

        Schema::create('padel_booking_equipments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('booking_id')->constrained('padel_bookings')->cascadeOnDelete();
            $table->string('order_id', 50)->nullable()->index();
            $table->foreignUlid('equipment_id')->constrained('court_equipments')->cascadeOnDelete();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('padel_booking_equipments');
        Schema::dropIfExists('padel_bookings');
        Schema::dropIfExists('court_equipments');
        Schema::dropIfExists('padel_courts');
    }
};
