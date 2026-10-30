<?php

namespace App\Services\Fnb;

use App\Models\Fnb\FnbCashEntry;
use App\Models\Fnb\FnbMenu;
use App\Models\Fnb\FnbStockMovement;
use App\Services\Finance\LedgerReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Modul 29 — export Laporan Keuangan F&B (Excel & PDF) dan Lembar Stok Opname (PDF untuk dicetak). Angka diambil dari
 * FnbReportService yang sama dengan layar, jadi file = layar. Teks dinetralkan dari formula injection (Excel).
 */
class FnbReportExport
{
    public function __construct(private FnbReportService $report) {}

    /** Excel 5 sheet: Ringkasan, Per Menu, Pengeluaran, Riwayat Stok, Stok Saat Ini. */
    public function excel(?string $from, ?string $until, string $periodLabel, string $path): string
    {
        $s = $this->report->summary($from, $until);
        $writer = new Writer();
        $writer->openToFile($path);
        $bold = (new Style())->setFontBold();
        $header = (new Style())->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('662721');
        $money = (new Style())->setFormat('#,##0;-#,##0');
        $row = fn (array $values, ?Style $style = null) => new Row(array_map(
            fn ($v) => Cell::fromValue(is_string($v) ? LedgerReport::cellSafe($v) : $v, $style ?? (is_float($v) ? $money : null)),
            $values,
        ));

        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Ringkasan');
        $sheet->setColumnWidth(52, 1);
        $sheet->setColumnWidth(20, 2);
        $writer->addRow($row(['Laporan Keuangan F&B — Club 61'], $bold));
        $writer->addRow($row(['Periode', $periodLabel]));
        $writer->addRow($row(['Dicetak', now(LedgerReport::TIMEZONE)->format('d/m/Y H:i').' WIB']));
        $writer->addRow($row([]));
        $writer->addRow($row(['LAPORAN LABA', 'Rp'], $header));
        foreach ([
            ['Penjualan (bersih, tanpa pajak & biaya layanan)', $s['sales']],
            ['HPP terjual', -$s['hpp']],
            ['Laba Kotor', $s['gross_profit']],
            ['Kerugian stok (rusak, hilang, selisih opname)', -$s['stock_loss']],
            ['Biaya operasional', -$s['operational']],
            ['Laba Bersih', $s['net_profit']],
        ] as [$label, $value]) {
            $writer->addRow($row([$label, (float) $value]));
        }
        $writer->addRow($row([]));
        $writer->addRow($row(['ARUS KAS', 'Rp'], $header));
        foreach ([
            ['Uang masuk penjualan kasir F&B (termasuk pajak & biaya layanan)', $s['money_in']],
            ['Pembelian persediaan & bahan', -$s['stock_purchases']],
            ['Biaya operasional', -$s['operational']],
            ['Selisih Arus Kas', $s['cash_flow']],
        ] as [$label, $value]) {
            $writer->addRow($row([$label, (float) $value]));
        }
        $writer->addRow($row([]));
        $writer->addRow($row(['PENGELUARAN PER KATEGORI', 'Rp'], $header));
        foreach ($s['expense_by_category'] as $name => $amount) {
            $writer->addRow($row([(string) $name, (float) $amount]));
        }
        if ($s['missing_cost_qty'] > 0) {
            $writer->addRow($row([]));
            $writer->addRow($row(["Catatan: {$s['missing_cost_qty']} item terjual belum memiliki HPP (dihitung Rp0). Menu: "
                .$s['menus']->where('missing_cost_qty', '>', 0)->pluck('name')->implode(', ')]));
        }

        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName('Per Menu');
        foreach ([34, 12, 18, 18, 18, 12] as $i => $w) {
            $sheet->setColumnWidth($w, $i + 1);
        }
        $writer->addRow($row(['Menu', 'Terjual', 'Penjualan', 'HPP', 'Laba Kotor', 'Margin %'], $header));
        foreach ($s['menus']->sortByDesc('profit') as $m) {
            $writer->addRow($row([(string) $m['name'], $m['qty'], (float) $m['sales'], (float) $m['hpp'], (float) $m['profit'],
                $m['sales'] > 0 ? round($m['profit'] / $m['sales'] * 100) : 0]));
        }

        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName('Pengeluaran');
        foreach ([12, 24, 44, 16, 14, 22, 40] as $i => $w) {
            $sheet->setColumnWidth($w, $i + 1);
        }
        $writer->addRow($row(['Tanggal', 'Kategori', 'Keterangan', 'Nominal', 'Status', 'Dicatat Oleh', 'Alasan Batal'], $header));
        FnbCashEntry::query()->with(['category:id,name', 'creator:id,name'])
            ->when($from, fn ($q) => $q->whereDate('entry_date', '>=', $from))
            ->when($until, fn ($q) => $q->whereDate('entry_date', '<=', $until))
            ->orderBy('entry_date')->orderBy('created_at')
            ->each(fn (FnbCashEntry $e) => $writer->addRow($row([
                $e->entry_date->format('Y-m-d'), (string) $e->category?->name, (string) $e->notes, (float) $e->amount,
                $e->voided_at ? 'Dibatalkan' : 'Aktif', (string) $e->creator?->name, (string) $e->void_reason,
            ])));

        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName('Riwayat Stok');
        foreach ([18, 30, 30, 12, 12, 60, 22] as $i => $w) {
            $sheet->setColumnWidth($w, $i + 1);
        }
        $writer->addRow($row(['Waktu (WIB)', 'Menu', 'Jenis', 'Perubahan', 'Stok Akhir', 'Keterangan', 'Oleh'], $header));
        $this->report->movementsBetween($from, $until)->with(['creator:id,name', 'receipt:id,receipt_number,supplier,invoice_number'])
            ->orderBy('created_at')
            ->each(fn (FnbStockMovement $mv) => $writer->addRow($row([
                $mv->created_at?->timezone(LedgerReport::TIMEZONE)->format('Y-m-d H:i'), $mv->menu_name,
                $mv->typeLabel().($mv->voided_at ? ' (dibatalkan)' : ''), $mv->qty_change, $mv->stock_after,
                implode(' · ', array_filter([
                    $mv->receipt ? 'No. '.$mv->receipt->receipt_number : null,
                    $mv->receipt?->supplier ? 'Supplier: '.$mv->receipt->supplier : null,
                    $mv->purchase_total !== null ? 'Harga pembelian '.number_format((float) $mv->purchase_total, 0, ',', '.') : null,
                    $mv->order_id ? 'Pesanan kasir F&B' : null,
                    $mv->notes,
                ])),
                $mv->creator?->name ?? ($mv->type === FnbStockMovement::SALE ? 'Sistem' : ''),
            ])));

        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName('Stok Saat Ini');
        foreach ([34, 22, 10, 10, 14, 16, 18, 14] as $i => $w) {
            $sheet->setColumnWidth($w, $i + 1);
        }
        $writer->addRow($row(['Menu', 'Kategori', 'Lacak', 'Stok', 'Stok Minimum', 'HPP', 'Nilai Stok', 'Status'], $header));
        $status = ['MINUS' => 'Minus', 'EMPTY' => 'Habis', 'LOW' => 'Menipis', 'OK' => 'Aman'];
        FnbMenu::query()->with('category:id,name')->orderBy('name')
            ->each(fn (FnbMenu $m) => $writer->addRow($row([
                $m->name, (string) $m->category?->name, $m->track_stock ? 'Ya' : 'Tidak',
                $m->track_stock ? $m->stock_qty : '', $m->track_stock ? ($m->min_stock ?? '') : '',
                (float) $m->hpp, $m->track_stock && $m->stock_qty > 0 ? (float) ($m->stock_qty * $m->hpp) : 0.0,
                $status[$m->stockStatus()] ?? 'Tidak dilacak',
            ])));

        $writer->close();

        return $path;
    }

