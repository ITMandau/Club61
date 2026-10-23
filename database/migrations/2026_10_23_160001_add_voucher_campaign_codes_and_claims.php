<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul 22 tahap 3 — cara sebar B (kode unik massal) & C (klaim ke akun). Hanya menambah kolom / tabel.
 *
 * B: satu baris kampanye (distribution UNIQUE, campaign_id null) + satu baris per kode (campaign_id = kampanye, kuota 1)
 *    di tabel vouchers yang sama — jadi checkout, pemotongan kuota saat lunas, pengembalian saat batal, struk, dan refund
 *    memakai jalur voucher yang sudah ada.
 * C: voucher_claims = akun yang sudah mengklaim promo (1 akun 1 klaim); promo CLAIM hanya bisa dipakai akun yang klaim.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->foreignUlid('campaign_id')->nullable()->after('distribution')->constrained('vouchers')->cascadeOnDelete();
        });

        Schema::create('voucher_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('voucher_id')->constrained('vouchers')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('claimed_at');
            $table->timestamps();
            $table->unique(['voucher_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_claims');
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campaign_id');
        });
    }
};
