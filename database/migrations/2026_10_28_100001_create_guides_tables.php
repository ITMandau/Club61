<?php

use App\Services\Permission\Club61PermissionMatrix;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Panduan Karyawan (Karyawan & Akses → Panduan): PDF yang diunggah admin diubah jadi gambar per halaman di server, lalu
 * dibaca lewat link rahasia tanpa login (seperti layar Digital Signage) — file aslinya tidak pernah dikirim ke browser.
 * Izin baru manage_guides diberikan ke role yang preset-nya memuatnya (admin); izin lain tidak disentuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('guides')) {
            Schema::create('guides', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('title', 150);
                $table->text('description')->nullable();
                $table->string('version', 30)->nullable();
                $table->string('token', 64)->unique();
                $table->boolean('is_active')->default(false);
                $table->string('pages_path')->nullable(); // folder gambar halaman di disk local (private)
                $table->unsignedSmallInteger('pages_count')->default(0);
                $table->string('source_name')->nullable(); // nama file asli (hanya info)
                $table->foreignUlid('created_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('guide_access_logs')) {
            Schema::create('guide_access_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignUlid('guide_id')->constrained('guides')->cascadeOnDelete();
                $table->string('ip', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->timestamp('viewed_at')->index();
            });
        }

        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            Club61PermissionMatrix::grantNewPermissionsToPresetRoles('web');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_access_logs');
        Schema::dropIfExists('guides');
    }
};
