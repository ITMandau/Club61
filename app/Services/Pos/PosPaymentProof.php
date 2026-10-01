<?php

namespace App\Services\Pos;

use App\Models\Pos\Payment;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Bukti bayar WAJIB untuk setiap pelunasan di meja kasir (selisih reschedule, settle tagihan).
 * Tanpa ini, tombol "Lunasi" cuma mencatat LUNAS tanpa pernah ada uang yang terverifikasi.
 *
 * Format payload sengaja identik dengan POS Walk-In (qris_details / edc_details) supaya rekap
 * settlement tutup shift (PosCashierShift::settlementBreakdown) membacanya dengan cara yang sama.
 * Divalidasi di SERVER — UI hanya membantu, request hasil rekayasa tetap ditolak di sini.
 */
class PosPaymentProof
{
    public const METHODS = [
        'QRIS' => 'QRIS Frontdesk',
        'EDC_BCA' => 'Mesin EDC BCA',
        'EDC_MANDIRI' => 'Mesin EDC Mandiri',
        'EDC_LAINNYA' => 'Mesin EDC Lainnya',
        'TRANSFER_BANK' => 'Transfer Bank',
    ];

    /**
     * Terjemahkan isian form pembayaran POS Walk-In (pos.partials.payment-method-form) ke format
     * validate(). Dipakai untuk pelunasan tagihan di kasir supaya kasir memakai layar bayar yang sama.
     *
     * @return array{0: string, 1: array<string, mixed>} [metode, input bukti]
     */
    public static function fromPosForm(
        string $paymentMethod,
        string $edcTerminal,
        string $edcCardType,
        string $edcLast4,
        string $edcApprovalCode,
        string $edcTraceNumber,
        string $qrisProvider,
        string $qrisRrn,
        string $qrisSenderName,
        string $edcCardNetwork = '',
        string $edcBank = '',
    ): array {
        $method = strtoupper($paymentMethod);

        if (in_array($method, ['QRIS', 'QRIS_STATIS'], true)) {
            return ['QRIS', ['qris_provider' => $qrisProvider, 'qris_rrn' => $qrisRrn, 'qris_sender_name' => $qrisSenderName]];
        }

        if (in_array($method, ['DEBIT_CARD', 'DEBIT', 'CREDIT_CARD', 'CREDIT', 'EDC_BCA', 'EDC_MANDIRI'], true)) {
            $terminal = in_array(strtoupper($edcTerminal), ['EDC_BCA', 'EDC_MANDIRI', 'EDC_LAINNYA'], true) ? strtoupper($edcTerminal) : 'EDC_BCA';
            $cardType = str_contains($method, 'CREDIT') || strtoupper($edcCardType) === 'CREDIT' ? 'CREDIT' : 'DEBIT';

            return [$terminal, [
                'card_type' => $cardType,
                'card_last_4' => $edcLast4,
                'approval_code' => $edcApprovalCode,
                'trace_number' => $edcTraceNumber,
                'card_network' => $edcCardNetwork,
                'card_issuer' => $edcBank,
            ]];
        }

        return [$method, []]; // ditolak validate() sebagai metode tidak dikenal
    }

    public const QRIS_PROVIDERS = [
        'BCA_QRIS' => 'QRIS BCA Frontdesk',
        'MANDIRI_QRIS' => 'QRIS Bank Mandiri',
        'GOPAY_QRIS' => 'GoPay / Midtrans QRIS',
        'OVO' => 'OVO',
        'SHOPEEPAY' => 'ShopeePay',
        'DANA' => 'DANA',
        'LIVIN' => 'Livin Mandiri',
        'LAINNYA' => 'QRIS Lainnya / Bank Lain',
    ];

    /** Nilai lama dari form POS (pos.partials.payment-method-form) → kode resmi. */
    private const PROVIDER_ALIASES = ['GOPAY' => 'GOPAY_QRIS'];

