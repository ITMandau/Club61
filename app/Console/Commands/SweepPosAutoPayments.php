<?php

namespace App\Console\Commands;

use App\Services\Pos\PosMidtransQrisService;
use Illuminate\Console\Command;

/** Batalkan Bayar Otomatis kasir (QR / VA) yang kedaluwarsa tanpa dibayar, beserta ordernya. */
class SweepPosAutoPayments extends Command
{
    protected $signature = 'pos:sweep-auto-payments';

    protected $description = 'Batalkan Bayar Otomatis kasir yang kedaluwarsa tanpa dibayar (order, slot & kartu member ikut dibatalkan)';

    public function handle(PosMidtransQrisService $service): int
    {
        $count = $service->sweepAbandoned();
        $this->info("{$count} tagihan Bayar Otomatis dibereskan.");

        return self::SUCCESS;
    }
}
