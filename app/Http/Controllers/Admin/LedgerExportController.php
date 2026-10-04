<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Pages\BukuTransaksi;
use App\Http\Controllers\Controller;
use App\Models\Finance\LedgerEntry;
use App\Services\Audit\ActivityLogger;
use App\Services\Finance\LedgerReport;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Arr;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Modul 17 FR-05 — Export Buku Transaksi sesuai filter aktif. Route GET biasa (bukan aksi Livewire) dengan batas baris;
 * URL-nya dikecualikan dari mode SPA panel (lihat AdminPanelProvider) supaya file diunduh, bukan dirender jadi halaman.
 *  - Excel (XLSX): dua lembar — "Transaksi" (satu baris per pembayaran / refund) & "Rincian Kategori" (per baris buku).
 *  - PDF: laporan siap cetak — ringkasan, rekap per kategori, daftar transaksi (dibatasi ledger.pdf_max_rows baris).
 * Nomor HP & email customer TIDAK ikut (PRD §10 no. 5). Teks dinetralkan dari formula injection (Excel).
 */
class LedgerExportController extends Controller
{
    public const TRANSACTION_HEADER = ['Waktu (WIB)', 'Jenis', 'No. Order', 'Sumber', 'Kategori', 'Customer', 'Kasir', 'Metode Bayar',
        'Bukti / Referensi', 'Harga Item (Kotor)', 'Diskon', 'Penjualan Bersih', 'Biaya Layanan', 'Pajak', 'Total Dibayar', 'Benefit (non-tunai)', 'Status'];

    public const DETAIL_HEADER = ['Waktu (WIB)', 'Jenis', 'No. Order', 'Sumber', 'Kategori', 'Customer', 'Kasir', 'Metode Bayar',
        'Harga Item (Kotor)', 'Diskon', 'Penjualan Bersih', 'Biaya Layanan', 'Pajak', 'Total Dibayar', 'Benefit (non-tunai)', 'ID Pembayaran', 'ID Refund'];

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless(
            $user && $user->can('View:BukuTransaksi') && $user->can('export_ledger'),
            403,
            'Akses ditolak: Anda tidak memiliki izin [export_ledger] untuk export Buku Transaksi.'
        );

        // validate() hanya mengembalikan parameter yang ada di whitelist — sisanya dibuang.
        $filters = $request->validate(LedgerReport::filterRules());
        $format = $filters['format'] ?? 'xlsx';
        $limit = $format === 'pdf'
            ? max(1, (int) config('ledger.pdf_max_rows', 1500))
            : max(1, (int) config('ledger.export_max_rows', 50000));

        $transactions = LedgerReport::orderNewestFirst(LedgerReport::grouped(LedgerReport::applyFilters(LedgerEntry::query(), $filters)))->limit($limit);
        $details = LedgerReport::applyFilters(LedgerEntry::query(), $filters)->orderByDesc('occurred_at')->orderByDesc('id')->limit($limit);

        [$from, $until] = LedgerReport::resolveRange($filters['periode'] ?? null, $filters['dari'] ?? null, $filters['sampai'] ?? null);
        $periodLabel = BukuTransaksi::periodLabel($filters['periode'] ?? null, $from, $until);
        ActivityLogger::record(
            module: 'FINANCE',
            event: 'ledger.exported',
            description: 'Export Buku Transaksi ke '.($format === 'pdf' ? 'PDF' : 'Excel').' ('.$periodLabel.')',
            meta: array_filter([
                'format' => $format,
                'dari' => $from,
                'sampai' => $until,
                'batas_baris' => $limit,
                'filter' => Arr::except($filters, ['search', 'format', 'periode', 'dari', 'sampai']) ?: null,
                'pencarian' => $filters['search'] ?? null,
            ], fn ($value) => $value !== null),
            severity: ActivityLogger::WARNING,
        );

        $stamp = now(LedgerReport::TIMEZONE)->format('Ymd-His');

        if ($format === 'pdf') {
            return $this->pdf($filters, $transactions, $limit, $periodLabel, $stamp, $user->name);
        }
        $path = tempnam(sys_get_temp_dir(), 'ledger').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $bold = (new Style)->setFontBold();
        // Nominal tetap angka (bisa dijumlah di Excel), ditampilkan dengan pemisah ribuan.
        $money = (new Style)->setFormat('#,##0;-#,##0');

