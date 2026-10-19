<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Riwayat Import Penjualan: satu baris per import (file asli disimpan untuk bukti) + status pembatalan. Import yang
 * terjadi sebelum tabel ini ada dibuatkan barisnya dari penanda di pembayaran (payload_log.import_batch).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_import_batches', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('batch_code', 40)->unique();
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->foreignUlid('imported_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('padel_transactions')->default(0);
            $table->unsignedInteger('padel_bookings')->default(0);
            $table->decimal('padel_total', 14, 2)->default(0);
            $table->unsignedInteger('fnb_transactions')->default(0);
            $table->decimal('fnb_total', 14, 2)->default(0);
            $table->json('new_customer_ids')->nullable();
            $table->string('status', 20)->default('IMPORTED'); // IMPORTED, CANCELLED
            $table->foreignUlid('cancelled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();
        });

        // Import lama (sebelum ada riwayat): rangkum dari pembayaran bertanda import.
        $batches = [];
        foreach (DB::table('payments')->where('payment_gateway', 'CASHIER_POS')->where('payload_log', 'like', '%IMPORT_EXCEL%')->get(['order_id', 'amount', 'payload_log']) as $payment) {
            $log = json_decode((string) $payment->payload_log, true) ?: [];
            $code = $log['import_batch'] ?? null;
            if (($log['source'] ?? null) !== 'IMPORT_EXCEL' || ! $code) {
                continue;
            }
            $orderNumber = (string) DB::table('orders')->where('id', $payment->order_id)->value('order_number');
            $isPadel = str_starts_with($orderNumber, 'IMP-PAD-');
            $batches[$code] ??= ['imported_by_id' => $log['imported_by_id'] ?? null, 'padel_transactions' => 0, 'padel_bookings' => 0, 'padel_total' => 0, 'fnb_transactions' => 0, 'fnb_total' => 0];
            $batches[$code][$isPadel ? 'padel_transactions' : 'fnb_transactions']++;
            $batches[$code][$isPadel ? 'padel_total' : 'fnb_total'] += (float) $payment->amount;
            if ($isPadel) {
                $batches[$code]['padel_bookings'] += DB::table('padel_bookings')->where('order_id', $payment->order_id)->count();
            }
        }
        foreach ($batches as $code => $row) {
            $imported = preg_match('/^IMP-(\d{14})-/', $code, $m) ? \Carbon\Carbon::createFromFormat('YmdHis', $m[1], 'Asia/Jakarta')->setTimezone(config('app.timezone')) : now();
            DB::table('sales_import_batches')->insert($row + [
                'id' => strtolower((string) Str::ulid()),
                'batch_code' => $code,
                'imported_by_id' => DB::table('users')->where('id', $row['imported_by_id'])->exists() ? $row['imported_by_id'] : null,
                'status' => 'IMPORTED',
                'created_at' => $imported,
                'updated_at' => $imported,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_import_batches');
    }
};
