<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('club_finance_settings', function (Blueprint $table) {
            $table->id();

            // Biaya Admin / Layanan
            $table->boolean('is_admin_fee_enabled')->default(false);
            $table->string('admin_fee_name', 50)->default('Biaya Layanan / Admin');
            $table->string('admin_fee_type', 20)->default('FIXED'); // FIXED, PERCENTAGE
            $table->decimal('admin_fee_amount', 12, 2)->default(2500.00);
            $table->string('admin_fee_channels', 20)->default('ONLINE_ONLY'); // ONLINE_ONLY, POS_ONLY, ALL

            // Pajak (PPh / PPN / PB1)
            $table->boolean('is_tax_enabled')->default(false);
            $table->string('tax_name', 50)->default('PB1 Pajak Daerah / PPh');
            $table->string('tax_type', 20)->default('PERCENTAGE'); // PERCENTAGE, FIXED
            $table->decimal('tax_rate', 5, 2)->default(10.00);
            $table->string('tax_channels', 20)->default('ALL'); // ALL, ONLINE_ONLY, POS_ONLY

            // Modular overrides untuk modul lain di masa depan (F&B, Pro Shop, Gym, Salon, etc.)
            $table->json('module_overrides')->nullable();

            $table->timestamps();
        });

        // Inisialisasi row default singleton ID = 1 (dalam kondisi nonaktif aman)
        DB::table('club_finance_settings')->insert([
            'id' => 1,
            'is_admin_fee_enabled' => false,
            'admin_fee_name' => 'Biaya Layanan / Admin',
            'admin_fee_type' => 'FIXED',
            'admin_fee_amount' => 2500.00,
            'admin_fee_channels' => 'ONLINE_ONLY',
            'is_tax_enabled' => false,
            'tax_name' => 'PB1 Pajak Daerah / PPh',
            'tax_type' => 'PERCENTAGE',
            'tax_rate' => 10.00,
            'tax_channels' => 'ALL',
            'module_overrides' => json_encode([
                'PADEL' => ['tax_active' => true, 'admin_fee_active' => true],
                'FNB' => ['tax_active' => true, 'admin_fee_active' => false],
                'MERCH' => ['tax_active' => true, 'admin_fee_active' => false],
                'WELLNESS' => ['tax_active' => true, 'admin_fee_active' => false],
                'SALON' => ['tax_active' => true, 'admin_fee_active' => false],
                'GYM' => ['tax_active' => true, 'admin_fee_active' => false],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_finance_settings');
    }
};
