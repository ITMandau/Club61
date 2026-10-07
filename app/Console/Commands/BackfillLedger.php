<?php

namespace App\Console\Commands;

use App\Models\Finance\LedgerEntry;
use App\Models\Pos\Payment;
use App\Models\Pos\Refund;
use App\Services\Finance\LedgerWriter;
use App\Services\Payment\PaymentOrchestratorService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Modul 17 FR-08: isi Buku Transaksi dari pembayaran & refund yang terjadi sebelum buku ada. Aman dijalankan ulang —
 * pembayaran / refund yang sudah tercatat dilewati (LedgerWriter idempoten + unique dedupe_key).
 * Komposisi diambil dari order SAAT INI dikurangi seluruh tagihan selisih reschedule (sama dengan struk penjualan).
 */
class BackfillLedger extends Command
{
    protected $signature = 'ledger:backfill
        {--from= : Hanya transaksi sejak tanggal ini (Y-m-d, waktu Jakarta)}
        {--dry-run : Hitung saja, tidak menulis apa pun}';

    protected $description = 'Isi Buku Transaksi dari pembayaran SUCCESS & refund PROCESSED lama';

    public function handle(LedgerWriter $writer): int
    {
        $from = $this->option('from')
            ? Carbon::parse($this->option('from'), 'Asia/Jakarta')->startOfDay()->setTimezone(config('app.timezone'))
            : null;
        $dryRun = (bool) $this->option('dry-run');

        $stats = ['payments' => 0, 'payments_skipped' => 0, 'refunds' => 0, 'refunds_skipped' => 0, 'failed' => 0];

        Payment::query()
            ->whereIn('status', LedgerWriter::MONEY_IN_STATUSES)
            ->when($from, fn ($q) => $q->where('paid_at', '>=', $from))
            ->lazyById(200)
            ->each(function (Payment $payment) use ($writer, $dryRun, &$stats) {
                $recorded = LedgerEntry::where('payment_id', $payment->id)
                    ->whereIn('entry_type', [LedgerEntry::TYPE_PAYMENT, LedgerEntry::TYPE_OVERPAYMENT])->exists();
                if ($recorded || ! LedgerWriter::isCountable($payment)) {
                    $stats['payments_skipped']++;

                    return;
                }

                if ($dryRun) {
                    $stats['payments']++;

                    return;
                }

                try {
                    DB::transaction(fn () => $writer->recordPayment($payment, $this->entryTypeFor($payment)));
                    $stats['payments']++;
                } catch (\Throwable $e) {
                    $stats['failed']++;
                    $this->error("Pembayaran {$payment->transaction_id}: {$e->getMessage()}");
                }
            });

        Refund::query()
            ->where('status', 'PROCESSED')
            ->when($from, fn ($q) => $q->where('processed_at', '>=', $from))
            ->lazyById(200)
            ->each(function (Refund $refund) use ($writer, $dryRun, &$stats) {
                if (LedgerEntry::where('refund_id', $refund->id)->exists()) {
                    $stats['refunds_skipped']++;

                    return;
                }

                if ($dryRun) {
                    $stats['refunds']++;

                    return;
                }

                try {
                    DB::transaction(fn () => $writer->recordRefund($refund));
                    $stats['refunds']++;
                } catch (\Throwable $e) {
                    $stats['failed']++;
                    $this->error("Refund {$refund->id}: {$e->getMessage()}");
                }
            });

        $this->table(['', 'Jumlah'], [
            [$dryRun ? 'Pembayaran akan dicatat' : 'Pembayaran dicatat', $stats['payments']],
            ['Pembayaran dilewati (sudah tercatat / simulasi / legacy)', $stats['payments_skipped']],
            [$dryRun ? 'Refund akan dicatat' : 'Refund dicatat', $stats['refunds']],
            ['Refund dilewati (sudah tercatat)', $stats['refunds_skipped']],
            ['Gagal', $stats['failed']],
        ]);

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** Pembayaran lama yang seluruh nominalnya masuk setelah tagihan lunas / ditutup = kelebihan bayar. */
    private function entryTypeFor(Payment $payment): string
    {
        $log = is_array($payment->payload_log) ? $payment->payload_log : [];
        if ($payment->status === PaymentOrchestratorService::DUPLICATE_STATUS || ! empty($log['received_after_bill_closed'])) {
            return LedgerEntry::TYPE_OVERPAYMENT;
        }

        $fullRefundForExtraMoney = Refund::where('payment_id', $payment->id)
            ->where('refund_amount', '>=', (float) $payment->amount - 1)
            ->where(fn ($q) => $q->where('reason', 'like', 'Late payment settlement on cancelled order%')
                ->orWhere('reason', 'like', 'Kelebihan bayar%'))
            ->exists();

        return $fullRefundForExtraMoney ? LedgerEntry::TYPE_OVERPAYMENT : LedgerEntry::TYPE_PAYMENT;
    }
}
