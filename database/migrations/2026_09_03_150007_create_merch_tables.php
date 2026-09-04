<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_products', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 100);
            $table->string('brand', 50)->default('Club 61 Official');
            $table->text('description')->nullable();
            $table->decimal('base_price', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('merch_variants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('product_id')->constrained('merch_products')->cascadeOnDelete();
            $table->string('sku', 50)->unique();
            $table->string('color', 30);
            $table->string('size', 10);
            $table->decimal('additional_price', 12, 2)->default(0.00);
            $table->integer('stock_quantity')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_variants');
        Schema::dropIfExists('merch_products');
    }
};
