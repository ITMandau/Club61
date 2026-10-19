<?php

namespace App\Services\Payment\Doku;

use App\Models\Payment\DokuNotification;
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
            if (! $notification || in_array($notification->result, [DokuNotification::PROCESSED, DokuNotification::IGNORED, DokuNotification::REJECTED], true)) {
                return;
            }

            $notification->increment('attempts');

            try {
                [$result, $note] = $this->handle($notification);
            } catch (\Throwable $e) {
                report($e);
                [$result, $note] = [DokuNotification::RETRY, 'Error: '.mb_substr($e->getMessage(), 0, 200)];
            }

            $notification->update([
                'result' => $result,
                'result_note' => $note !== null ? mb_substr($note, 0, 255) : null,
                'processed_at' => in_array($result, [DokuNotification::RETRY, DokuNotification::UNMATCHED], true) ? null : now(),
            ]);

            Log::channel('doku')->info("Notifikasi DOKU #{$notification->id} [{$notification->invoice_number}] {$notification->status} → {$result}".($note ? " ({$note})" : ''));
        } finally {
            $lock->release();
        }
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
