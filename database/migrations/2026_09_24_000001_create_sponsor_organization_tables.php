<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Akun korporat/sponsor (PRD Modul 12) — pembungkus B2B di atas 1 kartu
        // user_memberships (owner_type = ORGANIZATIONAL) yang sudah dibuat Modul 05.
        Schema::create('sponsor_organizations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 150);
            $table->foreignUlid('user_membership_id')->unique()->constrained('user_memberships')->cascadeOnDelete();
            $table->foreignUlid('sponsor_admin_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 15)->default('ACTIVE'); // ACTIVE | SUSPENDED
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Roster karyawan/anggota tim corporate.
        Schema::create('sponsor_organization_members', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sponsor_organization_id')->constrained('sponsor_organizations')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 15)->default('ACTIVE'); // ACTIVE | REVOKED
            $table->timestamps();

            $table->unique(['sponsor_organization_id', 'user_id'], 'uniq_sponsor_org_member');
        });

        // 3. Ledger voucher jam gratis per anggota. Setiap "release" (dari CSV import atau
        // tombol manual PIC) bikin 1 baris baru — SENGAJA tidak digabung ke baris lama, jadi
        // satu anggota bisa punya beberapa voucher aktif sekaligus, masing-masing expired
        // sendiri-sendiri 1 bulan dari tanggal terbit (use-it-or-lose-it, tidak roll over
        // otomatis ke voucher lain).
        Schema::create('sponsor_member_vouchers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sponsor_organization_member_id')->constrained('sponsor_organization_members')->cascadeOnDelete();
            $table->decimal('hours_granted', 8, 2);
            $table->decimal('hours_used', 8, 2)->default(0);
            $table->dateTime('issued_at');
            $table->dateTime('expires_at');
            $table->string('source', 20)->default('MANUAL_RELEASE'); // CSV_IMPORT | MANUAL_RELEASE
            // Label periode (format "YYYY-MM", mis. "2026-09") — HANYA diisi untuk voucher hasil
            // CSV_IMPORT. Dipakai supaya re-upload CSV bulan yang SAMA (misal koreksi jam di
            // minggu ke-2, atau ke-klik dobel tidak sengaja) meng-UPDATE baris yang sudah ada
            // untuk periode itu, bukan bikin baris baru yang menumpuk. Upload di bulan BERBEDA
            // tetap bikin baris baru (periode beda), sesuai kebijakan "voucher bulanan".
            // Voucher dari release manual (MANUAL_RELEASE) tidak pakai konsep ini — tetap selalu
            // menumpuk sesuai keputusan bisnis semula.
            $table->string('period', 7)->nullable();
            $table->foreignUlid('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            // Kapan KARYAWAN (bukan PIC) mengakui/melihat voucher ini lewat popup klaim di
            // dashboard. TIDAK mempengaruhi apakah voucher bisa dipakai — voucher tetap otomatis
            // aktif begitu terbit (lihat catatan di releaseVoucher()); ini murni penanda supaya
            // popup klaim tidak terus muncul untuk voucher yang sudah pernah dilihat.
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['sponsor_organization_member_id', 'expires_at'], 'idx_smv_member_expires');
            $table->index(['sponsor_organization_member_id', 'source', 'period'], 'idx_smv_member_source_period');
        });

        // 4. Jadwal akses lapangan per sponsor — DIKONTROL VENUE (staf), bukan PIC. Dipakai
        // untuk gantian jatah jam rame antar sponsor (3 lapangan, banyak sponsor, tidak boleh
        // rebutan slot peak bersamaan). Tiap baris = 1 aturan berlaku pada rentang tanggal
        // tertentu, opsional dibatasi hari tertentu saja (days_of_week null = semua hari
        // dalam rentang itu). Booking corporate hanya boleh masuk jika waktunya tercakup
        // SALAH SATU baris aktif milik sponsor tsb (all-or-nothing, sama seperti pengecekan
        // time_window individual yang sudah ada).
        Schema::create('sponsor_access_schedules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sponsor_organization_id')->constrained('sponsor_organizations')->cascadeOnDelete();
            $table->date('valid_from');
            $table->date('valid_until');
            $table->json('days_of_week')->nullable(); // contoh [1,3,5] (1=Senin..7=Minggu). null = semua hari dalam rentang.
            $table->time('time_start');
            $table->time('time_end');
            // Batas jumlah lapangan yang boleh dipakai BERSAMAAN oleh sponsor ini dalam jam yang
            // sama — mencegah 1 sponsor (apalagi kalau sponsor makin banyak) menyapu semua
            // lapangan sekaligus lewat booking gratis, sehingga customer bayar/sponsor lain tetap
            // kebagian slot. null = tanpa batas (boleh sebanyak lapangan yang kosong).
            $table->unsignedTinyInteger('max_concurrent_courts')->nullable();
            $table->foreignUlid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['sponsor_organization_id', 'valid_from', 'valid_until'], 'idx_sas_org_valid_window');
        });

        // 5. Tag booking padel ke akun corporate asalnya (untuk histori & dashboard PIC).
        Schema::table('padel_bookings', function (Blueprint $table) {
            $table->foreignUlid('sponsor_organization_id')
                ->nullable()
                ->after('membership_balance_id')
                ->constrained('sponsor_organizations')
                ->nullOnDelete();

            $table->foreignUlid('sponsor_member_voucher_id')
                ->nullable()
                ->after('sponsor_organization_id')
                ->constrained('sponsor_member_vouchers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('padel_bookings', function (Blueprint $table) {
            $table->dropForeign(['sponsor_organization_id']);
            $table->dropForeign(['sponsor_member_voucher_id']);
            $table->dropColumn(['sponsor_organization_id', 'sponsor_member_voucher_id']);
        });

        Schema::dropIfExists('sponsor_access_schedules');
        Schema::dropIfExists('sponsor_member_vouchers');
        Schema::dropIfExists('sponsor_organization_members');
        Schema::dropIfExists('sponsor_organizations');
    }
};
