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
        'TRANSFER_BANK' => 'Transfer Bank',
    ];

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

        if (in_array($method, ['EDC_BCA', 'EDC_MANDIRI'], true)) {
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
        $query = Payment::query()->where('status', 'SUCCESS')->where($jsonPath, $value);
        foreach ($alsoMatch as $path => $expected) {
            $query->where($path, $expected);
        }

        if ($query->exists()) {
            throw new HttpException(422, $message);
        }
    }
}