    /**
     * Cek pemakaian ulang bukti bayar yang SUDAH tersusun (qris_details / edc_details / transfer_details) — untuk
     * jalur yang menyusun payload sendiri (checkout walk-in). Dulu walk-in tidak pernah dicek sama sekali, jadi satu
     * RRN/approval code bisa dipakai berkali-kali. Nilai dinormalisasi ke huruf besar (by reference) supaya
     * pencocokan konsisten dengan jalur pelunasan.
     */
    public static function assertProofNotReused(array &$payloadLog): void
    {
        if (! empty($payloadLog['qris_details']['rrn'])) {
            $rrn = strtoupper(trim((string) $payloadLog['qris_details']['rrn']));
            $payloadLog['qris_details']['rrn'] = $rrn;
            self::assertNotUsedBefore('payload_log->qris_details->rrn', $rrn, "RRN QRIS {$rrn} sudah pernah dipakai di transaksi lain. Periksa ulang bukti bayar customer.");
        }

        if (! empty($payloadLog['edc_details']['approval_code'])) {
            $approval = strtoupper(trim((string) $payloadLog['edc_details']['approval_code']));
            $trace = strtoupper(trim((string) ($payloadLog['edc_details']['trace_number'] ?? '')));
            $payloadLog['edc_details']['approval_code'] = $approval;
            $payloadLog['edc_details']['trace_number'] = $trace;
            self::assertNotUsedBefore('payload_log->edc_details->approval_code', $approval, "Approval code {$approval} sudah pernah dipakai di transaksi lain. Periksa ulang slip EDC.", ['payload_log->edc_details->trace_number' => $trace]);
        }

        if (! empty($payloadLog['transfer_details']['reference'])) {
            $reference = strtoupper(trim((string) $payloadLog['transfer_details']['reference']));
            $payloadLog['transfer_details']['reference'] = $reference;
            self::assertNotUsedBefore('payload_log->transfer_details->reference', $reference, "Nomor referensi transfer {$reference} sudah pernah dipakai di transaksi lain.");
        }
    }

