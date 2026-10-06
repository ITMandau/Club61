<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Metode online yang boleh dipakai kasir untuk "Bayar Otomatis" (popup pembayaran di layar POS Walk-In, F&B, Jual
 * Membership). Diatur dari menu Metode Pembayaran Online; default hanya QRIS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('online_payment_methods', function (Blueprint $table) {
            $table->boolean('show_at_pos')->default(false)->after('is_active');
        });

        DB::table('online_payment_methods')->where('code', 'QRIS')->update(['show_at_pos' => true]);

        // Daftar metode di-cache 5 menit. Request antara deploy kode & migrate menyimpan show_at_pos = false (kolom belum
        // ada) → kasir tidak melihat "Bayar Otomatis" sampai cache kedaluwarsa.
        \Illuminate\Support\Facades\Cache::forget(\App\Services\Payment\OnlinePaymentMethodService::CACHE_KEY);
    }

    public function down(): void
    {
        Schema::table('online_payment_methods', function (Blueprint $table) {
            $table->dropColumn('show_at_pos');
        });
    }
};
