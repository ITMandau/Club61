<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Pages\LogAktivitas;
use App\Http\Controllers\Controller;
use App\Models\Audit\ActivityLog;
use App\Services\Audit\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Modul 16 — Export CSV Log Aktivitas. Sengaja route GET biasa (bukan aksi Livewire): respons aksi
 * Livewire di-buffer penuh di memori lalu di-base64 ke JSON, jadi export puluhan ribu baris bikin crash.
 * Di sini baris di-stream per potongan (keyset created_at + id) dan langsung di-flush ke browser.
 */
class ActivityLogExportController extends Controller
{
    private const CHUNK = 1000;

    public function __invoke(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless(
            $user && $user->can('View:LogAktivitas') && $user->can('export_activity_logs'),
            403,
            'Akses ditolak: Anda tidak memiliki izin [export_activity_logs] untuk export log aktivitas.'
        );

        // validate() hanya mengembalikan parameter yang ada di whitelist — sisanya dibuang.
        $filters = $request->validate(LogAktivitas::exportFilterRules());
        $limit = max(1, (int) config('audit.export_max_rows', 50000));
        $direction = ($filters['arah'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query = LogAktivitas::applyExportFilters(ActivityLog::query(), $filters);

        // Jumlah yang dicatat = yang benar-benar ikut ter-export (dibatasi export_max_rows).
        $total = min((clone $query)->count(), $limit);

        ActivityLogger::record(
            module: 'AUTH',
            event: 'activity_log.exported',
            description: "Export {$total} baris log aktivitas ke CSV",
            meta: array_filter([
                'jumlah_baris' => $total,
                'batas_baris' => $limit,
                'filter' => Arr::except($filters, ['search']) ?: null,
                'pencarian' => $filters['search'] ?? null,
            ], fn ($value) => $value !== null),
            severity: ActivityLogger::WARNING,
        );

        $filename = 'log-aktivitas-'.now('Asia/Jakarta')->format('Ymd-His').'.csv';

        return response()->streamDownload(
            fn () => $this->streamCsv($query, $limit, $direction),
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8', 'X-Accel-Buffering' => 'no']
        );
    }

    private function streamCsv(Builder $query, int $limit, string $direction): void
    {
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM supaya Excel membaca UTF-8 dengan benar
        // Escape char kosong: JSON di kolom Perubahan/Detail berisi \" — dengan escape default "\" sel jadi rusak.
        fputcsv($out, LogAktivitas::CSV_HEADER, ',', '"', '');

        $operator = $direction === 'asc' ? '>' : '<';
        $sent = 0;
        $cursor = null;

        while ($sent < $limit) {
            $size = min(self::CHUNK, $limit - $sent);
            $chunk = (clone $query)
                ->when($cursor, fn (Builder $q) => $q->where(fn (Builder $w) => $w
                    ->where('created_at', $operator, $cursor['created_at'])
                    ->orWhere(fn (Builder $same) => $same->where('created_at', $cursor['created_at'])->where('id', $operator, $cursor['id']))))
                ->orderBy('created_at', $direction)
                ->orderBy('id', $direction)
                ->limit($size)
                ->get();

            foreach ($chunk as $log) {
                fputcsv($out, LogAktivitas::csvRow($log), ',', '"', '');
            }

            $sent += $chunk->count();
            fflush($out);
            flush();

            if ($chunk->count() < $size) {
                break;
            }

            $last = $chunk->last();
            $cursor = ['created_at' => $last->getRawOriginal('created_at'), 'id' => $last->getKey()];
        }

        fclose($out);
    }
}
