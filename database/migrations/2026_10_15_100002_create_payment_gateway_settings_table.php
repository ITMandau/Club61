<?php

use App\Services\Permission\Club61PermissionMatrix;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modul 27: gateway yang dipakai untuk tagihan BARU — pembayaran online customer dan QR otomatis kasir dipilih terpisah.
 * Tagihan yang sudah dibuat tetap diurus gateway yang membuatnya (OnlineGatewayRouter). Satu baris saja.
 * Izin `manage_payment_gateway` dibuat & diberikan hanya ke role yang preset-nya memuatnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_settings', function (Blueprint $table) {
            $table->id();
            $table->string('online_gateway', 20)->default('MIDTRANS');
            $table->string('pos_qris_gateway', 20)->default('MIDTRANS');
            $table->timestamps();
        });

        DB::table('payment_gateway_settings')->insert([
            'online_gateway' => 'MIDTRANS',
            'pos_qris_gateway' => 'MIDTRANS',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            Club61PermissionMatrix::grantNewPermissionsToPresetRoles('web');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_settings');
        // Izin sengaja tidak dihapus: bisa sudah dicentang manual ke role lain.
    }
};
