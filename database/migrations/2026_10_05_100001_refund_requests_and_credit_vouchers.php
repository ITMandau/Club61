<?php

use App\Services\Permission\Club61PermissionMatrix;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul 21 Tahap 1 — refund dua langkah & voucher saldo:
 *  - refunds: booking yang dibatalkan + siapa yang mengajukan (pengajuan dari Kelola Pemesanan masuk Antrian Refund
 *    sebagai PENDING; disetujui → booking REFUNDED, ditolak → booking CANCELLED + uangnya jadi voucher saldo).
 *  - vouchers: voucher saldo (discount_type CREDIT) milik satu customer, sisa saldo bisa dipakai berkali-kali.
 *  - Izin baru request_refund_padel diberikan ke role yang preset-nya memuatnya (admin, cashier, receptionist).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->foreignUlid('padel_booking_id')->nullable()->after('payment_id')->constrained('padel_bookings')->nullOnDelete();
            $table->foreignUlid('requested_by_id')->nullable()->after('padel_booking_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->foreignUlid('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->foreignUlid('refund_id')->nullable()->after('user_id')->constrained('refunds')->nullOnDelete();
            $table->decimal('balance', 12, 2)->nullable()->after('max_discount_amount');
            $table->index(['user_id', 'is_active']);
        });

        // Voucher saldo tidak punya kuota pemakaian (dipakai sampai saldonya habis).
        Schema::table('vouchers', function (Blueprint $table) {
            $table->integer('quota')->nullable()->default(100)->change();
        });

        // Penanda potongan voucher saldo order yang batal sudah dikembalikan ke saldonya (idempoten).
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('voucher_restored_at')->nullable()->after('voucher_code');
        });

        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            Club61PermissionMatrix::grantNewPermissionsToPresetRoles('web');
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('voucher_restored_at');
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_active']);
            $table->dropConstrainedForeignId('refund_id');
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('balance');
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_by_id');
            $table->dropConstrainedForeignId('padel_booking_id');
        });
    }
};
