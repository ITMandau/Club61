<?php

namespace App\Services\Fnb;

use App\Models\Fnb\FnbCashCategory;
use App\Models\Fnb\FnbCashEntry;
use App\Models\Fnb\FnbMenu;
use App\Models\Fnb\FnbStockMovement;
use App\Models\Fnb\FnbStockReceipt;
use App\Models\Pos\Order;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Finance\LedgerReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Modul 29 — satu-satunya penulis stok menu F&B & Kartu Stok.
 *  - Stok hanya berkurang saat pesanan LUNAS (dipanggil FnbFulfillmentHandler), sekali per pesanan, boleh minus.
 *  - Penerimaan Barang (satu nota supplier, banyak menu; manual / impor Excel) menambah stok (menutup minus) dan
 *    otomatis tercatat sebagai satu pengeluaran "Pembelian Persediaan" di Pengeluaran F&B.
 *  - Penyesuaian Stok & Stok Opname mencatat selisih dengan alasan; HPP saat itu disimpan untuk nilai kerugian stok.
 * Stok diubah lewat query builder (bukan event model) supaya log otomatis Menu F&B tidak banjir tiap penjualan;
 * jejaknya ada di Kartu Stok + Log Aktivitas per aksi.
 */
class FnbStockService
{
    public const MAX_RECEIPT_LINES = 500;

    /** Batas angka per baris (input manual, impor Excel & opname) — di bawah kapasitas kolom database. */
    public const MAX_QTY = 100000;

    public const MAX_UNIT_PRICE = 100000000;

    public const MAX_PURCHASE_TOTAL = 10000000000;

    /** Pesanan F&B lunas → potong stok menu yang dilacak (idempoten) & lengkapi HPP item yang belum tercatat. */
    public function deductForOrder(Order $order): void
    {
        $items = $order->items()->where('item_type', 'FNB')->whereNotNull('reference_id')->get();
        if ($items->isEmpty() || FnbStockMovement::where('order_id', $order->id)->where('type', FnbStockMovement::SALE)->exists()) {
            return;
        }

        // Semua menu dikunci sekaligus berurutan id (sama dengan Penerimaan / Opname) — dua pelunasan bersamaan
        // (kasir + webhook QRIS) berisi menu yang sama tidak saling menunggu (deadlock) lalu gagal bayar.
        $byMenu = $items->groupBy('reference_id');
        $menus = FnbMenu::query()->whereIn('id', $byMenu->keys())->orderBy('id')->lockForUpdate()->get();

        foreach ($menus as $menu) {
            // Pesanan yang dibuat sebelum HPP dicatat (mis. QR menunggu bayar saat fitur ini dipasang).
            $order->items()->where('reference_id', $menu->id)->whereNull('unit_cost')->update(['unit_cost' => $menu->hpp]);

            if (! $menu->track_stock) {
                continue;
            }

            $qty = (int) $byMenu->get($menu->id)->sum('quantity');
            $this->move($menu, -$qty, FnbStockMovement::SALE, ['order_id' => $order->id], null);
        }
    }

