<?php

use App\Services\Permission\Club61PermissionMatrix;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Template Invoice (PDF lampiran email invoice lunas): satu baris setelan — logo, identitas usaha, judul & kalimat
 * dokumen, warna aksen. Kolom kosong = teks bawaan (InvoiceTemplateSetting::DEFAULTS). Izin baru View:TemplateInvoice &
 * manage_invoice_template diberikan ke role yang preset-nya memuatnya (admin); izin lain tidak disentuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoice_template_settings')) {
            Schema::create('invoice_template_settings', function (Blueprint $table) {
                $table->id();
                $table->string('logo_path')->nullable();
                $table->string('business_name', 120)->nullable();
                $table->string('address', 255)->nullable();
                $table->string('phone', 40)->nullable();
                $table->string('email', 120)->nullable();
                $table->string('website', 120)->nullable();
                $table->string('tax_id', 40)->nullable();
                $table->string('document_title', 40)->nullable();
                $table->string('status_label', 30)->nullable();
                $table->text('intro_text')->nullable();
                $table->text('notes_text')->nullable();
                $table->string('footer_text', 255)->nullable();
                $table->string('accent', 20)->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            Club61PermissionMatrix::grantNewPermissionsToPresetRoles('web');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_template_settings');
    }
};
