<?php

namespace App\Services\Finance;

use App\Models\Padel\PadelBooking;
use App\Models\Pos\Refund;
use App\Models\Pos\Voucher;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Modul 17 FR-06 — Antrian Refund. Refund PENDING (kelebihan bayar, pembayaran ganda, uang masuk untuk tagihan yang
 * sudah ditutup) diproses atau ditolak dari panel admin. Memproses = PROCESSED → baris buku negatif ditulis oleh hook
 * Refund (di transaksi yang sama). Semua aksi masuk Log Aktivitas KRITIS.
 *
 * Modul 21: pengajuan pembatalan dari Kelola Pemesanan juga masuk ke sini (refund.padel_booking_id). Disetujui →
 * booking REFUNDED; ditolak → booking tetap batal (CANCELLED) dan uangnya jadi voucher saldo atas nama customer.
 */
class RefundQueueService
{
    public const METHODS = [
        'TRANSFER_BANK' => 'Transfer ke rekening customer',
        'VOID_EDC' => 'Void / refund di mesin EDC',
        'ORIGINAL_PAYMENT' => 'Refund ke metode bayar asal (Dashboard Midtrans / QRIS)',
    ];

    public function process(Refund $refund, User $by, string $method, string $reference, ?string $notes = null): Refund
    {
        $this->authorize($by);
        if (! array_key_exists($method, self::METHODS)) {
            throw new HttpException(422, 'Metode pengembalian tidak dikenal.');
        }
        $reference = trim($reference);
        if (mb_strlen($reference) < 3) {
            throw new HttpException(422, 'Nomor referensi pengembalian wajib diisi (no. transfer / void EDC / refund Midtrans).');
        }

        return DB::transaction(function () use ($refund, $by, $method, $reference, $notes) {
            $locked = $this->lockPending($refund);
            $locked->update([
                'status' => 'PROCESSED',
                'refund_method' => $method,
                'refund_reference' => mb_substr($reference, 0, 120),
                'processed_by_id' => $by->id,
                'processed_at' => now(),
                'admin_notes' => filled($notes) ? mb_substr(trim($notes), 0, 1000) : null,
            ]);

            $this->closeBooking($locked, 'REFUNDED');

            ActivityLogger::record(
                module: 'FINANCE',
                event: 'refund.processed',
                description: 'Refund '.ActivityLogger::rupiah((float) $locked->refund_amount).' order '.($locked->order?->order_number ?? '-')
                    .' DIPROSES via '.self::METHODS[$method]." (ref {$reference})",
                subject: $locked->order,
                meta: array_filter([
                    'no_order' => $locked->order?->order_number,
                    'nominal' => (float) $locked->refund_amount,
                    'metode' => $method,
                    'referensi' => $reference,
                    'alasan_refund' => $locked->reason,
                    'catatan' => $locked->admin_notes,
                ]),
                severity: ActivityLogger::CRITICAL,
            );

            return $locked;
        });
    }

    /** Menolak refund. $issueVoucher: uangnya dijadikan voucher saldo customer (default, keputusan PM). */
    public function reject(Refund $refund, User $by, string $reason, bool $issueVoucher = true): Refund
    {
        $this->authorize($by);
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new HttpException(422, 'Alasan penolakan wajib diisi (minimal 5 karakter).');
        }

        return DB::transaction(function () use ($refund, $by, $reason, $issueVoucher) {
            $locked = $this->lockPending($refund);
            $locked->update([
                'status' => 'REJECTED',
                'processed_by_id' => $by->id,
                'processed_at' => now(),
                'admin_notes' => mb_substr($reason, 0, 1000),
            ]);

            $this->closeBooking($locked, 'CANCELLED');
            $voucher = $issueVoucher ? app(VoucherService::class)->issueRefundCredit($locked) : null;

            ActivityLogger::record(
                module: 'FINANCE',
                event: 'refund.rejected',
                description: 'Refund '.ActivityLogger::rupiah((float) $locked->refund_amount).' order '.($locked->order?->order_number ?? '-').' DITOLAK: '.$reason
                    .($voucher ? ' — dijadikan voucher saldo '.$voucher->code : ''),
                subject: $locked->order,
                meta: [
                    'no_order' => $locked->order?->order_number,
                    'nominal' => (float) $locked->refund_amount,
                    'alasan_refund' => $locked->reason,
                    'alasan_tolak' => $reason,
                    'voucher_saldo' => $voucher?->code,
                ],
                severity: ActivityLogger::CRITICAL,
            );

            if ($voucher) {
                $this->notifyVoucher($voucher);
            }

            return $locked->setRelation('voucher', $voucher);
        });
    }

    /** Booking yang refund-nya diajukan dari Kelola Pemesanan ikut ditutup: REFUNDED / CANCELLED. */
    private function closeBooking(Refund $refund, string $status): void
    {
        if (! $refund->padel_booking_id) {
            return;
        }

        PadelBooking::whereKey($refund->padel_booking_id)->where('status', 'REFUND_PENDING')->update(['status' => $status]);
    }

    /** Email ke customer setelah transaksi tersimpan; gagal kirim tidak membatalkan penolakan. */
    private function notifyVoucher(Voucher $voucher): void
    {
        DB::afterCommit(function () use ($voucher) {
            $user = $voucher->user;
            if (! $user || \App\Support\PlaceholderEmail::is($user->email)) {
                return;
            }

            try {
                $user->notify(new \App\Notifications\CreditVoucherIssued($voucher));
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    private function authorize(User $by): void
    {
        if (! $by->can('process_refund_queue')) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki izin [process_refund_queue].');
        }
    }

    /** Kunci baris & pastikan masih PENDING — dua admin menekan "Proses" bersamaan tidak boleh mengembalikan uang dua kali. */
    private function lockPending(Refund $refund): Refund
    {
        $locked = Refund::with('order')->whereKey($refund->getKey())->lockForUpdate()->firstOrFail();
        if ($locked->status !== 'PENDING') {
            throw new HttpException(409, "Refund ini sudah berstatus {$locked->status}. Muat ulang halaman.");
        }

        return $locked;
    }
}
