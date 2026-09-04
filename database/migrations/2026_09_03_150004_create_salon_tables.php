<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salon_services', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 100);
            $table->integer('duration_minutes')->default(45);
            $table->decimal('price', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('salon_appointments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('appointment_code', 30)->unique();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('stylist_id')->constrained('staff_profiles')->cascadeOnDelete();
            $table->date('appointment_date');
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->integer('total_duration_minutes');
            $table->decimal('total_amount', 12, 2);
            $table->string('status', 20)->default('PENDING'); // PENDING, PAID, IN_SERVICE, COMPLETED, CANCELLED
            $table->string('qr_code_hash', 100)->nullable();
            $table->timestamps();

            $table->index(['stylist_id', 'appointment_date', 'start_time', 'end_time'], 'idx_salon_stylist_schedule');
        });

        Schema::create('salon_appointment_services', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('appointment_id')->constrained('salon_appointments')->cascadeOnDelete();
            $table->foreignUlid('service_id')->constrained('salon_services')->cascadeOnDelete();
            $table->unsignedInteger('sequence_order')->default(1);
            $table->integer('duration_minutes');
            $table->decimal('price', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salon_appointment_services');
        Schema::dropIfExists('salon_appointments');
        Schema::dropIfExists('salon_services');
    }
};
