<?php

namespace App\Console\Commands;

use App\Models\Payment\DokuNotification;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Services\Payment\Doku\DokuClient;
use App\Services\Payment\Doku\DokuNotificationProcessor;
use App\Services\Payment\Doku\DokuReconciliationService;
use App\Services\Payment\Doku\DokuSessionGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Jaring pengaman DOKU (Modul 27), tiap 5 menit:
 *   1. Notifikasi yang belum tuntas (status DOKU belum sinkron, tagihan belum ditemukan, proses setelah respons gagal)
 *      diproses ulang.
 *   2. Tagihan DOKU yang masih PENDING ditanyakan statusnya ke DOKU (webhook hilang) → lunas / ditutup.
 *   3. Sesi lain dari tagihan yang sudah lunas: ikut dibayar → pembayaran ganda + refund; masih menunggu → ditutup.
 *   4. Lunas lewat cek status tapi notifikasinya tidak pernah masuk → [ALERT] URL notifikasi di dashboard DOKU.
 * Tetap jalan walau DOKU_ENABLED=false: tagihan DOKU yang sudah ada diurus sampai selesai (cukup kunci terisi).
 */
class ReconcileDokuPayments extends Command
{
    protected $signature = 'payment:reconcile-doku {--hours=24 : Rentang tagihan yang dicek (jam ke belakang)}';

    protected $description = 'Cek ulang tagihan & notifikasi DOKU yang belum tuntas (cadangan webhook)';

    /** Batas per putaran supaya tidak membanjiri API DOKU. */
    private const MAX_ORDERS_PER_RUN = 100;

    public function handle(DokuReconciliationService $reconciler, DokuNotificationProcessor $processor, DokuClient $client): int
    {
        if (! $client->isConfigured()) {
            $this->line('Kunci DOKU kosong — dilewati.');

            return self::SUCCESS;
        }

        // 1. Notifikasi yang belum tuntas.
        $pending = DokuNotification::query()
            ->whereIn('result', [DokuNotification::RECEIVED, DokuNotification::RETRY, DokuNotification::UNMATCHED])
            ->where('received_at', '>=', now()->subHours(DokuNotification::RETRY_HOURS))
            // "received" yang masih baru sedang diproses setelah respons — jangan diserobot.
            ->where('received_at', '<=', now()->subMinute())
            ->where('attempts', '<', DokuNotification::MAX_ATTEMPTS)
            ->orderBy('id')
            ->limit(200)
            ->pluck('id');
        foreach ($pending as $id) {
            $processor->process((int) $id);
        }

        // 2. Tagihan DOKU yang masih menunggu.
        $since = now()->subHours((int) $this->option('hours'));
        $orders = Order::query()
            ->whereHas('payments', fn ($q) => $q
                ->where('payment_gateway', 'DOKU')
                ->where('status', 'PENDING')
                ->where(fn ($w) => $w->where('created_at', '>=', $since)->orWhere('updated_at', '>=', $since))
                // Beri notifikasi asli kesempatan datang duluan.
                ->where('updated_at', '<=', now()->subMinutes(2)))
            ->limit(self::MAX_ORDERS_PER_RUN)
            ->get();

        $counts = array_fill_keys([DokuReconciliationService::PAID, DokuReconciliationService::PENDING, DokuReconciliationService::NOT_PAID, DokuReconciliationService::ERROR], 0);
        foreach ($orders as $order) {
            $result = $reconciler->reconcileOrder($order);
            $counts[$result]++;
            if ($result === DokuReconciliationService::PAID) {
                $this->info("LUNAS di DOKU, dilunasi: {$order->order_number}");
            }
        }

        $this->line(sprintf(
            'Notifikasi diproses ulang: %d. Dicek %d order — lunas: %d, masih pending: %d, tidak dibayar: %d, gagal cek: %d',
            $pending->count(), $orders->count(),
            $counts[DokuReconciliationService::PAID], $counts[DokuReconciliationService::PENDING],
            $counts[DokuReconciliationService::NOT_PAID], $counts[DokuReconciliationService::ERROR],
        ));

        // 3. Sesi saudara dari tagihan yang sudah lunas.
        $duplicates = app(DokuSessionGuard::class)->sweepPaidSiblings($reconciler);
        if ($duplicates > 0) {
            $this->warn("Pembayaran ganda DOKU baru ditemukan: {$duplicates} (refund PENDING dibuat).");
        }

        // 4. Lunas lewat cek status ≥ 15 menit lalu tanpa notifikasi sama sekali (sekali per invoice).
        Payment::where('payment_gateway', 'DOKU')
            ->where('status', 'SUCCESS')
            ->whereBetween('updated_at', [now()->subHours(DokuSessionGuard::SIBLING_WATCH_HOURS), now()->subMinutes(15)])
            ->get()
            ->filter(fn (Payment $p) => ($p->payload_log['confirmed_via'] ?? null) === 'DOKU_STATUS_API')
            ->each(function (Payment $p) {
                if (! DokuNotification::where('invoice_number', $p->transaction_id)->exists()
                    && Cache::add("doku_missing_notification:{$p->transaction_id}", true, now()->addDays(3))) {
                    DokuClient::alert("DOKU: [{$p->transaction_id}] lunas lewat cek status tapi notifikasinya tidak pernah masuk — cek URL notifikasi di dashboard DOKU (per kanal).");
                }
            });

        return self::SUCCESS;
    }
}
