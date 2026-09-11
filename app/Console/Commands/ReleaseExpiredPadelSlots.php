<?php

namespace App\Console\Commands;

use App\Services\Padel\PadelBookingService;
use Illuminate\Console\Command;

class ReleaseExpiredPadelSlots extends Command
{
    protected $signature = 'padel:release-expired-slots';
    protected $description = 'Merilis slot lapangan padel yang berstatus LOCKED lebih dari 10 menit tanpa pembayaran';

    public function handle(PadelBookingService $bookingService): int
    {
        $this->info('Memeriksa slot padel kedaluwarsa...');

        $releasedCount = $bookingService->releaseExpiredLocks();

        if ($releasedCount > 0) {
            $this->info("Berhasil merilis {$releasedCount} slot padel kedaluwarsa kembali ke publik.");
        } else {
            $this->line('Tidak ada slot kedaluwarsa yang ditemukan.');
        }

        return Command::SUCCESS;
    }
}