    /** PDF ringkas untuk owner / PM: ringkasan laba & arus kas, pengeluaran per kategori, rekap per menu, stok menipis. */
    public function pdf(?string $from, ?string $until, string $periodLabel, ?string $printedBy): string
    {
        $s = $this->report->summary($from, $until);

        return Pdf::loadView('exports.laporan-fnb-pdf', [
            's' => $s,
            'menus' => $s['menus']->sortByDesc('sales')->values(),
            'alerts' => $this->report->stockAlerts(),
            'periodLabel' => $periodLabel,
            'printedBy' => $printedBy,
            'printedAt' => now(LedgerReport::TIMEZONE)->format('d/m/Y H:i').' WIB',
        ])->setPaper('a4', 'portrait')->output();
    }

    /**
     * Laporan Pengeluaran F&B sesuai filter halaman (periode, kategori, status): sheet Rincian + Per Kategori.
     * Total & rekap hanya menghitung catatan aktif; yang dibatalkan tetap tampil di rincian dengan alasannya.
     */
    public function expensesExcel(Builder $entries, string $periodLabel, string $statusLabel, string $path): string
    {
        $rows = $entries->get();
        $writer = new Writer();
        $writer->openToFile($path);
        $header = (new Style())->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('662721');
        $bold = (new Style())->setFontBold();
        $money = (new Style())->setFormat('#,##0;-#,##0');
        $row = fn (array $values, ?Style $style = null) => new Row(array_map(
            fn ($v) => Cell::fromValue(is_string($v) ? LedgerReport::cellSafe($v) : $v, $style ?? (is_float($v) ? $money : null)),
            $values,
        ));

        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Rincian');
        foreach ([12, 24, 22, 46, 16, 14, 22, 22, 36] as $i => $w) {
            $sheet->setColumnWidth($w, $i + 1);
        }
        $writer->addRow($row(['Pengeluaran F&B — '.$periodLabel.' · '.$statusLabel], $bold));
        $writer->addRow($row([]));
        $writer->addRow($row(['Tanggal', 'Kategori', 'Jenis', 'Keterangan', 'Nominal', 'Status', 'No. Penerimaan', 'Dicatat Oleh', 'Alasan Batal'], $header));
        foreach ($rows as $e) {
            $writer->addRow($row([
                $e->entry_date->format('Y-m-d'), (string) $e->category?->name, $e->category?->is_stock_purchase ? 'Pembelian persediaan / bahan' : 'Biaya operasional',
                (string) $e->notes, (float) $e->amount, $e->voided_at ? 'Dibatalkan' : 'Aktif', (string) $e->receipt?->receipt_number,
                (string) $e->creator?->name, (string) $e->void_reason,
            ]));
        }
        $active = $rows->whereNull('voided_at');
        $writer->addRow($row([]));
        $writer->addRow($row(['Total pengeluaran aktif', '', '', '', (float) $active->sum('amount')], $bold));

        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName('Per Kategori');
        foreach ([30, 28, 14, 18] as $i => $w) {
            $sheet->setColumnWidth($w, $i + 1);
        }
        $writer->addRow($row(['Kategori', 'Jenis', 'Jumlah Catatan', 'Total'], $header));
        foreach ($this->expenseByCategory($active) as $c) {
            $writer->addRow($row([$c['name'], $c['type'], $c['count'], (float) $c['total']]));
        }
        $writer->addRow($row(['Total', '', $active->count(), (float) $active->sum('amount')], $bold));

        $writer->close();

        return $path;
    }

