<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pivot yang selama ini belum pernah dibuat — fnb_modifier_groups sudah ada sejak
        // migration awal tapi tidak pernah terhubung ke fnb_menus manapun (lihat PRD Modul 15
        // §1 & §3.2). cascadeOnDelete di kedua sisi: hapus menu atau hapus grup modifier
        // cuma membuang baris keterkaitannya, bukan ikut menghapus induk yang lain.
        Schema::create('fnb_menu_modifier_group', function (Blueprint $table) {
            $table->foreignUlid('menu_id')->constrained('fnb_menus')->cascadeOnDelete();
            $table->foreignUlid('group_id')->constrained('fnb_modifier_groups')->cascadeOnDelete();
            $table->primary(['menu_id', 'group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fnb_menu_modifier_group');
    }
};
