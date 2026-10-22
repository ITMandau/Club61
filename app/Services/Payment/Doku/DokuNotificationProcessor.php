<?php

namespace App\Services\Payment\Doku;

use App\Models\Payment\DokuNotification;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Memproses notifikasi DOKU yang SUDAH lolos verifikasi tanda tangan & tersimpan (DokuNotificationController).
 * Dipanggil setelah respons 200 terkirim, dan diulang oleh `payment:reconcile-doku` untuk hasil RETRY / UNMATCHED.
 *
 *   SUCCESS → dikonfirmasi ulang ke API status DOKU + invoice & nominal dicocokkan dengan sesi kita → lunas (idempoten).
 *   EXPIRED → sesi ditandai mati (status tidak pernah mundur: sesi lunas / tercatat ganda tidak diubah).
 *   lainnya (FAILED, PENDING, …) → diabaikan; pembayaran masih bisa terjadi sampai kedaluwarsa.
 */
class DokuNotificationProcessor
{
    public function __construct(
        protected DokuCheckoutService $checkout,
        protected DokuReconciliationService $reconciler,
        protected DokuSessionGuard $guard,
    ) {}

    public function process(int $notificationId): void
    {
        // Satu pemroses per notifikasi (pemrosesan setelah respons vs rekonsiliasi berkala).
        $lock = Cache::lock("doku_notification:{$notificationId}", 120);
        if (! $lock->get()) {
            return;
        }

        try {
            $notification = DokuNotification::find($notificationId);
            if (! $notification || DokuNotification::isFinal($notification->result)) {
                return;
            }

            $notification->increment('attempts');

            try {
                [$result, $note] = $this->handle($notification);
            } catch (\Throwable $e) {
                report($e);
                [$result, $note] = [DokuNotification::RETRY, 'Error: '.mb_substr($e->getMessage(), 0, 200)];
            }

            $exhausted = in_array($result, [DokuNotification::RETRY, DokuNotification::UNMATCHED], true)
                && $notification->attempts >= DokuNotification::MAX_ATTEMPTS;
            if ($exhausted) {
                [$result, $note] = [DokuNotification::EXHAUSTED, "Habis setelah {$notification->attempts} percobaan: {$note}"];
            }

            $notification->update([
                'result' => $result,
                'result_note' => $note !== null ? mb_substr($note, 0, 255) : null,
                'processed_at' => in_array($result, [DokuNotification::RETRY, DokuNotification::UNMATCHED], true) ? null : now(),
            ]);

            Log::channel('doku')->info("Notifikasi DOKU #{$notification->id} [{$notification->invoice_number}] {$notification->status} → {$result}".($note ? " ({$note})" : ''));

            if ($exhausted) {
                $this->reportExhausted($notification);
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Notifikasi yang tidak tuntas lalu keluar dari jendela proses ulang (lebih dari RETRY_HOURS jam, mis. scheduler
     * sempat mati) atau sudah mencapai batas percobaan sebelum aturan ini ada → ditandai EXHAUSTED + dilaporkan sekali.
     *
     * @return int jumlah notifikasi yang dikeluarkan dari antrean
     */
    public function exhaustStale(int $limit = 50): int
    {
        $ids = DokuNotification::query()
            ->whereNotIn('result', DokuNotification::FINAL_RESULTS)
            ->where(fn ($q) => $q->where('received_at', '<', now()->subHours(DokuNotification::RETRY_HOURS))
                ->orWhere('attempts', '>=', DokuNotification::MAX_ATTEMPTS))
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $count = 0;
        foreach ($ids as $id) {
            $lock = Cache::lock("doku_notification:{$id}", 120);
            if (! $lock->get()) {
                continue;
            }

            try {
                // Bersyarat: hanya yang masih belum final (sekali saja walau dua rekonsiliasi berjalan bersamaan).
                $updated = DokuNotification::whereKey($id)->whereNotIn('result', DokuNotification::FINAL_RESULTS)->update([
                    'result' => DokuNotification::EXHAUSTED,
                    'result_note' => 'Tidak tuntas dalam '.DokuNotification::RETRY_HOURS.' jam / '.DokuNotification::MAX_ATTEMPTS.' percobaan',
                    'processed_at' => now(),
                ]);
                if ($updated === 1) {
                    $this->reportExhausted(DokuNotification::find($id));
                    $count++;
                }
            } finally {
                $lock->release();
            }
        }

        return $count;
    }

    /**
     * Notifikasi "SUCCESS" yang tidak pernah tuntas = uang mungkin sudah masuk tanpa tercatat → [ALERT] + log aktivitas
     * KRITIS (sama seriusnya dengan Midtrans). Status lain cukup peringatan.
     */
    private function reportExhausted(?DokuNotification $notification): void
    {
        if (! $notification) {
            return;
        }

        $invoice = (string) $notification->invoice_number;
        $paid = strtoupper((string) $notification->status) === 'SUCCESS';
        $amount = $notification->amount !== null ? ActivityLogger::rupiah((float) $notification->amount) : '-';
        $reason = (string) $notification->result_note;

        if ($paid) {
            DokuClient::alert("Notifikasi DOKU LUNAS [{$invoice}] {$amount} tidak bisa diproses otomatis ({$reason}) — cek manual di dashboard DOKU: lunasi / refund.", [
                'notifikasi_id' => $notification->id,
                'percobaan' => $notification->attempts,
            ]);
        } else {
            Log::channel('doku')->warning("Notifikasi DOKU {$notification->status} [{$invoice}] dikeluarkan dari antrean ({$reason}).");
        }

        try {
            $order = $invoice !== '' ? DokuSessionGuard::findBill($invoice)?->order : null;
        } catch (\Throwable $e) {
            report($e);
            $order = null;
        }

        ActivityLogger::record(
            module: 'FINANCE',
            event: 'payment.doku_notification_unresolved',
            description: $paid
                ? "Notifikasi DOKU LUNAS {$invoice} ({$amount}) tidak bisa diproses otomatis — cek manual di dashboard DOKU"
                : "Notifikasi DOKU {$notification->status} {$invoice} tidak tuntas diproses — dikeluarkan dari antrean",
            subject: $order,
            meta: [
                'invoice' => $invoice,
                'status_doku' => $notification->status,
                'nominal' => $notification->amount !== null ? (float) $notification->amount : null,
                'percobaan' => $notification->attempts,
                'alasan' => $reason,
            ],
            severity: $paid ? ActivityLogger::CRITICAL : ActivityLogger::WARNING,
            asSystem: true,
        );
    }

    /** @return array{0: string, 1: ?string} */
    private function handle(DokuNotification $notification): array
    {
        $invoice = (string) $notification->invoice_number;
        if ($invoice === '') {
            return [DokuNotification::IGNORED, 'Tanpa invoice_number'];
        }

        return match (strtoupper((string) $notification->status)) {
            'SUCCESS' => $this->handleSuccess($notification, $invoice),
            'EXPIRED' => $this->handleExpired($invoice),
            default => [DokuNotification::IGNORED, 'Status '.($notification->status ?: '-').' bukan status akhir'],
        };
    }

    private function handleSuccess(DokuNotification $notification, string $invoice): array
    {
        $bill = DokuSessionGuard::findBill($invoice);
        if (! $bill || ! $bill->order) {
            // Sesi belum tersimpan (customer membayar sangat cepat) / bukan invoice kita: dicoba lagi; rekonsiliasi
            // status tetap menangkap pembayarannya begitu sesinya tersimpan.
            return [DokuNotification::UNMATCHED, 'Tagihan pemilik invoice belum ditemukan'];
        }

        $session = DokuSessionGuard::session($bill, $invoice) ?? [];
        if ($notification->amount === null || abs((float) $notification->amount - (float) ($session['amount'] ?? -1)) > 1) {
            DokuClient::alert("Notifikasi DOKU: nominal tidak cocok dengan tagihan [{$invoice}] — TIDAK dilunasi otomatis, cek manual.", [
                'nominal_notifikasi' => $notification->amount,
                'nominal_tagihan' => $session['amount'] ?? null,
            ]);

            return [DokuNotification::REJECTED, 'Nominal tidak cocok'];
        }

        // Notifikasi bertanda tangan pun dikonfirmasi ke API status (sama dengan Midtrans): yang dipakai data DOKU terbaru.
        $state = $this->checkout->state($invoice, DokuSessionGuard::expiresAt($session));

        return match ($state['state']) {
            DokuCheckoutService::PAID => $this->reconciler->amountMatches($state['data'], $session, $invoice)
                ? $this->settled($bill->order, $invoice, $state['data'])
                : [DokuNotification::REJECTED, 'Nominal status DOKU tidak cocok'],
            // API status bisa belum sinkron sesaat setelah bayar (DOKU: cek status ≥ 60 detik setelah bayar).
            DokuCheckoutService::PENDING, DokuCheckoutService::ERROR, DokuCheckoutService::NOT_FOUND => [DokuNotification::RETRY, "Status DOKU masih {$state['state']}"],
            default => $this->contradicted($invoice, $state['state']),
        };
    }

    private function settled($order, string $invoice, ?array $data): array
    {
        $this->reconciler->settle($order, $invoice, $data ?? [], 'DOKU_NOTIFICATION');

        return [DokuNotification::PROCESSED, null];
    }

    private function contradicted(string $invoice, string $state): array
    {
        DokuClient::alert("Notifikasi DOKU 'SUCCESS' DITOLAK: API status DOKU menyatakan {$state} untuk [{$invoice}] — cek manual.");

        return [DokuNotification::IGNORED, "API status: {$state}"];
    }

    private function handleExpired(string $invoice): array
    {
        $this->guard->markExpired($invoice);

        return [DokuNotification::PROCESSED, 'Sesi ditandai kedaluwarsa'];
    }
}
