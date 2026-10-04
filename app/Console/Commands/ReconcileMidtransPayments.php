<?php

namespace App\Console\Commands;

use App\Models\Pos\Order;
use App\Services\Payment\MidtransReconciliationService;
use Illuminate\Console\Command;

/**
 * Jaring pengaman kalau webhook Midtrans tidak sampai: cek ulang semua order online yang
 * pembayarannya masih PENDING (24 jam terakhir) langsung ke Midtrans. Order yang ternyata
 * sudah lunas dilunasi lewat jalur yang sama dengan webhook. Order yang terlanjur CANCELLED
 * tapi uangnya masuk otomatis tercatat PAID + Refund PENDING (lihat markOrderAsPaid).
 */
class ReconcileMidtransPayments extends Command
{
    protected $signature = 'payment:reconcile-midtrans {--hours=24 : Rentang order yang dicek (jam ke belakang)}';

    protected $description = 'Cek ulang status pembayaran Midtrans untuk order yang masih pending (cadangan webhook)';

    public function handle(MidtransReconciliationService $reconciler): int
    {
        $since = now()->subHours((int) $this->option('hours'));

        // Order berstatus PAID TETAP ikut dicek kalau masih punya pembayaran Midtrans PENDING — itu
        // tagihan selisih reschedule yang sedang dibayar customer lewat invoice. Jendela waktu memakai
        // updated_at juga: tagihan selisih bisa dibuat lama sebelum customer akhirnya membayar.
        $orders = Order::query()
            ->whereHas('payments', fn ($q) => $q
                ->where('payment_gateway', 'MIDTRANS')
                ->where('status', 'PENDING')
                ->where(fn ($w) => $w->where('created_at', '>=', $since)->orWhere('updated_at', '>=', $since))
                // Beri webhook asli kesempatan datang duluan.
                ->where('created_at', '<=', now()->subMinutes(2)))
            ->get();

        $counts = array_fill_keys([
            MidtransReconciliationService::PAID,
            MidtransReconciliationService::PENDING,
            MidtransReconciliationService::NOT_PAID,
            MidtransReconciliationService::ERROR,
        ], 0);

        foreach ($orders as $order) {
            $result = $reconciler->reconcileOrder($order);
            $counts[$result]++;

            if ($result === MidtransReconciliationService::PAID) {
                $this->info("LUNAS di Midtrans, dilunasi: {$order->order_number}");
            }
        }

        $this->line(sprintf(
            'Dicek %d order — lunas: %d, masih pending: %d, tidak dibayar: %d, gagal cek: %d',
            $orders->count(),
            $counts[MidtransReconciliationService::PAID],
            $counts[MidtransReconciliationService::PENDING],
            $counts[MidtransReconciliationService::NOT_PAID],
            $counts[MidtransReconciliationService::ERROR],
        ));

        return self::SUCCESS;
    }
}
