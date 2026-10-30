<?php

namespace App\Services\Fnb;

use App\Models\Fnb\FnbMenu;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Modul 29 — Impor Excel Penerimaan Barang & stok awal. Templat berisi SEMUA menu terdaftar (ID Sistem untuk
 * pencocokan). Per baris staf mengisi (opsional) Stok Fisik Saat Ini, Jumlah Diterima + Harga Beli per Satuan, serta
 * Supplier & No. Nota. Sistem menghitung sendiri:
 *  - Stok Fisik diisi → selisih dengan stok sistem dicatat sebagai Stok Opname (sebelum barang baru ditambahkan);
 *  - Total = jumlah × harga; baris dikelompokkan per Supplier + No. Nota → satu Penerimaan Barang per nota;
 *  - menu yang persediaannya belum dilacak otomatis diaktifkan Lacak Persediaan-nya.
 * Hasil baca hanya pratinjau — saat disimpan file dibaca ulang dan semua baris masuk atau tidak sama sekali.
 */
class FnbReceiptImport
{
    public const SHEET = 'Penerimaan Barang';

    public const HEADERS = [
        'ID Sistem (jangan diubah)', 'Nama Menu', 'Kategori', 'Lacak Persediaan', 'Stok di Sistem',
        'Stok Fisik Saat Ini', 'Jumlah Diterima', 'Harga Beli per Satuan (Rp)', 'Total (otomatis)', 'Supplier', 'No. Nota',
    ];

    private const WIDTHS = [30, 30, 22, 18, 16, 20, 18, 26, 18, 24, 16];

    private const COLUMNS = [
        'idsistemjangandiubah' => 'id', 'idsistem' => 'id', 'id' => 'id',
        'namamenu' => 'name', 'menu' => 'name',
        'stokfisiksaatini' => 'physical', 'stokfisik' => 'physical',
        'jumlahditerima' => 'qty', 'jumlah' => 'qty',
        'hargabelipersatuanrp' => 'price', 'hargabelipersatuan' => 'price', 'hargasatuan' => 'price',
        'supplier' => 'supplier',
        'nonota' => 'invoice', 'nonotafaktur' => 'invoice', 'nota' => 'invoice',
    ];

    public function buildTemplate(string $path): string
    {
        $writer = new Writer();
        $writer->openToFile($path);
        $sheet = $writer->getCurrentSheet();
        $sheet->setName(self::SHEET);
        $sheet->setSheetView((new SheetView())->setFreezeRow(2));
        foreach (self::WIDTHS as $i => $width) {
            $sheet->setColumnWidth($width, $i + 1);
        }

        $header = (new Style())->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('662721')->setCellVerticalAlignment(CellVerticalAlignment::BOTTOM);
        $muted = (new Style())->setFontColor('9A8A84');
        $input = (new Style())->setBackgroundColor('FFF8E6');
        $writer->addRow(Row::fromValues(self::HEADERS, $header)->setHeight(30));

        $row = 1;
        FnbMenu::query()->with('category:id,name')->orderBy('name')->each(function (FnbMenu $m) use ($writer, $muted, $input, &$row) {
            $row++;
            $writer->addRow(new Row([
                Cell::fromValue($m->id, $muted),
                Cell::fromValue($this->safe($m->name)),
                Cell::fromValue($this->safe((string) $m->category?->name), $muted),
                Cell::fromValue($m->track_stock ? 'Ya' : 'Tidak', $muted),
                Cell::fromValue($m->track_stock ? $m->stock_qty : 0, $muted),
                Cell::fromValue(null, $input),
                Cell::fromValue(null, $input),
                Cell::fromValue(null, $input),
                Cell::fromValue("=IF(AND(ISNUMBER(G{$row}),ISNUMBER(H{$row})),G{$row}*H{$row},\"\")", $muted),
                Cell::fromValue(null, $input),
                Cell::fromValue(null, $input),
            ]));
        });

        $guide = $writer->addNewSheetAndMakeItCurrent();
        $guide->setName('Petunjuk');
        $guide->setColumnWidth(130, 1);
        foreach ([
            ['Petunjuk Impor Penerimaan Barang & Stok F&B'],
            [''],
            ['1. Templat berisi seluruh menu yang terdaftar. Isi hanya baris menu yang ingin diperbarui; baris yang kolom isiannya kosong tidak diproses.'],
            ['2. Stok Fisik Saat Ini (opsional): jumlah fisik sebelum barang baru diterima. Selisih dengan Stok di Sistem dicatat otomatis sebagai Stok Opname.'],
            ['3. Jumlah Diterima & Harga Beli per Satuan: barang yang baru diterima dari supplier. Total dihitung otomatis (jumlah × harga). Isi harga 0 untuk barang titipan yang belum dibayar.'],
            ['4. Supplier & No. Nota: baris dengan Supplier dan No. Nota yang sama digabung menjadi satu Penerimaan Barang (satu catatan di Pengeluaran F&B).'],
            ['5. Menu yang Lacak Persediaan-nya "Tidak" akan diaktifkan otomatis apabila barisnya diisi.'],
            ['6. Jangan mengubah kolom ID Sistem. Kolom Kategori, Lacak Persediaan, Stok di Sistem, dan Total hanya sebagai informasi.'],
        ] as $line) {
            $writer->addRow(Row::fromValues($line));
        }

        $writer->close();

        return $path;
    }

