<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HandlesFnbErrors;
use App\Services\Audit\ActivityLogger;
use App\Services\Finance\LedgerReport;
use App\Services\Fnb\FnbReportExport;
use App\Services\Fnb\FnbReportService;
use BackedEnum;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Modul 29 — Laporan Keuangan F&B (dashboard): Laba (penjualan − HPP − kerugian stok − biaya operasional), Arus Kas
 * (uang masuk penjualan − semua pengeluaran), tren 6 bulan, menu terlaris / paling untung, dan stok menipis.
 * Unduh Excel / PDF sesuai periode yang dipilih (izin export_reports, tercatat di Log Aktivitas).
 */
class LaporanFnb extends Page
{
    use HandlesFnbErrors;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationLabel = 'Laporan F&B';

    protected static string|UnitEnum|null $navigationGroup = 'F&B';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Laporan Keuangan F&B';

    protected string $view = 'filament.pages.laporan-fnb';

    public string $preset = 'bulan_ini';

    public ?string $from = null;

    public ?string $until = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('View:LaporanFnb');
    }

    public static function canExport(): bool
    {
        return self::canAccess() && (bool) auth()->user()?->can('export_reports');
    }

    public function exportExcel(): BinaryFileResponse
    {
        [$from, $until, $label] = $this->exportRange('Excel');
        $path = app(FnbReportExport::class)->excel($from, $until, $label, $this->tempXlsxPath('fnb-report'));

        return response()->download($path, $this->fileName('xlsx'))->deleteFileAfterSend();
    }

    public function exportPdf(): StreamedResponse
    {
        [$from, $until, $label] = $this->exportRange('PDF');
        $pdf = app(FnbReportExport::class)->pdf($from, $until, $label, auth()->user()?->name);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, $this->fileName('pdf'), ['Content-Type' => 'application/pdf']);
    }

    /** @return array{0: ?string, 1: ?string, 2: string} */
    private function exportRange(string $format): array
    {
        $this->authorizeAction('View:LaporanFnb');
        $this->authorizeAction('export_reports');
        [$from, $until] = LedgerReport::resolveRange($this->preset, $this->from, $this->until);
        $label = BukuTransaksi::periodLabel($this->preset, $from, $until);

        ActivityLogger::record('FNB', 'fnb_report.exported', "Export Laporan Keuangan F&B ke {$format} ({$label})",
            meta: array_filter(['format' => $format, 'dari' => $from, 'sampai' => $until]));

        return [$from, $until, $label];
    }

    private function fileName(string $extension): string
    {
        return 'laporan-fnb-'.now(LedgerReport::TIMEZONE)->format('Ymd-His').'.'.$extension;
    }

    protected function getViewData(): array
    {
        $report = app(FnbReportService::class);
        [$from, $until] = LedgerReport::resolveRange($this->preset, $this->from, $this->until);
        $summary = $report->summary($from, $until);

        return [
            'periodLabel' => BukuTransaksi::periodLabel($this->preset, $from, $until),
            's' => $summary,
            'topQty' => $summary['menus']->sortByDesc('qty')->take(8)->values(),
            'topProfit' => $summary['menus']->sortByDesc('profit')->take(8)->values(),
            'trend' => $report->monthlyTrend(),
            'alerts' => $report->stockAlerts(),
        ];
    }
}
