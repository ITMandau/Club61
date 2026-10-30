<?php

use App\Services\Permission\Club61PermissionMatrix;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Modul 29 — Mini Inventory & Keuangan F&B (PRD_MODUL_29):
 *  - Menu F&B: HPP manual, pilihan lacak stok, stok (boleh minus), stok minimum.
 *  - order_items.unit_cost: HPP per item saat dijual (snapshot) — laba periode lalu tidak berubah saat HPP diganti.
 *  - fnb_stock_receipts: Penerimaan Barang (satu nota supplier, banyak menu; manual atau impor Excel).
 *  - fnb_stock_movements: kartu stok (penjualan, penerimaan, penyesuaian, opname) — tidak diedit / dihapus.
 *  - Pengeluaran F&B: kategori + catatan biaya (pemasukan F&B = penjualan kasir di Buku Transaksi, tidak diinput manual).
 * Tabel lama raw_materials / recipe_boms tidak disentuh (bahan baku tidak dipakai).
 *
 * Setiap langkah dicek dulu (kolom / tabel / kategori sudah ada → dilewati): DDL MySQL langsung tersimpan, jadi kalau
 * migrasi berhenti di tengah, `php artisan migrate` berikutnya cukup melanjutkan sisanya.
 * JANGAN di-rollback di production — down() menghapus stok, HPP penjualan, dan seluruh catatan pengeluaran F&B.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fnb_menus', function (Blueprint $t) {
            if (! Schema::hasColumn('fnb_menus', 'hpp')) {
                $t->decimal('hpp', 12, 2)->default(0)->after('base_price');
            }
            if (! Schema::hasColumn('fnb_menus', 'track_stock')) {
                $t->boolean('track_stock')->default(false)->after('hpp');
            }
            if (! Schema::hasColumn('fnb_menus', 'stock_qty')) {
                $t->integer('stock_qty')->default(0)->after('track_stock');
            }
            if (! Schema::hasColumn('fnb_menus', 'min_stock')) {
                $t->integer('min_stock')->nullable()->after('stock_qty');
            }
        });

        if (! Schema::hasColumn('order_items', 'unit_cost')) {
            Schema::table('order_items', function (Blueprint $t) {
                $t->decimal('unit_cost', 12, 2)->nullable()->after('unit_price');
            });
        }

        // Penerimaan Barang: satu nota supplier = satu penerimaan berisi banyak menu (input manual atau impor Excel).
        $this->createTable('fnb_stock_receipts', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->string('receipt_number', 30)->unique();
            $t->string('supplier', 100)->nullable();
            $t->string('invoice_number', 60)->nullable(); // No. nota / faktur dari supplier
            $t->text('notes')->nullable();
            $t->integer('item_count');
            $t->decimal('total_amount', 14, 2)->default(0);
            $t->string('source', 10)->default('MANUAL'); // MANUAL, IMPORT
            $t->dateTime('voided_at')->nullable();
            $t->foreignUlid('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('void_reason', 255)->nullable();
            $t->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index('created_at', 'idx_fnb_receipt_time');
        });

        $this->createTable('fnb_stock_movements', function (Blueprint $t) {
            $t->ulid('id')->primary();
            // Menu boleh dihapus — riwayat tetap terbaca lewat menu_name.
            $t->foreignUlid('menu_id')->nullable()->constrained('fnb_menus')->nullOnDelete();
            $t->string('menu_name', 100);
            $t->string('type', 20); // SALE, RESTOCK, RESTOCK_VOID, ADJUSTMENT, OPNAME
            $t->integer('qty_change');
            $t->integer('stock_after');
            $t->decimal('unit_cost', 12, 2)->default(0); // HPP saat itu (nilai kerugian penyesuaian / opname)
            $t->decimal('purchase_total', 14, 2)->nullable(); // Penerimaan Barang: total harga pembelian baris ini
            $t->foreignUlid('receipt_id')->nullable()->constrained('fnb_stock_receipts')->nullOnDelete();
            $t->string('reason', 30)->nullable();
            $t->text('notes')->nullable();
            $t->ulid('order_id')->nullable();
            $t->ulid('opname_batch')->nullable();
            $t->dateTime('voided_at')->nullable();
            $t->foreignUlid('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('void_reason', 255)->nullable();
            $t->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->nullable();

            // Satu pesanan hanya memotong stok satu menu sekali (notifikasi bayar bisa terkirim berulang).
            $t->unique(['order_id', 'menu_id', 'type'], 'uniq_fnb_stock_order_menu_type');
            $t->index(['menu_id', 'created_at'], 'idx_fnb_stock_menu_time');
            $t->index(['type', 'created_at'], 'idx_fnb_stock_type_time');
        });

        $this->createTable('fnb_cash_categories', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->string('name', 60);
            // Pembelian persediaan / bahan: masuk Arus Kas, tapi tidak dipotong lagi di Laba (sudah diwakili HPP).
            $t->boolean('is_stock_purchase')->default(false);
            $t->string('system_key', 30)->nullable()->unique(); // RESTOCK = kategori otomatis Penerimaan Barang
            $t->boolean('is_active')->default(true);
            $t->integer('sort_order')->default(0);
            $t->timestamps();
        });

        $this->createTable('fnb_cash_entries', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->date('entry_date');
            $t->foreignUlid('category_id')->constrained('fnb_cash_categories')->restrictOnDelete();
            $t->decimal('amount', 14, 2);
            $t->text('notes')->nullable();
            $t->string('attachment_path')->nullable();
            $t->foreignUlid('receipt_id')->nullable()->unique()->constrained('fnb_stock_receipts')->nullOnDelete();
            $t->dateTime('voided_at')->nullable();
            $t->foreignUlid('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('void_reason', 255)->nullable();
            $t->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index('entry_date', 'idx_fnb_cash_date');
        });

        $now = now();
        $categories = [
            ['Pembelian Persediaan', true, 'RESTOCK'],
            ['Belanja Bahan Dapur', true, null],
            ['Gas', false, null],
            ['Listrik / Air', false, null],
            ['Perlengkapan', false, null],
            ['Gaji', false, null],
            ['Perawatan', false, null],
            ['Lain-lain', false, null],
        ];
        if (! DB::table('fnb_cash_categories')->exists()) {
            DB::table('fnb_cash_categories')->insert(array_map(fn ($c, $i) => [
                'id' => (string) Str::ulid(), 'name' => $c[0], 'is_stock_purchase' => $c[1], 'system_key' => $c[2],
                'is_active' => true, 'sort_order' => $i + 1, 'created_at' => $now, 'updated_at' => $now,
            ], $categories, array_keys($categories)));
        }

        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            Club61PermissionMatrix::grantNewPermissionsToPresetRoles('web');
        }
    }

    /** Schema::create yang dilewati bila tabelnya sudah ada (migrasi yang sempat berhenti di tengah). */
    private function createTable(string $table, \Closure $schema): void
    {
        if (! Schema::hasTable($table)) {
            Schema::create($table, $schema);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fnb_cash_entries');
        Schema::dropIfExists('fnb_cash_categories');
        Schema::dropIfExists('fnb_stock_movements');
        Schema::dropIfExists('fnb_stock_receipts');

        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn('unit_cost'));
        Schema::table('fnb_menus', fn (Blueprint $t) => $t->dropColumn(['hpp', 'track_stock', 'stock_qty', 'min_stock']));
    }
};
