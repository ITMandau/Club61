<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_cashier_shifts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('shift_number', 50)->unique();
            $table->string('counter', 30)->default('PADEL_FRONTDESK')->index();
            $table->string('status', 20)->default('OPEN'); // OPEN, CLOSED
            $table->foreignUlid('opened_by_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('closed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->decimal('starting_cash', 12, 2)->default(0.00);
            $table->decimal('expected_cash', 12, 2)->default(0.00);
            $table->decimal('actual_cash', 12, 2)->nullable();
            $table->decimal('cash_difference', 12, 2)->default(0.00);
            $table->decimal('total_cash_sales', 12, 2)->default(0.00);
            $table->decimal('total_edc_bca_sales', 12, 2)->default(0.00);
            $table->decimal('total_edc_mandiri_sales', 12, 2)->default(0.00);
            $table->decimal('total_qris_sales', 12, 2)->default(0.00);
            $table->decimal('total_other_sales', 12, 2)->default(0.00);
            $table->decimal('total_sales', 12, 2)->default(0.00);
            $table->integer('total_transactions')->default(0);
            $table->text('opening_notes')->nullable();
            $table->text('closing_notes')->nullable();
            $table->timestamps();

            $table->index(['counter', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_cashier_shifts');
    }
};