    /**
     * @param  array<string, mixed>  $input  qris_provider, qris_rrn, qris_sender_name | card_type,
     *                                       card_last_4, approval_code, trace_number | transfer_bank,
     *                                       transfer_reference, transfer_sender_name
     * @return array<string, mixed> potongan payload_log yang siap digabung
     *
     * @throws HttpException 422
     */
    public static function validate(string $method, array $input, float $amount): array
    {
        $method = strtoupper(trim($method));
        $text = fn (string $key) => trim((string) ($input[$key] ?? ''));

        if (! array_key_exists($method, self::METHODS)) {
            throw new HttpException(422, 'Metode pembayaran tidak dikenali. Pilih QRIS, EDC BCA, EDC Mandiri, atau Transfer Bank (venue 100% cashless).');
        }

        if ($method === 'QRIS') {
            $provider = strtoupper($text('qris_provider')) ?: 'BCA_QRIS';
            $provider = self::PROVIDER_ALIASES[$provider] ?? $provider;
            $rrn = strtoupper($text('qris_rrn'));

            if (! array_key_exists($provider, self::QRIS_PROVIDERS)) {
                throw new HttpException(422, 'Penyedia QRIS tidak dikenali.');
            }
            if (! preg_match('/^[A-Z0-9]{6,32}$/', $rrn)) {
                throw new HttpException(422, 'Nomor RRN QRIS wajib diisi (minimal 6 karakter huruf/angka) dari bukti bayar customer.');
            }
            self::assertNotUsedBefore('payload_log->qris_details->rrn', $rrn, "RRN QRIS {$rrn} sudah pernah dipakai di transaksi lain. Periksa ulang bukti bayar customer.");

            return ['qris_details' => [
                'provider' => $provider,
                'rrn' => $rrn,
                'sender_name' => $text('qris_sender_name') ?: null,
            ]];
        }

        if (in_array($method, ['EDC_BCA', 'EDC_MANDIRI', 'EDC_LAINNYA'], true)) {
            $last4 = $text('card_last_4');
            $approval = strtoupper($text('approval_code'));
            $trace = strtoupper($text('trace_number'));
            $cardType = strtoupper($text('card_type')) === 'CREDIT' ? 'CREDIT' : 'DEBIT';

            if (! preg_match('/^[0-9]{4}$/', $last4)) {
                throw new HttpException(422, 'Masukkan tepat 4 digit terakhir kartu customer.');
            }
            if (! preg_match('/^[A-Z0-9]{3,12}$/', $approval)) {
                throw new HttpException(422, 'Approval code dari slip EDC wajib diisi (3-12 karakter huruf/angka).');
            }
            if (! preg_match('/^[A-Z0-9]{3,12}$/', $trace)) {
                throw new HttpException(422, 'Trace number dari slip EDC wajib diisi (3-12 karakter huruf/angka).');
            }
            self::assertNotUsedBefore('payload_log->edc_details->approval_code', $approval, "Approval code {$approval} sudah pernah dipakai di transaksi lain. Periksa ulang slip EDC.", ['payload_log->edc_details->trace_number' => $trace]);

            return ['edc_details' => [
                'terminal' => $method,
                'card_type' => $cardType,
                'card_network' => $text('card_network') ?: null,
                'card_issuer' => $text('card_issuer') ?: null,
                'card_last_4' => $last4,
                'approval_code' => $approval,
                'trace_number' => $trace,
                'charged_amount' => $amount,
            ]];
        }

        $bank = strtoupper($text('transfer_bank'));
        $reference = strtoupper($text('transfer_reference'));

        if ($bank === '') {
            throw new HttpException(422, 'Bank tujuan / pengirim transfer wajib diisi.');
        }
        if (! preg_match('/^[A-Z0-9\-\/]{4,40}$/', $reference)) {
            throw new HttpException(422, 'Nomor referensi transfer wajib diisi (minimal 4 karakter) dari bukti transfer.');
        }
        self::assertNotUsedBefore('payload_log->transfer_details->reference', $reference, "Nomor referensi transfer {$reference} sudah pernah dipakai di transaksi lain.");

        return ['transfer_details' => [
            'bank' => $bank,
            'reference' => $reference,
            'sender_name' => $text('transfer_sender_name') ?: null,
        ]];
    }

    /** Satu bukti bayar hanya boleh melunasi satu transaksi (cegah 1 struk dipakai berulang). */
    private static function assertNotUsedBefore(string $jsonPath, string $value, string $message, array $alsoMatch = []): void
    {
        // Data lama walk-in menyimpan nilai apa adanya (bisa huruf kecil) dan membership lama memakai key qris_rrn.
        $paths = [$jsonPath];
        if ($jsonPath === 'payload_log->qris_details->rrn') {
            $paths[] = 'payload_log->qris_details->qris_rrn';
        }
        $variants = array_values(array_unique([$value, strtolower($value)]));

        $query = Payment::query()->where('status', 'SUCCESS')
            ->where(function ($q) use ($paths, $variants) {
                foreach ($paths as $path) {
                    $q->orWhereIn($path, $variants);
                }
            });
        foreach ($alsoMatch as $path => $expected) {
            $query->whereIn($path, array_values(array_unique([$expected, strtolower($expected)])));
        }

        if ($query->exists()) {
            throw new HttpException(422, $message);
        }

        // Dua kasir memasukkan bukti yang sama di detik yang sama: cek di atas belum melihat transaksi yang belum
        // commit. Kunci singkat per bukti — dilepas setelah commit, atau kedaluwarsa sendiri kalau transaksi gagal.
        $lock = \Illuminate\Support\Facades\Cache::lock('pos_proof:'.md5($jsonPath.'|'.strtoupper($value)), 10);
        if (! $lock->get()) {
            throw new HttpException(409, 'Bukti bayar yang sama sedang diproses di transaksi lain. Periksa ulang bukti bayar customer, atau coba lagi 10 detik lagi.');
        }
        \Illuminate\Support\Facades\DB::afterCommit(fn () => $lock->release());
    }
}
