<?php

namespace App\Services\Pos;

use App\Models\Finance\LedgerEntry;
use App\Models\Pos\Payment;
use App\Models\Pos\PosCashierShift;
use App\Models\Pos\Refund;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Finance\LedgerWriter;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Koreksi metode bayar salah klik kasir (mis. dicatat QRIS padahal kartu debit) — HANYA pemegang izin
 * approve_shift_closing (admin), bukan kasir. Nominal tidak pernah berubah (salah nominal = void / refund).
 *
 *   - Hanya pembayaran manual di meja kasir (CASHIER_POS: QRIS stiker / EDC / transfer) pada shift yang masih OPEN atau
 *     NEEDS_REVIEW. QR / VA otomatis (diverifikasi gateway), pembayaran online, & yang sudah ada refund-nya ditolak.
 *   - Bukti metode yang benar divalidasi seperti di kasir (PosPaymentProof): format & 1 bukti = 1 transaksi.
 *   - Pembayaran: metode & bukti diganti, riwayat koreksi disimpan di payload_log.method_corrections.
 *   - Buku Transaksi: baris TETAP satu — metode/label/bukti diperbarui, metode asli & siapa/kapan disimpan.
 *   - Pencatat transaksi tidak mengoreksi transaksinya sendiri (kecuali super_admin). Log aktivitas KRITIS.
 */
class PaymentMethodCorrection
{
    /** Metode yang benar, sama dengan pilihan bukti bayar kasir. */
    public const METHODS = PosPaymentProof::METHODS;

    public function __construct(protected ShiftClosingService $closing, protected LedgerWriter $ledger) {}

    /** Alasan pembayaran ini tidak bisa dikoreksi (null = bisa). */
    public static function blockedReason(Payment $payment, ?PosCashierShift $shift = null): ?string
    {
        $shift ??= $payment->posShift;
        $log = is_array($payment->payload_log) ? $payment->payload_log : [];

        return match (true) {
            $payment->status !== 'SUCCESS' => 'Hanya pembayaran yang sudah lunas.',
            $payment->payment_gateway !== 'CASHIER_POS' || ! $shift => 'Hanya pembayaran manual di meja kasir (QRIS stiker / EDC / transfer).',
            isset($log['pos_qris']) => 'QR / VA otomatis sudah diverifikasi gateway — metodenya tidak bisa diubah.',
            (float) $payment->amount <= 0 => 'Pembayaran Rp0 (benefit) tidak punya metode bayar.',
            ! ($shift->status === 'OPEN' || $shift->review_status === PosCashierShift::REVIEW_NEEDS_REVIEW) => 'Shift-nya sudah final — koreksi hanya saat shift masih buka atau sedang direview.',
            Refund::where('payment_id', $payment->getKey())->exists() => 'Pembayaran ini sudah punya refund.',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $proof  isian bukti seperti PosPaymentProof::validate (card_type, approval_code, …)
     */
    public function apply(Payment $payment, User $by, string $method, array $proof, string $reason): Payment
    {
        if (! $by->can('approve_shift_closing')) {
            throw new HttpException(403, 'Akses ditolak: koreksi metode bayar hanya untuk pemegang izin [approve_shift_closing].');
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new HttpException(422, 'Alasan koreksi wajib diisi (minimal 5 karakter).');
        }
        $method = strtoupper(trim($method));
        if (! array_key_exists($method, self::METHODS)) {
            throw new HttpException(422, 'Metode bayar yang benar tidak dikenali.');
        }

        return DB::transaction(function () use ($payment, $by, $method, $proof, $reason) {
            // Urutan kunci sama dengan tutup shift: shift dulu, baru pembayaran.
            $shift = $payment->pos_shift_id ? PosCashierShift::whereKey($payment->pos_shift_id)->lockForUpdate()->first() : null;
            $payment = Payment::with('order')->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if ($blocked = self::blockedReason($payment, $shift)) {
                throw new HttpException(422, $blocked);
            }

            $log = is_array($payment->payload_log) ? $payment->payload_log : [];
            $recordedBy = $log['settled_by'] ?? $log['cashier_id'] ?? $payment->order?->cashier_id;
            if ($recordedBy === $by->getKey() && ! $by->hasRole('super_admin')) {
                throw new HttpException(403, 'Transaksi ini Anda catat sendiri sebagai kasir — harus dikoreksi admin lain atau super admin.');
            }

            $newProof = PosPaymentProof::validate($method, $proof, (float) $payment->amount, $payment->getKey());
            $newMethod = match (true) {
                isset($newProof['edc_details']) => ($newProof['edc_details']['card_type'] === 'CREDIT' ? 'CREDIT_CARD' : 'DEBIT_CARD'),
                isset($newProof['qris_details']) => 'QRIS',
                default => 'TRANSFER_BANK',
            };

            $before = $this->ledger->methodSnapshot($payment);
            $oldProof = array_intersect_key($log, array_flip(['qris_details', 'edc_details', 'transfer_details', 'qris_provider']));
            if ($newMethod === strtoupper((string) $payment->payment_method) && $newProof == array_intersect_key($oldProof, $newProof)) {
                throw new HttpException(422, 'Metode & bukti yang diisi sama dengan yang tercatat — tidak ada yang dikoreksi.');
            }

            $corrections = is_array($log['method_corrections'] ?? null) ? $log['method_corrections'] : [];
            $corrections[] = [
                'at' => now()->toIso8601String(),
                'by_id' => $by->getKey(),
                'by_name' => $by->name,
                'reason' => mb_substr($reason, 0, 500),
                'from_method' => $payment->payment_method,
                'from_proof' => $oldProof,
                'to_method' => $newMethod,
            ];

            $log = array_diff_key($log, $oldProof) + $newProof;
            $log['method_corrections'] = $corrections;
            $payment->update(['payment_method' => $newMethod, 'payload_log' => $log]);

            $after = $this->ledger->methodSnapshot($payment);
            $entries = LedgerEntry::where('payment_id', $payment->getKey())
                ->whereIn('entry_type', [LedgerEntry::TYPE_PAYMENT, LedgerEntry::TYPE_OVERPAYMENT])
                ->get();
            LedgerEntry::correctPaymentMethod($entries, $after, $by);

            if ($shift && $shift->status === 'CLOSED') {
                $this->closing->recompute($shift);
            }

            ActivityLogger::record(
                module: 'FINANCE',
                event: 'payment.method_corrected',
                description: 'Mengoreksi metode bayar '.ActivityLogger::rupiah((float) $payment->amount).' order '.($payment->order?->order_number ?? '-')
                    .': '.$before['payment_method_label'].' → '.$after['payment_method_label'].' ('.$reason.')',
                subject: $payment->order,
                meta: array_filter([
                    'no_order' => $payment->order?->order_number,
                    'shift' => $shift?->shift_number,
                    'nominal' => (float) $payment->amount,
                    'dari' => $before['payment_method_label'].($before['payment_reference'] ? ' · '.$before['payment_reference'] : ''),
                    'menjadi' => $after['payment_method_label'].($after['payment_reference'] ? ' · '.$after['payment_reference'] : ''),
                    'alasan' => $reason,
                    'baris_buku' => $entries->count(),
                ], fn ($v) => $v !== null),
                severity: ActivityLogger::CRITICAL,
            );

            return $payment;
        });
    }
}