    /**
     * @return array{rows: list<array<string, mixed>>, errors: int, fatal: ?string, receipts: int, purchase_total: float, counted: int, enable: int}
     */
    public function parse(string $path): array
    {
        $result = ['rows' => [], 'errors' => 0, 'fatal' => null, 'receipts' => 0, 'purchase_total' => 0.0, 'counted' => 0, 'enable' => 0];
        $reader = new Reader();
        try {
            $reader->open($path);
        } catch (\Throwable) {
            return ['fatal' => 'File tidak dapat dibaca. Gunakan file Excel (.xlsx) dari templat.'] + $result;
        }

        $menus = FnbMenu::query()->get(['id', 'name', 'track_stock', 'stock_qty', 'hpp', 'min_stock']);
        $byId = $menus->keyBy('id');
        $byName = $menus->keyBy(fn (FnbMenu $m) => mb_strtolower(trim($m->name)));
        $seen = [];
        $groups = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($this->key($sheet->getName()) !== $this->key(self::SHEET)) {
                    continue;
                }

                $map = null;
                foreach ($sheet->getRowIterator() as $index => $row) {
                    $cells = $row->toArray();
                    if ($map === null) {
                        $map = [];
                        foreach ($cells as $col => $title) {
                            $key = self::COLUMNS[$this->key((string) $title)] ?? null;
                            if ($key !== null && ! in_array($key, $map, true)) {
                                $map[$col] = $key;
                            }
                        }
                        if (! in_array('qty', $map, true) || ! in_array('price', $map, true) || ! array_intersect(['id', 'name'], $map)) {
                            return ['fatal' => 'Kolom "ID Sistem" / "Nama Menu", "Jumlah Diterima", dan "Harga Beli per Satuan" tidak ditemukan pada baris judul. Gunakan templat terbaru.'] + $result;
                        }

                        continue;
                    }

                    $data = [];
                    foreach ($map as $col => $key) {
                        $data[$key] = $cells[$col] ?? null;
                    }
                    $physical = $data['physical'] ?? null;
                    $qty = $data['qty'] ?? null;
                    $price = $data['price'] ?? null;
                    if ($this->blank($physical) && $this->blank($qty) && $this->blank($price)) {
                        continue;
                    }
                    if (count($result['rows']) >= FnbStockService::MAX_RECEIPT_LINES) {
                        return ['fatal' => 'Maksimal '.FnbStockService::MAX_RECEIPT_LINES.' baris terisi per impor. Pisahkan menjadi beberapa file.'] + $result;
                    }

                    $id = trim((string) ($data['id'] ?? ''));
                    $name = trim((string) ($data['name'] ?? ''));
                    $menu = ($id !== '' ? $byId->get($id) : null) ?? ($name !== '' ? $byName->get(mb_strtolower($name)) : null);
                    $supplier = $this->clean($data['supplier'] ?? null, 100);
                    $invoice = $this->clean($data['invoice'] ?? null, 60);
                    $hasQty = ! $this->blank($qty);

                    $error = match (true) {
                        ! $menu => 'Menu "'.($name ?: $id).'" tidak ditemukan.',
                        isset($seen[$menu->id]) => "{$menu->name} tercantum lebih dari satu kali (lihat baris {$seen[$menu->id]}).",
                        ! $this->blank($physical) && ! $this->wholeNumber($physical, 0) => 'Stok Fisik Saat Ini harus bilangan bulat 0 atau lebih.',
                        ! $this->blank($physical) && (int) $physical > FnbStockService::MAX_QTY => 'Stok Fisik Saat Ini maksimal '.number_format(FnbStockService::MAX_QTY, 0, ',', '.').'.',
                        $hasQty && ! $this->wholeNumber($qty, 1) => 'Jumlah Diterima harus bilangan bulat minimal 1.',
                        $hasQty && (int) $qty > FnbStockService::MAX_QTY => 'Jumlah Diterima maksimal '.number_format(FnbStockService::MAX_QTY, 0, ',', '.').'.',
                        $hasQty && $this->blank($price) => 'Harga Beli per Satuan wajib diisi (isi 0 untuk barang titipan).',
                        ! $hasQty && ! $this->blank($price) => 'Harga diisi tanpa Jumlah Diterima.',
                        $hasQty && (! is_numeric($price) || (float) $price < 0) => 'Harga Beli per Satuan harus angka 0 atau lebih.',
                        $hasQty && (float) $price > FnbStockService::MAX_UNIT_PRICE => 'Harga Beli per Satuan maksimal Rp '.number_format(FnbStockService::MAX_UNIT_PRICE, 0, ',', '.').'.',
                        default => null,
                    };

                    if ($menu && ! isset($seen[$menu->id])) {
                        $seen[$menu->id] = $index;
                    }

                    $stockBase = $menu ? (! $this->blank($physical) && is_numeric($physical) ? (int) $physical : ($menu->track_stock ? $menu->stock_qty : 0)) : 0;
                    $total = $hasQty && is_numeric($qty) && is_numeric($price) ? round((int) $qty * (float) $price, 2) : 0.0;
                    $result['rows'][] = [
                        'row' => $index,
                        'menu_id' => $menu?->id,
                        'name' => $menu?->name ?? ($name ?: $id),
                        'enable' => $menu && ! $menu->track_stock,
                        'system' => $menu && $menu->track_stock ? $menu->stock_qty : 0,
                        'physical' => ! $this->blank($physical) && is_numeric($physical) ? (int) $physical : null,
                        'qty' => $hasQty && is_numeric($qty) ? (int) $qty : null,
                        'price' => is_numeric($price) ? (float) $price : null,
                        'total' => $total,
                        'final' => $stockBase + ($hasQty && is_numeric($qty) ? (int) $qty : 0),
                        'supplier' => $supplier,
                        'invoice' => $invoice,
                        'error' => $error,
                    ];

                    if ($error) {
                        $result['errors']++;

                        continue;
                    }
                    $result['enable'] += $menu->track_stock ? 0 : 1;
                    $result['counted'] += $this->blank($physical) ? 0 : 1;
                    if ($hasQty) {
                        $groups[mb_strtolower(($supplier ?? '').'|'.($invoice ?? ''))] = true;
                        $result['purchase_total'] += $total;
                    }
                }

                break;
            }
        } catch (\Throwable) {
            return ['fatal' => 'Isi file Excel rusak atau tidak dapat dibaca. Simpan ulang sebagai .xlsx lalu unggah kembali.'] + $result;
        } finally {
            $reader->close();
        }

        if ($result['rows'] === [] && $result['fatal'] === null) {
            $result['fatal'] = 'Belum ada baris yang diisi, atau sheet "'.self::SHEET.'" tidak ditemukan.';
        }
        $result['receipts'] = count($groups);

        return $result;
    }

    /**
     * Simpan hasil impor (file sudah dibaca ulang & tanpa error): aktifkan Lacak Persediaan, Stok Opname dari Stok
     * Fisik, lalu satu Penerimaan Barang per Supplier + No. Nota. Opsional: HPP menu = harga beli per satuan terbaru.
     *
     * @return array{receipts: list<string>, counted: int, enabled: int, hpp_updated: int}
     */
    public function commit(array $parsed, bool $updateHpp, ?string $notes, User $by): array
    {
        abort_if($parsed['fatal'] || $parsed['errors'] > 0, 422, 'File masih memiliki baris bermasalah.');
        $rows = collect($parsed['rows']);
        $stock = app(FnbStockService::class);

        return DB::transaction(function () use ($rows, $updateHpp, $notes, $by, $stock) {
            // Semua menu di file dikunci di awal berurutan id: langkah berikutnya (aktifkan lacak, opname, penerimaan
            // per supplier) mengunci ulang baris yang sama, jadi urutan kunci tetap searah dengan pelunasan kasir.
            $menus = FnbMenu::query()->whereIn('id', $rows->pluck('menu_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $enabled = 0;
            foreach ($rows->where('enable', true) as $row) {
                $menu = $menus->get($row['menu_id']);
                if ($menu && ! $menu->fresh()->track_stock) {
                    $stock->updateSettings($menu, (float) $menu->hpp, true, $menu->min_stock, $by);
                    $enabled++;
                }
            }

            $counts = $rows->whereNotNull('physical')->mapWithKeys(fn ($r) => [$r['menu_id'] => $r['physical']])->all();
            $counted = $counts ? $stock->opname($counts, trim('Stok fisik dari impor Excel. '.($notes ?? '')), $by)['counted'] : 0;

            $receipts = [];
            foreach ($rows->whereNotNull('qty')->groupBy(fn ($r) => mb_strtolower(($r['supplier'] ?? '').'|'.($r['invoice'] ?? ''))) as $group) {
                $first = $group->first();
                $receipts[] = $stock->receive(
                    $group->map(fn ($r) => ['menu_id' => $r['menu_id'], 'qty' => $r['qty'], 'total' => $r['total']])->values()->all(),
                    $first['supplier'], $first['invoice'], $notes, $by, 'IMPORT',
                )->receipt_number;
            }

            $hppUpdated = 0;
            if ($updateHpp) {
                foreach ($rows->whereNotNull('qty')->filter(fn ($r) => $r['price'] > 0) as $row) {
                    $menu = $menus->get($row['menu_id'])->fresh();
                    if ((float) $menu->hpp !== (float) $row['price']) {
                        $stock->updateSettings($menu, (float) $row['price'], true, $menu->min_stock, $by);
                        $hppUpdated++;
                    }
                }
            }

            return ['receipts' => $receipts, 'counted' => $counted, 'enabled' => $enabled, 'hpp_updated' => $hppUpdated];
        });
    }

    private function wholeNumber(mixed $value, int $min): bool
    {
        return is_numeric($value) && (int) $value == $value && (int) $value >= $min;
    }

    private function key(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower($value));
    }

    private function blank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    private function clean(mixed $value, int $max): ?string
    {
        $value = trim(mb_substr(preg_replace('/\s+/u', ' ', (string) $value), 0, $max));

        return $value === '' ? null : $value;
    }

    /** Teks yang diawali = + - @ tidak dianggap rumus oleh Excel. */
    private function safe(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }
}
