<?php

use App\Services\Payment\OnlinePaymentCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modul 27 Tahap 1: metode pembayaran online bisa DITAMBAH dari admin (jenis QRIS / VA), kanal tiap gateway dipilih dari
 * daftar kode resmi. Metode bawaan diisi dari katalog — kanalnya tetap dibaca dari katalog (bukan kolom ini) supaya
 * transaksi lama tidak berubah; kolom ini diisi agar halaman admin bisa menampilkannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('online_payment_methods', function (Blueprint $table) {
            $table->string('kind', 10)->nullable()->after('badge');
            $table->json('midtrans_channels')->nullable()->after('kind');
            $table->json('doku_channels')->nullable()->after('midtrans_channels');
            $table->boolean('is_custom')->default(false)->after('doku_channels');
        });

        foreach (OnlinePaymentCatalog::all() as $code => $method) {
            DB::table('online_payment_methods')->where('code', $code)->update([
                'kind' => $method['group'],
                'midtrans_channels' => json_encode($method['midtrans']),
                'doku_channels' => json_encode($method['doku']),
                'is_custom' => false,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('online_payment_methods')->where('is_custom', true)->delete();

        Schema::table('online_payment_methods', function (Blueprint $table) {
            $table->dropColumn(['kind', 'midtrans_channels', 'doku_channels', 'is_custom']);
        });
    }
};
