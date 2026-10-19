<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul 27: jejak setiap notifikasi DOKU yang LOLOS verifikasi tanda tangan. `request_id` unik = notifikasi dobel
 * diproses sekali. Header Signature & data pribadi customer tidak disimpan. Dipangkas otomatis setelah 90 hari.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doku_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('request_id', 128)->unique();
            $table->string('invoice_number', 64)->nullable()->index();
            $table->string('status', 20)->nullable();
            $table->decimal('amount', 14, 2)->nullable();
            // received → processed / ignored / rejected; retry & unmatched diproses ulang rekonsiliasi berkala.
            $table->string('result', 20)->default('received')->index();
            $table->string('result_note', 255)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->json('payload')->nullable();
            $table->timestamp('received_at')->index();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doku_notifications');
    }
};
