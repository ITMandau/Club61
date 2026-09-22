<?php

namespace App\Console\Commands;

use App\Models\Membership\UserMembership;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncExpiredMemberships extends Command
{
    protected $signature = 'membership:sync-expired';

    protected $description = 'Sinkronisasi status membership yang sudah habis masa aktif atau kuotanya habis.';

    public function handle(): int
    {
        $today = Carbon::today()->toDateString();
        $expiredCount = 0;

        // Ambil seluruh membership yang masih bertatus ACTIVE
        $activeMemberships = UserMembership::with('balances')
            ->where('status', 'ACTIVE')
            ->get();

        foreach ($activeMemberships as $membership) {
            $isExpired = false;

            // Kondisi 1: end_date sudah terlewat
            if ($membership->end_date && $membership->end_date < $today) {
                $isExpired = true;
            } else {
                // Kondisi 2: Kuota HOURS / VISITS habis
                // CRITICAL: Hanya berlaku jika kartu MEMILIKI minimal 1 balance HOURS atau VISITS
                $trackableBalances = $membership->balances->whereIn('quota_type', ['HOURS', 'VISITS']);

                if ($trackableBalances->isNotEmpty()) {
                    $allTrackableDepleted = $trackableBalances->every(function ($balance) {
                        return (float) $balance->remaining_quota <= 0;
                    });

                    if ($allTrackableDepleted) {
                        $isExpired = true;
                    }
                }
            }

            if ($isExpired) {
                $membership->update(['status' => 'EXPIRED']);
                $expiredCount++;
            }
        }

        $this->info("Sinkronisasi selesai: {$expiredCount} membership telah ditandai EXPIRED.");

        return Command::SUCCESS;
    }
}
