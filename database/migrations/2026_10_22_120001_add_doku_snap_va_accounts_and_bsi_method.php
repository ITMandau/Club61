<?php

use App\Services\Payment\OnlinePaymentCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Modul 27b lanjutan: VA bank lain (Mandiri, BRI, BNI, BSI) lewat DOKU SNAP.
 *   - payment_gateway_settings.doku_snap_va: Partner Service ID (BIN) + prefix customer no. per kanal VA dari dashboard
 *     DOKU — bukan rahasia, diisi admin (izin manage_payment_gateway). Kanal tanpa isian tetap lewat DOKU Checkout.
 *   - Metode bawaan baru BSI_SYARIAH_VA (BSI VA DOKU, nonaktif): kanal BSI dipisah dari "VA Bank Lain" yang sekarang
 *     hanya Permata di DOKU — SNAP VA hanya bisa satu bank per tagihan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_gateway_settings', function (Blueprint $table) {
            $table->json('doku_snap_va')->nullable()->after('pos_qris_gateway');
        });

        if (DB::table('online_payment_methods')->where('code', 'BSI_SYARIAH_VA')->doesntExist()) {
            $method = OnlinePaymentCatalog::all()['BSI_SYARIAH_VA'];
            DB::table('online_payment_methods')->insert([
                'id' => (string) Str::ulid(),
                'code' => 'BSI_SYARIAH_VA',
                'label' => $method['label'],
                'description' => $method['description'],
                'badge' => $method['badge'],
                'kind' => $method['group'],
                'midtrans_channels' => json_encode($method['midtrans']),
                'doku_channels' => json_encode($method['doku']),
                'is_custom' => false,
                'is_active' => false,
                'show_at_pos' => false,
                'sort_order' => (int) DB::table('online_payment_methods')->max('sort_order') + 1,
                'min_amount' => $method['default_min'],
                'max_amount' => $method['default_max'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Kolom tampilan saja (kanal bawaan dibaca dari katalog) — disamakan dengan katalog.
        DB::table('online_payment_methods')->where('code', 'BSI_VA')
            ->update(['doku_channels' => json_encode(OnlinePaymentCatalog::all()['BSI_VA']['doku'])]);
    }

    public function down(): void
    {
        Schema::table('payment_gateway_settings', function (Blueprint $table) {
            $table->dropColumn('doku_snap_va');
        });
        // Baris BSI_SYARIAH_VA sengaja tidak dihapus: transaksi lama bisa menyimpan kodenya.
    }
};
