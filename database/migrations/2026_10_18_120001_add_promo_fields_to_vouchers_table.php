<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul 22 tahap 2 — voucher promo dibuat dari halaman Marketing (cara sebar A: kode umum). Isian per promo (PRD §4.2):
 * nama, cara sebar, kategori yang dipotong, kanal, boleh digabung benefit member, tanggal mulai, pembuat / pengubah.
 * Voucher saldo (CREDIT) tidak memakai kolom-kolom ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->string('name', 100)->nullable()->after('code');
            $table->string('distribution', 10)->default('CODE')->after('name'); // CODE (A) | UNIQUE (B) | CLAIM (C) | AUTO (D)
            $table->json('categories')->nullable()->after('max_discount_amount'); // null = PADEL
            $table->string('channel', 10)->default('ALL')->after('categories'); // ALL | ONLINE | POS
            $table->boolean('stack_with_membership')->default(false)->after('channel');
            $table->dateTime('valid_from')->nullable()->after('used_count');
            $table->foreignUlid('created_by_id')->nullable()->after('is_active')->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by_id')->nullable()->after('created_by_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_id');
            $table->dropConstrainedForeignId('updated_by_id');
            $table->dropColumn(['name', 'distribution', 'categories', 'channel', 'stack_with_membership', 'valid_from']);
        });
    }
};
