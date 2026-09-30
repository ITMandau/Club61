<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

\Illuminate\Support\Facades\Schedule::command('padel:release-expired-slots')->everyMinute();
\Illuminate\Support\Facades\Schedule::command('membership:sync-expired')->dailyAt('00:01');
// Cadangan webhook Midtrans: order yang sudah dibayar tapi notifikasinya tidak sampai tetap lunas otomatis.
\Illuminate\Support\Facades\Schedule::command('payment:reconcile-midtrans')->everyFiveMinutes()->withoutOverlapping();
// Retensi Log Aktivitas (Modul 16) — default simpan 24 bulan, atur lewat AUDIT_RETENTION_MONTHS.
\Illuminate\Support\Facades\Schedule::command('audit:prune')->dailyAt('02:30')->withoutOverlapping();