    public function expensesPdf(Builder $entries, string $periodLabel, string $statusLabel, ?string $printedBy): string
    {
        $rows = $entries->get();
        $active = $rows->whereNull('voided_at');

        return Pdf::loadView('exports.pengeluaran-fnb-pdf', [
            'rows' => $rows,
            'byCategory' => $this->expenseByCategory($active),
            'totals' => [
                'all' => (float) $active->sum('amount'),
                'stock' => (float) $active->filter(fn ($e) => $e->category?->is_stock_purchase)->sum('amount'),
                'operational' => (float) $active->reject(fn ($e) => $e->category?->is_stock_purchase)->sum('amount'),
                'voided' => $rows->whereNotNull('voided_at')->count(),
            ],
            'periodLabel' => $periodLabel,
            'statusLabel' => $statusLabel,
            'printedBy' => $printedBy,
            'printedAt' => now(LedgerReport::TIMEZONE)->format('d/m/Y H:i').' WIB',
        ])->setPaper('a4', 'portrait')->output();
    }

    /** @return list<array{name: string, type: string, count: int, total: float}> urut terbesar */
    private function expenseByCategory(Collection $active): array
    {
        return $active->groupBy(fn ($e) => $e->category?->name ?? '-')
            ->map(fn ($items, $name) => [
                'name' => (string) $name,
                'type' => $items->first()->category?->is_stock_purchase ? 'Pembelian persediaan / bahan' : 'Biaya operasional',
                'count' => $items->count(),
                'total' => (float) $items->sum('amount'),
            ])
            ->sortByDesc('total')->values()->all();
    }

    /** Lembar hitung fisik untuk dibawa ke gudang (kolom fisik & catatan kosong, tanpa angka sistem = hitung buta). */
    public function opnameSheet(?string $printedBy): string
    {
        return Pdf::loadView('exports.lembar-opname-fnb', [
            'menus' => FnbMenu::query()->with('category:id,name')->where('track_stock', true)->orderBy('name')->get(),
            'printedBy' => $printedBy,
            'printedAt' => now(LedgerReport::TIMEZONE)->format('d/m/Y H:i').' WIB',
        ])->setPaper('a4', 'portrait')->output();
    }
}
