<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignUlid('pos_shift_id')->nullable()->after('cashier_id')->constrained('pos_cashier_shifts')->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignUlid('pos_shift_id')->nullable()->after('order_id')->constrained('pos_cashier_shifts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['pos_shift_id']);
            $table->dropColumn('pos_shift_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['pos_shift_id']);
            $table->dropColumn('pos_shift_id');
        });
    }
};
