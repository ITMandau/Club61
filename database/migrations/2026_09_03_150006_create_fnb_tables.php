<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fnb_categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 50);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('fnb_menus', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('category_id')->constrained('fnb_categories')->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->text('image_url')->nullable();
            $table->decimal('base_price', 12, 2);
            $table->string('station', 20)->default('BAR'); // BAR, KITCHEN
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });

        Schema::create('fnb_modifier_groups', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 50); // Sugar Level, Milk Option
            $table->boolean('is_required')->default(false);
            $table->integer('max_selection')->default(1);
            $table->timestamps();
        });

        Schema::create('fnb_modifier_options', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('group_id')->constrained('fnb_modifier_groups')->cascadeOnDelete();
            $table->string('name', 50);
            $table->decimal('extra_price', 12, 2)->default(0.00);
            $table->timestamps();
        });

        Schema::create('raw_materials', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->string('unit', 20); // GRAM, ML, PCS
            $table->decimal('current_stock', 12, 2)->default(0.00);
            $table->decimal('min_alert_stock', 12, 2)->default(0.00);
            $table->timestamps();
        });

        Schema::create('recipe_boms', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('menu_id')->constrained('fnb_menus')->cascadeOnDelete();
            $table->foreignUlid('raw_material_id')->constrained('raw_materials')->cascadeOnDelete();
            $table->decimal('quantity_used', 12, 2); // 18.00 gram, 200.00 ml
            $table->timestamps();
        });

        Schema::create('table_qr_codes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('table_number', 20)->unique();
            $table->string('qr_hash', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_qr_codes');
        Schema::dropIfExists('recipe_boms');
        Schema::dropIfExists('raw_materials');
        Schema::dropIfExists('fnb_modifier_options');
        Schema::dropIfExists('fnb_modifier_groups');
        Schema::dropIfExists('fnb_menus');
        Schema::dropIfExists('fnb_categories');
    }
};
