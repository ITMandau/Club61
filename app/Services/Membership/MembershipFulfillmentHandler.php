<?php

namespace App\Services\Membership;

use App\Models\Membership\UserMembership;
use App\Models\Pos\Order;
use App\Services\Payment\Contracts\DomainFulfillmentHandlerInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MembershipFulfillmentHandler implements DomainFulfillmentHandlerInterface
{
    public function __construct(
        protected MembershipBalanceService $balanceService
    ) {}

    /**
     * Eksekusi pemenuhan aktivasi membership dan saldo kuota pasca pembayaran.
     */
    public function fulfill(Order $order, Collection $items): void
    {
        $memberships = UserMembership::where('order_id', $order->id)
            ->orWhere('order_id', $order->order_number)
            ->get();

        foreach ($memberships as $membership) {
            // Guard: Dilarang memproses ulang kartu yang sudah aktif/selesai
            if (in_array($membership->status, ['ACTIVE', 'COMPLETED', 'MERGED', 'CANCELLED'], true)) {
                continue;
            }

            // Guard Idempotency: Atomic conditional update status dari PENDING_PAYMENT
            // Jika affected row = 0, berarti webhook duplikat / sudah dieksekusi proses lain secara paralel
            $affected = UserMembership::where('id', $membership->id)
                ->where('status', 'PENDING_PAYMENT')
                ->update(['status' => 'ACTIVE']);

            if ($affected === 0) {
                continue;
            }

            // Refresh model post atomic update
            $membership->refresh();

            // Kasus 1: Renewal paket ke kartu lama
            if ($membership->renewal_of_id) {
                $this->balanceService->fulfillRenewal($membership);
                continue;
            }

            // Kasus 2: Pembelian kartu baru
            $startDate = Carbon::today();
            $endDate = $startDate->copy()->addDays($membership->plan->duration_days);

            $membership->start_date = $startDate->toDateString();
            $membership->end_date = $endDate->toDateString();

            if (empty($membership->qr_pass_hash)) {
                $membership->qr_pass_hash = hash(
                    'sha256',
                    $membership->id . '|' . $membership->membership_code . '|' . config('app.key')
                );
            }
            $membership->save();

            // Isi saldo kuota dari initial_quota via TOPUP
            foreach ($membership->balances as $balance) {
                if ($balance->initial_quota !== null && (float) $balance->initial_quota > 0) {
                    $this->balanceService->adjustQuota(
                        balanceId: $balance->id,
                        changeType: 'TOPUP',
                        quantity: (float) $balance->initial_quota,
                        notes: 'Aktivasi pelunasan paket ' . $membership->plan->code,
                        relatedType: UserMembership::class,
                        relatedId: $membership->id
                    );
                }
            }
        }
    }
}
