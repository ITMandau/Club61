<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul 16 — Log Aktivitas & Jejak Audit. Satu tabel untuk semua jejak (staf, customer, sistem,
 * webhook). Sengaja tanpa updated_at & tanpa foreign key ke users: nama & role pelaku disimpan
 * sebagai snapshot supaya log lama tetap terbaca walau user-nya diganti nama / dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->timestamp('created_at')->useCurrent();

            $table->string('module', 30);
            $table->string('event', 60);
            $table->string('severity', 10)->default('INFO');
            $table->string('description', 255);

            $table->string('subject_type', 120)->nullable();
            $table->string('subject_id', 36)->nullable();
            $table->string('subject_label', 120)->nullable();

            $table->char('causer_id', 26)->nullable();
            $table->string('causer_name', 100)->nullable();
            $table->string('causer_role', 100)->nullable();
            $table->string('actor_type', 15);
            $table->string('channel', 20);

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('url', 255)->nullable();
            $table->string('http_method', 10)->nullable();

            $table->json('changes')->nullable();
            $table->json('meta')->nullable();
            $table->uuid('batch_id')->nullable();

            $table->index('created_at');
            $table->index(['causer_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['module', 'event']);
            $table->index(['severity', 'created_at']);
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
