<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('padel_bookings', function (Blueprint $table) {
            $table->foreignUlid('membership_balance_id')
                ->nullable()
                ->after('user_id')
                ->constrained('user_membership_balances')
                ->nullOnDelete();

            $table->decimal('member_discount_court', 12, 2)
                ->default(0)
                ->after('court_fee');

            $table->decimal('member_hours_consumed', 8, 2)
                ->nullable()
                ->after('member_discount_court');
        });

        Schema::table('wellness_bookings', function (Blueprint $table) {
            $table->foreignUlid('membership_balance_id')
                ->nullable()
                ->after('user_id')
                ->constrained('user_membership_balances')
                ->nullOnDelete();

            $table->decimal('member_discount_amount', 12, 2)
                ->default(0)
                ->after('total_amount');

            $table->integer('member_sessions_consumed')
                ->nullable()
                ->after('member_discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('wellness_bookings', function (Blueprint $table) {
            $table->dropForeign(['membership_balance_id']);
            $table->dropColumn([
                'membership_balance_id',
                'member_discount_amount',
                'member_sessions_consumed',
            ]);
        });

        Schema::table('padel_bookings', function (Blueprint $table) {
            $table->dropForeign(['membership_balance_id']);
            $table->dropColumn([
                'membership_balance_id',
                'member_discount_court',
                'member_hours_consumed',
            ]);
        });
    }
};