    /**
     * Penerimaan Barang: semua baris masuk atau tidak sama sekali.
     *
     * @param  array<int, array{menu_id: string, qty: int|string, total: int|float|string|null}>  $lines
     */
    public function receive(array $lines, ?string $supplier, ?string $invoiceNumber, ?string $notes, User $by, string $source = 'MANUAL'): FnbStockReceipt
    {
        abort_if($lines === [], 422, 'Isi minimal satu baris barang yang diterima.');
        abort_if(count($lines) > self::MAX_RECEIPT_LINES, 422, 'Maksimal '.self::MAX_RECEIPT_LINES.' baris per penerimaan.');

        $menuIds = [];
        foreach ($lines as $i => $line) {
            $row = $i + 1;
            abort_if(empty($line['menu_id']), 422, "Baris {$row}: menu wajib dipilih.");
            abort_if(in_array($line['menu_id'], $menuIds, true), 422, "Baris {$row}: menu yang sama tercantum lebih dari satu kali. Gabungkan jumlahnya dalam satu baris.");
            abort_unless(is_numeric($line['qty'] ?? null) && (int) $line['qty'] == $line['qty'] && (int) $line['qty'] >= 1, 422, "Baris {$row}: jumlah diterima harus bilangan bulat minimal 1.");
            abort_if((int) $line['qty'] > self::MAX_QTY, 422, "Baris {$row}: jumlah diterima maksimal ".number_format(self::MAX_QTY, 0, ',', '.').'.');
            $total = $line['total'] ?? null;
            abort_unless($total === null || $total === '' || (is_numeric($total) && (float) $total >= 0), 422, "Baris {$row}: total harga pembelian harus angka 0 atau lebih.");
            $menuIds[] = $line['menu_id'];
        }

        $total = round(collect($lines)->sum(fn ($l) => (float) ($l['total'] ?: 0)), 2);
        abort_if($total > self::MAX_PURCHASE_TOTAL, 422, 'Total satu Penerimaan Barang terlalu besar. Pisahkan menjadi beberapa penerimaan.');

        return DB::transaction(function () use ($lines, $menuIds, $total, $supplier, $invoiceNumber, $notes, $by, $source) {
            // Kunci berurutan (id) supaya dua penerimaan bersamaan tidak saling menunggu (deadlock).
            $menus = FnbMenu::query()->whereIn('id', $menuIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($lines as $i => $line) {
                $menu = $menus->get($line['menu_id']);
                abort_unless($menu, 422, 'Baris '.($i + 1).': menu tidak ditemukan.');
                // Barang yang diterima pasti dihitung stoknya — menu yang belum dilacak diaktifkan (stok mulai dari 0).
                if (! $menu->track_stock) {
                    $this->updateSettings($menu, (float) $menu->hpp, true, $menu->min_stock, $by);
                    $menus->put($menu->id, $menu->fresh());
                }
            }

            $receipt = FnbStockReceipt::create([
                'receipt_number' => 'PB-'.now(LedgerReport::TIMEZONE)->format('ymd').'-'.strtoupper(Str::random(5)),
                'supplier' => $this->clean($supplier, 100),
                'invoice_number' => $this->clean($invoiceNumber, 60),
                'notes' => $this->clean($notes, 500),
                'item_count' => count($lines),
                'total_amount' => $total,
                'source' => $source === 'IMPORT' ? 'IMPORT' : 'MANUAL',
                'created_by' => $by->id,
            ]);

            foreach ($lines as $line) {
                $this->move($menus->get($line['menu_id']), (int) $line['qty'], FnbStockMovement::RESTOCK, [
                    'receipt_id' => $receipt->id,
                    'purchase_total' => round((float) ($line['total'] ?: 0), 2),
                ], $by);
            }

            if ($total > 0) {
                FnbCashEntry::create([
                    'entry_date' => now(LedgerReport::TIMEZONE)->toDateString(),
                    'category_id' => $this->restockCategory()->id,
                    'amount' => $total,
                    'notes' => "Penerimaan Barang {$receipt->receipt_number} ({$receipt->item_count} item)"
                        .($receipt->supplier ? " · {$receipt->supplier}" : '').($receipt->invoice_number ? " · Nota {$receipt->invoice_number}" : ''),
                    'receipt_id' => $receipt->id,
                    'created_by' => $by->id,
                ]);
            }

            ActivityLogger::record('FNB', 'fnb_stock.received',
                "Mencatat Penerimaan Barang {$receipt->receipt_number}: {$receipt->item_count} item, ".ActivityLogger::rupiah($total),
                subject: $receipt,
                meta: [
                    'sumber' => FnbStockReceipt::SOURCES[$receipt->source],
                    'supplier' => $receipt->supplier,
                    'no_nota' => $receipt->invoice_number,
                    'barang' => collect($lines)->mapWithKeys(fn ($l) => [$menus->get($l['menu_id'])->name => '+'.(int) $l['qty']])->all(),
                ],
                subjectLabel: $receipt->receipt_number,
            );

            return $receipt;
        });
    }

    /** Penerimaan salah input → stok seluruh barangnya dikurangi kembali, pengeluarannya ikut batal. Riwayat tetap ada. */
    public function voidReceipt(FnbStockReceipt $receipt, string $reason, User $by): void
    {
        $reason = $this->clean($reason, 255);
        abort_if($reason === null || mb_strlen($reason) < 5, 422, 'Alasan pembatalan wajib diisi (minimal 5 karakter).');

        DB::transaction(function () use ($receipt, $reason, $by) {
            $receipt = FnbStockReceipt::query()->lockForUpdate()->findOrFail($receipt->id);
            abort_if($receipt->voided_at !== null, 422, 'Penerimaan Barang ini sudah dibatalkan.');

            $lines = $receipt->lines()->get();
            $menus = FnbMenu::query()->whereIn('id', $lines->pluck('menu_id')->filter())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($lines as $line) {
                // Menu yang sudah dihapus / tidak lagi dilacak tidak punya stok — barisnya cukup ditandai batal.
                $menu = $menus->get($line->menu_id);
                if ($menu?->track_stock) {
                    $this->move($menu, -$line->qty_change, FnbStockMovement::RESTOCK_VOID, ['receipt_id' => $receipt->id, 'notes' => $reason], $by);
                }
                $line->update(['voided_at' => now(), 'voided_by' => $by->id, 'void_reason' => $reason]);
            }

            $receipt->update(['voided_at' => now(), 'voided_by' => $by->id, 'void_reason' => $reason]);
            FnbCashEntry::where('receipt_id', $receipt->id)->whereNull('voided_at')->get()
                ->each(fn (FnbCashEntry $e) => $e->update(['voided_at' => now(), 'voided_by' => $by->id, 'void_reason' => 'Penerimaan Barang dibatalkan: '.$reason]));

            ActivityLogger::record('FNB', 'fnb_stock.receipt_voided',
                "Membatalkan Penerimaan Barang {$receipt->receipt_number} ({$receipt->item_count} item, ".ActivityLogger::rupiah((float) $receipt->total_amount).')',
                subject: $receipt,
                meta: ['alasan' => $reason, 'dicatat' => $receipt->created_at?->timezone(LedgerReport::TIMEZONE)->format('d/m/Y H:i')],
                severity: ActivityLogger::WARNING,
                subjectLabel: $receipt->receipt_number,
            );
        });
    }

    public function adjust(FnbMenu $menu, int $qtyChange, string $reason, string $notes, User $by): FnbStockMovement
    {
        abort_if($qtyChange === 0, 422, 'Jumlah penyesuaian tidak boleh 0.');
        abort_if(abs($qtyChange) > self::MAX_QTY, 422, 'Jumlah penyesuaian maksimal '.number_format(self::MAX_QTY, 0, ',', '.').'.');
        abort_unless(array_key_exists($reason, FnbStockMovement::REASONS), 422, 'Alasan penyesuaian tidak dikenal.');
        $notes = $this->clean($notes, 500);
        abort_if($notes === null || mb_strlen($notes) < 5, 422, 'Keterangan penyesuaian wajib diisi (minimal 5 karakter).');

        return DB::transaction(function () use ($menu, $qtyChange, $reason, $notes, $by) {
            $menu = FnbMenu::query()->lockForUpdate()->findOrFail($menu->id);
            abort_unless($menu->track_stock, 422, "Persediaan {$menu->name} tidak dilacak. Aktifkan Lacak Persediaan pada data menu terlebih dahulu.");
            $movement = $this->move($menu, $qtyChange, FnbStockMovement::ADJUSTMENT, ['reason' => $reason, 'notes' => $notes], $by);

            ActivityLogger::record('FNB', 'fnb_stock.adjusted',
                "Penyesuaian stok {$menu->name}: ".sprintf('%+d', $qtyChange).' ('.FnbStockMovement::REASONS[$reason].", stok menjadi {$movement->stock_after})",
                subject: $menu,
                meta: ['alasan' => FnbStockMovement::REASONS[$reason], 'keterangan' => $notes, 'stok_setelah' => $movement->stock_after],
                severity: ActivityLogger::WARNING,
            );

            return $movement;
        });
    }

    /**
     * Penyesuaian Stok banyak menu sekaligus (satu keterangan umum): semua baris tersimpan atau tidak sama sekali.
     *
     * @param  array<int, array{menu_id: string, change: int, reason: string}>  $lines  change bertanda (+ tambah / − kurang)
     * @return int jumlah baris yang tersimpan
     */
    public function adjustMany(array $lines, string $notes, User $by): int
    {
        abort_if($lines === [], 422, 'Isi minimal satu baris penyesuaian.');
        abort_if(count(array_unique(array_column($lines, 'menu_id'))) !== count($lines), 422, 'Menu yang sama tercantum lebih dari satu kali. Gabungkan dalam satu baris.');

        // Diurutkan per menu supaya penguncian baris selalu searah (tidak saling menunggu dengan transaksi lain).
        usort($lines, fn ($a, $b) => strcmp($a['menu_id'], $b['menu_id']));

        return DB::transaction(function () use ($lines, $notes, $by) {
            foreach ($lines as $line) {
                $this->adjust(FnbMenu::findOrFail($line['menu_id']), (int) $line['change'], $line['reason'], $notes, $by);
            }

            return count($lines);
        });
    }

    /**
     * @param  array<string, int|string|null>  $counts  menu_id => hasil perhitungan fisik (kosong = tidak dihitung)
     * @param  array<string, string|null>  $itemNotes  menu_id => catatan per menu (mis. "2 botol di gudang belakang")
     * @return array{counted: int, changed: int}
     */
    public function opname(array $counts, ?string $notes, User $by, array $itemNotes = []): array
    {
        $counts = array_filter($counts, fn ($v) => $v !== null && $v !== '');
        abort_if($counts === [], 422, 'Isi hasil perhitungan fisik minimal satu menu.');
        foreach ($counts as $value) {
            abort_unless(is_numeric($value) && (int) $value == $value && (int) $value >= 0, 422, 'Hasil perhitungan fisik harus bilangan bulat 0 atau lebih.');
            abort_if((int) $value > self::MAX_QTY, 422, 'Hasil perhitungan fisik maksimal '.number_format(self::MAX_QTY, 0, ',', '.').'.');
        }

        return DB::transaction(function () use ($counts, $notes, $by, $itemNotes) {
            $batch = (string) Str::ulid();
            $notes = $this->clean($notes, 500);
            $changed = [];

            $menus = FnbMenu::query()->whereIn('id', array_keys($counts))->where('track_stock', true)->orderBy('id')->lockForUpdate()->get();
            abort_if($menus->isEmpty(), 422, 'Menu yang dihitung tidak ditemukan atau persediaannya tidak dilacak.');

            foreach ($menus as $menu) {
                $diff = (int) $counts[$menu->id] - $menu->stock_qty;
                if ($diff !== 0) {
                    // Catatan per menu didahulukan, lalu catatan umum opname.
                    $itemNote = $this->clean($itemNotes[$menu->id] ?? null, 200);
                    $this->move($menu, $diff, FnbStockMovement::OPNAME, [
                        'opname_batch' => $batch,
                        'notes' => $this->clean(implode(' · ', array_filter([$itemNote, $notes])), 500),
                    ], $by);
                    $changed[$menu->name] = sprintf('%+d', $diff).($itemNote ? " ({$itemNote})" : '');
                }
            }

            ActivityLogger::record('FNB', 'fnb_stock.opname',
                "Menyimpan Stok Opname F&B: {$menus->count()} menu dihitung, ".count($changed).' berselisih',
                meta: ['selisih' => $changed ?: 'tidak ada', 'catatan' => $notes],
                severity: $changed ? ActivityLogger::WARNING : ActivityLogger::INFO,
            );

            return ['counted' => $menus->count(), 'changed' => count($changed)];
        });
    }

    /** HPP, lacak persediaan & stok minimum (log otomatis "Mengubah Menu F&B"). Mulai melacak = stok dimulai dari 0. */
    public function updateSettings(FnbMenu $menu, float $hpp, bool $trackStock, ?int $minStock, ?User $by = null): void
    {
        abort_if($hpp < 0, 422, 'HPP tidak boleh negatif.');
        abort_if($hpp > self::MAX_UNIT_PRICE, 422, 'HPP terlalu besar.');
        abort_if($minStock !== null && $minStock < 0, 422, 'Stok minimum tidak boleh negatif.');

        DB::transaction(function () use ($menu, $hpp, $trackStock, $minStock, $by) {
            $menu = FnbMenu::query()->lockForUpdate()->findOrFail($menu->id);
            // Angka stok lama tidak terpakai selama tidak dilacak — dinolkan lewat Kartu Stok supaya jejaknya tetap ada.
            // Nilainya 0 (bukan kerugian stok): barangnya tidak hilang, hanya belum dihitung ulang.
            if ($trackStock && ! $menu->track_stock && (int) $menu->stock_qty !== 0) {
                $this->move($menu, -(int) $menu->stock_qty, FnbStockMovement::ADJUSTMENT, [
                    'unit_cost' => 0,
                    'notes' => 'Lacak Persediaan diaktifkan kembali — stok lama ('.(int) $menu->stock_qty.') dinolkan. Lakukan Stok Opname bila barangnya masih ada.',
                ], $by);
            }
            $menu->update(['hpp' => $hpp, 'track_stock' => $trackStock, 'min_stock' => $trackStock ? $minStock : null]);
        });
    }

    public function restockCategory(): FnbCashCategory
    {
        return FnbCashCategory::firstOrCreate(
            ['system_key' => FnbCashCategory::RESTOCK_KEY],
            ['name' => 'Pembelian Persediaan', 'is_stock_purchase' => true, 'is_active' => true, 'sort_order' => 0],
        );
    }

    /** Ubah stok (atomik, boleh minus) + tulis satu baris Kartu Stok. Menu sudah dikunci pemanggil. */
    private function move(FnbMenu $menu, int $qtyChange, string $type, array $attributes, ?User $by): FnbStockMovement
    {
        FnbMenu::whereKey($menu->id)->increment('stock_qty', $qtyChange);
        $stockAfter = (int) FnbMenu::whereKey($menu->id)->value('stock_qty');

        return FnbStockMovement::create(array_merge([
            'menu_id' => $menu->id,
            'menu_name' => $menu->name,
            'type' => $type,
            'qty_change' => $qtyChange,
            'stock_after' => $stockAfter,
            'unit_cost' => $menu->hpp,
            'created_by' => $by?->id,
            'created_at' => now(),
        ], $attributes));
    }

    private function clean(?string $value, int $max): ?string
    {
        $value = trim(mb_substr(preg_replace('/\s+/u', ' ', (string) $value), 0, $max));

        return $value === '' ? null : $value;
    }
}
