<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('profession', 30); // TRAINER, THERAPIST, STYLIST, CASHIER, BARISTA
            $table->text('bio')->nullable();
            $table->decimal('hourly_rate', 12, 2)->default(0.00);
            $table->decimal('commission_rate', 5, 2)->default(0.00);
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });

        Schema::create('staff_schedules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('staff_id')->constrained('staff_profiles')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0 (Sun) - 6 (Sat)
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_day_off')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_schedules');
        Schema::dropIfExists('staff_profiles');
    }
};
