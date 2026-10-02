<?php

use App\Services\Payment\OnlinePaymentCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Pengaturan metode pembayaran online (Midtrans) yang bisa diubah admin — dulu daftar metode ditulis manual di
 * 7 tempat (3 halaman checkout, 3 validasi API, pemetaan Midtrans) dan isinya berbeda-beda.
 * Kode metode = OnlinePaymentCatalog (tetap); tabel ini hanya menyimpan pengaturannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_payment_methods', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 30)->unique();
            $table->string('label', 100);
            $table->string('description', 255)->nullable();
            $table->string('badge', 10);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->decimal('min_amount', 14, 2)->nullable();
            $table->decimal('max_amount', 14, 2)->nullable();
            $table->timestamps();
        });

        $now = now();
        $order = 0;
        foreach (OnlinePaymentCatalog::all() as $code => $method) {
            DB::table('online_payment_methods')->insert([
                'id' => (string) Str::ulid(),
                'code' => $code,
                'label' => $method['label'],
                'description' => $method['description'],
                'badge' => $method['badge'],
                'is_active' => $method['default_active'],
                'sort_order' => $order++,
                'min_amount' => $method['default_min'],
                'max_amount' => $method['default_max'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('online_payment_methods');
    }
};
