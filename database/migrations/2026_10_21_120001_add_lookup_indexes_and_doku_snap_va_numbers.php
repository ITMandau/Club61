<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit go-live 21 Okt 2026 (G31, G35–G37) — hanya MENAMBAH, tidak mengubah data:
 *   - `doku_snap_va_numbers`: nomor VA SNAP → invoice, diisi saat VA dibuat. Notifikasi VA tanpa trxId langsung ketemu
 *     tagihannya (dulu semua tagihan DOKU 7 hari ditarik lalu dicocokkan satu per satu). Dipangkas setelah 90 hari.
 *   - payments(payment_gateway, status, updated_at): rekonsiliasi DOKU tiap 5 menit.
 *   - orders(created_at) & orders(order_type, created_at): hitungan order "hari ini" di kasir & nomor antrian F&B.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doku_snap_va_numbers', function (Blueprint $table) {
            $table->id();
            // Angka saja. Tidak unik: nomor VA bisa dipakai ulang DOKU setelah tagihan lama selesai.
            $table->string('va_number', 32)->index();
            $table->string('invoice_number', 64);
            $table->timestamp('created_at')->index();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['payment_gateway', 'status', 'updated_at'], 'idx_payments_gateway_status_updated');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index('created_at', 'idx_orders_created_at');
            $table->index(['order_type', 'created_at'], 'idx_orders_type_created');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_type_created');
            $table->dropIndex('idx_orders_created_at');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('idx_payments_gateway_status_updated');
        });

        Schema::dropIfExists('doku_snap_va_numbers');
    }
};
