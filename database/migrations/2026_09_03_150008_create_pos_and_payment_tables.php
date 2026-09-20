<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('order_number', 35)->unique();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('order_type', 20)->default('DINE_IN'); // DINE_IN, TAKE_AWAY, DELIVERY, ONLINE_BOOKING, RETAIL, WALK_IN
            $table->string('table_number', 20)->nullable();
            $table->text('delivery_address')->nullable();
            $table->decimal('delivery_fee', 12, 2)->default(0.00);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0.00);
            $table->decimal('tax_amount', 12, 2)->default(0.00);
            $table->decimal('service_charge', 12, 2)->default(0.00);
            $table->decimal('grand_total', 12, 2);
            $table->string('payment_status', 20)->default('UNPAID'); // UNPAID, PARTIALLY_PAID, PAID, REFUNDED, CANCELLED
            $table->boolean('is_split_bill')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('item_type', 20); // PADEL, WELLNESS, SALON, GYM, FNB, MERCH
            $table->string('reference_id', 26)->nullable(); // ULID target item
            $table->string('item_name', 150);
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('order_item_modifiers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignUlid('modifier_option_id')->constrained('fnb_modifier_options')->cascadeOnDelete();
            $table->string('modifier_name', 100);
            $table->decimal('extra_price', 12, 2)->default(0.00);
            $table->timestamps();
        });

        Schema::create('kitchen_tickets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('station', 20)->default('BAR'); // KITCHEN, BAR
            $table->string('status', 20)->default('QUEUED'); // QUEUED, COOKING, READY, SERVED
            $table->dateTime('created_at');
            $table->dateTime('served_at')->nullable();
        });

        Schema::create('bill_splits', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('payer_name', 100);
            $table->string('split_type', 20)->default('EQUAL'); // EQUAL, BY_ITEM
            $table->decimal('amount_due', 12, 2);
            $table->string('status', 20)->default('PENDING'); // PENDING, PAID
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bill_split_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('bill_split_id')->constrained('bill_splits')->cascadeOnDelete();
            $table->foreignUlid('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->integer('quantity_allocated')->default(1);
            $table->decimal('allocated_amount', 12, 2);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUlid('bill_split_id')->nullable()->constrained('bill_splits')->nullOnDelete();
            $table->string('payment_gateway', 30)->default('MIDTRANS'); // MIDTRANS, XENDIT, CASH, EDC_BCA
            $table->string('transaction_id', 100)->unique();
            $table->string('snap_token', 255)->nullable();
            $table->text('payment_url')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 30)->default('QRIS'); // QRIS, CREDIT_CARD, BANK_TRANSFER, CASH
            $table->string('status', 20)->default('PENDING'); // PENDING, SUCCESS, FAILED, EXPIRED
            $table->json('payload_log')->nullable();
            $table->timestamps();
        });

        Schema::create('vouchers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 30)->unique();
            $table->string('discount_type', 20)->default('PERCENT'); // PERCENT, FIXED
            $table->decimal('discount_value', 12, 2);
            $table->decimal('min_order_amount', 12, 2)->default(0.00);
            $table->decimal('max_discount_amount', 12, 2)->nullable();
            $table->integer('quota')->default(100);
            $table->integer('used_count')->default(0);
            $table->dateTime('valid_until');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUlid('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->decimal('refund_amount', 12, 2);
            $table->text('reason');
            $table->string('status', 20)->default('PENDING'); // PENDING, APPROVED, PROCESSED, REJECTED
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('bill_split_items');
        Schema::dropIfExists('bill_splits');
        Schema::dropIfExists('kitchen_tickets');
        Schema::dropIfExists('order_item_modifiers');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
