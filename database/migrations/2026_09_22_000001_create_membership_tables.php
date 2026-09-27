<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop stub tabel lama gym jika ada
        Schema::dropIfExists('gym_checkins');
        Schema::dropIfExists('gym_memberships');
        Schema::dropIfExists('gym_packages');

        // 2. Tabel sequence kode membership (atomic counter)
        Schema::create('membership_code_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        // 3. Template paket membership (dikelola admin)
        Schema::create('membership_plans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('ownership_type', 15)->default('INDIVIDUAL'); // INDIVIDUAL | ORGANIZATIONAL
            $table->integer('duration_days')->default(30);
            $table->decimal('price', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // 4. Matrix benefit per fasilitas
        Schema::create('membership_plan_benefits', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('plan_id')->constrained('membership_plans')->cascadeOnDelete();
            $table->string('facility', 10); // PADEL | GYM | SAUNA
            $table->string('quota_type', 10); // HOURS | VISITS | NONE
            $table->decimal('quota_value', 8, 2)->nullable();
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->integer('booking_priority_days')->default(0);
            $table->time('time_window_start')->nullable();
            $table->time('time_window_end')->nullable();
            $table->json('extra_benefits')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'facility'], 'uniq_plan_facility');
        });

        // 5. Kartu member customer / sponsor
        Schema::create('user_memberships', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('membership_code', 30)->unique();
            $table->string('owner_type', 15)->default('INDIVIDUAL'); // INDIVIDUAL | ORGANIZATIONAL
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('plan_id')->constrained('membership_plans')->cascadeOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignUlid('renewal_of_id')->nullable()->constrained('user_memberships')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('PENDING_PAYMENT'); // PENDING_PAYMENT | ACTIVE | EXPIRED | FROZEN | UPGRADED | CANCELLED | MERGED
            $table->string('qr_pass_hash', 100)->unique()->nullable();
            $table->decimal('purchase_price_snapshot', 12, 2)->default(0);
            $table->decimal('manual_discount_percent', 5, 2)->default(0);
            $table->string('manual_discount_reason', 255)->nullable();
            $table->foreignUlid('sold_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // 6. Saldo kuota ter-snapshot per fasilitas
        Schema::create('user_membership_balances', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_membership_id')->constrained('user_memberships')->cascadeOnDelete();
            $table->string('facility', 10); // PADEL | GYM | SAUNA
            $table->string('quota_type', 10); // HOURS | VISITS | NONE
            $table->decimal('initial_quota', 8, 2)->nullable();
            $table->decimal('remaining_quota', 8, 2)->default(0); // Mulai dari nol, diisi saat TOPUP
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->integer('booking_priority_days')->default(0);
            $table->time('time_window_start')->nullable();
            $table->time('time_window_end')->nullable();
            $table->json('extra_benefits')->nullable();
            $table->timestamps();

            $table->unique(['user_membership_id', 'facility'], 'uniq_user_mbr_balance_facility');
        });

        // 7. Audit log mutasi kuota (immutable, tanpa updated_at)
        Schema::create('membership_usage_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('balance_id')->constrained('user_membership_balances')->cascadeOnDelete();
            $table->string('change_type', 20); // DECREMENT | REVERSAL | MANUAL_ADJUSTMENT | TOPUP | ROLLOVER_IN | ROLLOVER_OUT
            $table->decimal('quantity', 8, 2);
            $table->string('related_type', 100)->nullable();
            $table->string('related_id', 36)->nullable();
            $table->foreignUlid('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // 8. Checkin fasilitas generik (Gym tap access)
        Schema::create('facility_checkins', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('facility', 10)->default('GYM');
            $table->foreignUlid('balance_id')->constrained('user_membership_balances')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('checkin_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_checkins');
        Schema::dropIfExists('membership_usage_logs');
        Schema::dropIfExists('user_membership_balances');
        Schema::dropIfExists('user_memberships');
        Schema::dropIfExists('membership_plan_benefits');
        Schema::dropIfExists('membership_plans');
        Schema::dropIfExists('membership_code_sequences');
    }
};
