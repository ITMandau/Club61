<?php

namespace App\Console\Commands;

use App\Models\Audit\ActivityLog;
use App\Services\Audit\ActivityLogger;
use Illuminate\Console\Command;

/**
 * Retensi Log Aktivitas — SATU-SATUNYA jalur penghapusan log. Dijalankan sistem (scheduler),
 * bukan user, dan penghapusannya sendiri ikut tercatat.
 */
class PruneActivityLogs extends Command
{
    protected $signature = 'audit:prune';

    protected $description = 'Hapus log aktivitas yang lebih tua dari masa simpan (config audit.retention_months)';

    public function handle(): int
    {
        $months = (int) config('audit.retention_months', 24);

        // Pengaman salah konfigurasi: AUDIT_RETENTION_MONTHS=0 / negatif tidak boleh menghapus semua log.
        if ($months < 1) {
            $this->error('AUDIT_RETENTION_MONTHS harus minimal 1 bulan. Tidak ada log yang dihapus.');

            return self::FAILURE;
        }

        $cutoff = now()->subMonths($months);
        $deleted = ActivityLog::pruneOlderThan($cutoff);

        if ($deleted > 0) {
            ActivityLogger::record(
                module: 'SYSTEM',
                event: 'activity_log.pruned',
                description: "Sistem menghapus {$deleted} log aktivitas yang lebih tua dari {$months} bulan (retensi)",
                meta: ['jumlah_dihapus' => $deleted, 'batas_tanggal' => $cutoff->toDateTimeString()],
            );
        }

        $this->info("{$deleted} log aktivitas dihapus (lebih tua dari {$cutoff->toDateString()}).");

        return self::SUCCESS;
    }
}
