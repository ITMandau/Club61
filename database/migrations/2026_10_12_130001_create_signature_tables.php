<?php

use App\Services\Permission\Club61PermissionMatrix;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul 26 — Signature: layar TV venue (konten bergantian + monitoring lapangan). Yang diedit admin = draft (tabel di
 * bawah); TV hanya menayangkan snapshot JSON di signature_versions yang ditunjuk signature_screens.published_version_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signature_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 80);
            $table->string('layout', 20)->default('PRESET_A'); // PRESET_A, PRESET_B, PRESET_C, CUSTOM (tahap 2)
            $table->json('zones')->nullable();                 // susunan zona (persen) — hanya untuk CUSTOM
            $table->json('theme')->nullable();                 // warna & font
            $table->json('ticker')->nullable();                // pengaturan running text
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });

        Schema::create('signature_contents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 100);
            $table->string('type', 20);                        // IMAGE, VIDEO, MONITORING, SCHEDULE, SPONSOR, CLOCK
            $table->string('file_path')->nullable();
            $table->string('url', 500)->nullable();            // YouTube / link halaman (tahap 2)
            $table->unsignedInteger('duration_seconds')->default(15);
            $table->boolean('sound')->default(false);
            $table->unsignedTinyInteger('volume')->default(80);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('signature_playlist_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('template_id')->constrained('signature_templates')->cascadeOnDelete();
            $table->string('zone_key', 30)->default('main');
            $table->foreignUlid('content_id')->constrained('signature_contents')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('duration_seconds')->nullable(); // kosong = durasi konten
            $table->timestamps();
        });

        Schema::create('signature_running_texts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('kind', 20)->default('MANUAL');    // MANUAL, AUTO_CHECKIN, AUTO_BOOKING, AUTO_SLOTS, AUTO_SPONSOR
            $table->string('text', 300);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('signature_sponsors', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('kind', 20)->default('BRAND');      // BRAND (manual), CORPORATE (Sponsor Corporate)
            $table->string('name', 100)->nullable();
            $table->foreignUlid('sponsor_organization_id')->nullable()->constrained('sponsor_organizations')->nullOnDelete();
            $table->string('logo_path')->nullable();
            $table->unsignedInteger('duration_seconds')->default(8);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();
        });

        Schema::create('signature_screens', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 80);
            $table->string('token', 64)->unique();
            $table->foreignUlid('template_id')->nullable()->constrained('signature_templates')->nullOnDelete();
            $table->string('name_display', 20)->default('FULL'); // FULL, INITIALS, BOOKED
            $table->boolean('sound_enabled')->default(true);
            $table->boolean('operating_hours_only')->default(true);
            $table->ulid('published_version_id')->nullable();
            $table->dateTime('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('signature_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('screen_id')->constrained('signature_screens')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->longText('payload');
            $table->string('note', 120)->nullable();
            $table->foreignUlid('published_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('created_at');
            $table->unique(['screen_id', 'version']);
        });

        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            Club61PermissionMatrix::grantNewPermissionsToPresetRoles('web');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_versions');
        Schema::dropIfExists('signature_screens');
        Schema::dropIfExists('signature_sponsors');
        Schema::dropIfExists('signature_running_texts');
        Schema::dropIfExists('signature_playlist_items');
        Schema::dropIfExists('signature_contents');
        Schema::dropIfExists('signature_templates');
    }
};