        $writer->getCurrentSheet()->setName('Transaksi');
        $writer->addRow(Row::fromValues(self::TRANSACTION_HEADER, $bold));
        foreach ($transactions->cursor() as $row) {
            $writer->addRow(self::xlsxRow(self::transactionRow($row), $money));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName('Rincian Kategori');
        $writer->addRow(Row::fromValues(self::DETAIL_HEADER, $bold));
        foreach ($details->cursor() as $row) {
            $writer->addRow(self::xlsxRow(self::detailRow($row), $money));
        }
        $writer->close();

        return response()->download($path, "buku-transaksi-{$stamp}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    private function pdf(array $filters, \Illuminate\Database\Eloquent\Builder $transactions, int $limit, string $periodLabel, string $stamp, ?string $printedBy): Response
    {
        $base = LedgerReport::applyFilters(LedgerEntry::query(), $filters);
        $rows = $transactions->get();
        // Jumlah transaksi (satu per pembayaran / refund) — untuk menandai PDF yang terpotong batas baris.
        $total = (int) (clone $base)->toBase()->selectRaw('COUNT(DISTINCT COALESCE(refund_id, payment_id)) as c')->value('c');

        $byCategory = (clone $base)->toBase()
            ->selectRaw("category, SUM(net_amount) as net, SUM(service_amount) as service, SUM(tax_amount) as tax, SUM(total_amount) as total, COUNT(DISTINCT CASE WHEN entry_type <> 'REFUND' THEN payment_id END) as payments")
            ->groupBy('category')->orderByDesc('total')->get();

        return Pdf::loadView('exports.buku-transaksi-pdf', [
            'summary' => LedgerReport::summary($base, ...LedgerReport::resolveRange($filters['periode'] ?? null, $filters['dari'] ?? null, $filters['sampai'] ?? null)),
            'byCategory' => $byCategory,
            'rows' => $rows,
            'truncated' => $total > $rows->count(),
            'total' => $total,
            'limit' => $limit,
            'periodLabel' => $periodLabel,
            'printedBy' => $printedBy,
            'printedAt' => now(LedgerReport::TIMEZONE)->format('d/m/Y H:i').' WIB',
            'company' => rescue(fn () => \App\Models\Setting\CompanyProfileSetting::current(), null, false),
        ])->setPaper('a4', 'landscape')->download("buku-transaksi-{$stamp}.pdf");
    }

    /** @param  array<int, string|float>  $values */
    private static function xlsxRow(array $values, Style $money): Row
    {
        return new Row(array_map(fn ($value) => Cell::fromValue($value, is_float($value) ? $money : null), $values));
    }

    /** @return array<int, string|float> */
    public static function transactionRow(LedgerEntry $row): array
    {
        return [
            $row->occurred_at?->timezone(LedgerReport::TIMEZONE)->format('Y-m-d H:i:s') ?? '',
            LedgerEntry::ENTRY_TYPES[$row->entry_type] ?? $row->entry_type,
            LedgerReport::cellSafe($row->order_number),
            LedgerEntry::sourceLabel($row->source),
            LedgerReport::categoriesLabel($row->categories),
            LedgerReport::cellSafe($row->customer_name),
            LedgerReport::cellSafe(LedgerReport::cashierLabel($row)),
            LedgerReport::cellSafe($row->payment_method_label ?? $row->payment_method),
            LedgerReport::cellSafe($row->payment_reference),
            (float) $row->gross_amount,
            (float) $row->discount_amount,
            (float) $row->net_amount,
            (float) $row->service_amount,
            (float) $row->tax_amount,
            (float) $row->total_amount,
            (float) $row->benefit_amount,
            LedgerReport::statusLabel($row),
        ];
    }

    /** @return array<int, string|float> */
    public static function detailRow(LedgerEntry $row): array
    {
        return [
            $row->occurred_at?->timezone(LedgerReport::TIMEZONE)->format('Y-m-d H:i:s') ?? '',
            LedgerEntry::ENTRY_TYPES[$row->entry_type] ?? $row->entry_type,
            LedgerReport::cellSafe($row->order_number),
            LedgerEntry::sourceLabel($row->source),
            LedgerEntry::categoryLabel($row->category),
            LedgerReport::cellSafe($row->customer_name),
            LedgerReport::cellSafe(LedgerReport::cashierLabel($row)),
            LedgerReport::cellSafe($row->payment_method_label ?? $row->payment_method),
            (float) $row->gross_amount,
            (float) $row->discount_amount,
            (float) $row->net_amount,
            (float) $row->service_amount,
            (float) $row->tax_amount,
            (float) $row->total_amount,
            (float) $row->benefit_amount,
            (string) $row->payment_id,
            (string) $row->refund_id,
        ];
    }
}
