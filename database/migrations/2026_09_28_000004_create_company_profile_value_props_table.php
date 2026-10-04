<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_profile_value_props', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('icon_key', 30);
            $table->string('title', 60);
            $table->string('description', 150)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('company_profile_value_props')->insert([
            [
                'id' => (string) Str::ulid(),
                'icon_key' => 'booking',
                'title' => 'Booking Online Real-Time',
                'description' => 'Cek slot & bayar langsung dari HP, tanpa telepon dulu.',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => (string) Str::ulid(),
                'icon_key' => 'membership',
                'title' => 'Membership Fleksibel',
                'description' => 'Paket jam bermain individual maupun korporat.',
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => (string) Str::ulid(),
                'icon_key' => 'sponsor',
                'title' => 'Program Sponsor Corporate',
                'description' => 'Solusi benefit karyawan untuk perusahaan mitra.',
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => (string) Str::ulid(),
                'icon_key' => 'voucher',
                'title' => 'Promo & Voucher Rutin',
                'description' => 'Penawaran spesial untuk member aktif.',
                'sort_order' => 4,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => (string) Str::ulid(),
                'icon_key' => 'support',
                'title' => 'Layanan Frontdesk Responsif',
                'description' => 'Staf siap bantu reschedule & kebutuhan lain di lokasi.',
                'sort_order' => 5,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => (string) Str::ulid(),
                'icon_key' => 'shield',
                'title' => 'Transaksi Aman & Tercatat',
                'description' => 'Semua pembayaran tercatat rapi lewat sistem terintegrasi.',
                'sort_order' => 6,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('company_profile_value_props');
    }
};
