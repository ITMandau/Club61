<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul 17 — Buku Transaksi Terpadu. Satu baris = satu kategori pendapatan dari satu pembayaran / refund.
 * Ditulis sekali saat uang masuk / refund diproses, tidak pernah diubah (lihat App\Models\Finance\LedgerEntry).
 *
 * order_id / payment_id / refund_id sengaja TANPA foreign key: FK cascade akan ikut menghapus baris buku saat
 * order/payment dihapus, padahal buku harus tetap terbaca (nomor order, nama customer & kasir disimpan sebagai snapshot).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->dateTime('occurred_at')->index();
            $table->string('entry_type', 20); // PAYMENT, OVERPAYMENT, REFUND
            $table->string('source', 40)->index();
            $table->string('category', 30)->index();

            $table->ulid('order_id')->index();
            $table->ulid('payment_id')->nullable()->index();
            $table->ulid('refund_id')->nullable()->index();
            // Kunci idempotensi: "P:{payment_id}:{category}" / "R:{refund_id}:{category}". Unique key PRD
            // (payment_id + category + entry_type) tidak bisa dipakai: satu pembayaran bisa direfund berkali-kali.
            $table->string('dedupe_key', 80)->unique();

            $table->string('order_number', 35);
            $table->ulid('customer_id')->nullable();
            $table->string('customer_name', 150)->nullable();
            $table->ulid('cashier_id')->nullable();
            $table->string('cashier_name', 150)->nullable();
            $table->ulid('pos_shift_id')->nullable()->index();

            $table->string('payment_gateway', 30)->nullable();
            $table->string('payment_method', 40)->nullable();
            $table->string('payment_method_label', 120)->nullable();
            $table->string('payment_reference', 120)->nullable();

            $table->decimal('gross_amount', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('net_amount', 14, 2)->default(0);
            $table->decimal('service_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->decimal('benefit_amount', 14, 2)->default(0);

            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['occurred_at', 'source'], 'idx_ledger_date_source');
            $table->index(['occurred_at', 'category'], 'idx_ledger_date_category');
            $table->index(['occurred_at', 'payment_method'], 'idx_ledger_date_method');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
