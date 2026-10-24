<?php

namespace App\Console\Commands;

use App\Services\Padel\RecurringScheduleService;
use Illuminate\Console\Command;

class GenerateRecurringBookings extends Command
{
    protected $signature = 'padel:generate-recurring';

    protected $description = 'Buat booking Jadwal Tetap Mingguan yang sudah masuk jangkauan (dipotong dari kuota jam membership).';

    public function handle(RecurringScheduleService $service): int
    {
        $summary = $service->generateAll();

        $this->info("Jadwal diproses: {$summary['schedules']}, booking dibuat: {$summary['booked']}, gagal: {$summary['failed']}.");

        return self::SUCCESS;
    }
}
