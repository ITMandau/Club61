<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Struk Bayar Otomatis dicetak otomatis SEKALI per order (aplikasi Club61) — refresh / tab lain / buka ulang tidak mencetak lagi. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('receipt_printed_at')->nullable()->after('invoice_emailed_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('receipt_printed_at');
        });
    }
};
