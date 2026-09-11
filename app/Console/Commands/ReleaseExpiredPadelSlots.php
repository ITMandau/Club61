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
        $this->info('Memeriksa slot dan tiket padel kedaluwarsa...');

        $result = $bookingService->syncExpiredAndCompletedBookings();

        if ($result['released_locks'] > 0) {
            $this->info("Berhasil merilis {$result['released_locks']} slot padel terkunci (LOCKED) yang kedaluwarsa.");
        }
        if ($result['expired'] > 0) {
            $this->info("Berhasil mengubah {$result['expired']} tiket PAID lewat jadwal tanpa check-in menjadi EXPIRED.");
        }
        if ($result['completed'] > 0) {
            $this->info("Berhasil mengubah {$result['completed']} sesi CHECKED_IN yang telah usai menjadi COMPLETED.");
        }

        if ($result['released_locks'] === 0 && $result['expired'] === 0 && $result['completed'] === 0) {
            $this->line('Semua slot dan tiket padel sudah sinkron.');
        }

        return Command::SUCCESS;
    }
}
