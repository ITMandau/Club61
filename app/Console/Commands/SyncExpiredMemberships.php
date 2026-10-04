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
                // Kondisi 2: kartu sudah TIDAK memberi apa pun lagi sebelum end_date. Hanya kalau SEMUA balance
                // berkuota terbatas, semuanya habis, dan tidak ada diskon tersisa. Dulu cukup "semua HOURS/VISITS
                // sisa 0": balance unlimited (initial_quota null, sisa selalu 0) dianggap habis → kartu gym unlimited
                // hangus sehari setelah dibeli, dan diskon "di luar kuota" ikut hilang sebelum masa berlaku habis.
                $balances = $membership->balances;
                $stillGivesSomething = $balances->contains(fn ($b) => ! in_array($b->quota_type, ['HOURS', 'VISITS'], true)
                    || $b->initial_quota === null
                    || (float) $b->remaining_quota > 0
                    || (float) $b->discount_percent > 0);

                if ($balances->isNotEmpty() && ! $stillGivesSomething) {
                    $isExpired = true;
                }
            }

            if ($isExpired) {
                $membership->update(['status' => 'EXPIRED']);
                $expiredCount++;
            }
        }

        $this->info("Sinkronisasi selesai: {$expiredCount} membership telah ditandai EXPIRED.");

        // Pesanan membership online yang ditinggal tanpa dibayar (> 24 jam) dibatalkan, setelah dicek ke Midtrans.
        $abandoned = app(\App\Services\Membership\MembershipOnlinePaymentService::class)->cancelStale();
        $this->info("{$abandoned} pesanan membership online yang tidak dibayar dibatalkan.");

        return Command::SUCCESS;
    }
}
