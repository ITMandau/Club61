<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gym_packages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 100);
            $table->integer('duration_days')->default(30);
            $table->integer('visit_limit')->nullable(); // NULL = Unlimited
            $table->decimal('price', 12, 2);
            $table->timestamps();
        });

        Schema::create('gym_memberships', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('membership_code', 30);
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('package_id')->constrained('gym_packages')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('remaining_visits')->nullable();
            $table->string('status', 20)->default('ACTIVE'); // ACTIVE, EXPIRED, FROZEN
            $table->string('qr_pass_hash', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['membership_code', 'deleted_at'], 'uniq_membership_code_softdelete');
        });

        Schema::create('gym_checkins', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('membership_id')->constrained('gym_memberships')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('checkin_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_checkins');
        Schema::dropIfExists('gym_memberships');
        Schema::dropIfExists('gym_packages');
    }
};
