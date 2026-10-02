<?php

namespace App\Services\Payment;

/**
 * Daftar RESMI metode pembayaran online yang didukung integrasi Midtrans Snap kita, beserta pemetaannya
 * ke `enabled_payments` Snap. Kode di sini tetap (tidak bisa ditambah dari admin) karena metode yang tidak
 * didukung / tidak terpetakan membuat Snap error. Yang bisa diatur admin (aktif, nama tampilan, keterangan,
 * urutan, batas nominal) disimpan di tabel `online_payment_methods` — lihat OnlinePaymentMethodService.
 *
 * Batas nominal default mengikuti ketentuan resmi:
 *   - QRIS: maksimal Rp10.000.000 per transaksi (ketentuan Bank Indonesia).
 *   Metode lain tidak diberi batas default (tidak ada batas resmi yang tetap); admin bisa mengisinya
 *   kalau Midtrans / bank menetapkan.
 */
final class OnlinePaymentCatalog
{
    public const QRIS_MAX_AMOUNT = 10_000_000;

    /**
     * @return array<string, array{label: string, description: string, badge: string, group: string, midtrans: array<int, string>, default_active: bool, default_min: ?int, default_max: ?int}>
     */
    public static function all(): array
    {
        return [
            'QRIS' => [
                'label' => 'QRIS (GoPay / OVO / DANA / ShopeePay / m-Banking)',
                'description' => 'Scan kode QR dari aplikasi e-wallet atau mobile banking apa pun. Konfirmasi otomatis.',
                'badge' => 'QRIS',
                'group' => 'QRIS',
                'midtrans' => ['other_qris', 'gopay', 'shopeepay'],
                'default_active' => true,
                'default_min' => null,
                'default_max' => self::QRIS_MAX_AMOUNT,
            ],
            'BCA_VA' => [
                'label' => 'BCA Virtual Account',
                'description' => 'Transfer ke nomor Virtual Account BCA. Konfirmasi otomatis.',
                'badge' => 'BCA',
                'group' => 'VA',
                'midtrans' => ['bca_va'],
                'default_active' => true,
                'default_min' => null,
                'default_max' => null,
            ],
            'MANDIRI_VA' => [
                'label' => 'Mandiri Virtual Account',
                'description' => 'Bayar lewat Mandiri Bill Payment (Livin\' / ATM Mandiri). Konfirmasi otomatis.',
                'badge' => 'MDR',
                'group' => 'VA',
                'midtrans' => ['echannel'],
                'default_active' => true,
                'default_min' => null,
                'default_max' => null,
            ],
            'BNI_VA' => [
                'label' => 'BNI Virtual Account',
                'description' => 'Transfer ke nomor Virtual Account BNI. Konfirmasi otomatis.',
                'badge' => 'BNI',
                'group' => 'VA',
                'midtrans' => ['bni_va'],
                'default_active' => true,
                'default_min' => null,
                'default_max' => null,
            ],
            'BRI_VA' => [
                'label' => 'BRI Virtual Account',
                'description' => 'Transfer ke nomor Virtual Account BRI (BRIVA). Konfirmasi otomatis.',
                'badge' => 'BRI',
                'group' => 'VA',
                'midtrans' => ['bri_va'],
                'default_active' => true,
                'default_min' => null,
                'default_max' => null,
            ],
            'CIMB_VA' => [
                'label' => 'CIMB Niaga Virtual Account',
                'description' => 'Transfer ke nomor Virtual Account CIMB Niaga. Konfirmasi otomatis.',
                'badge' => 'CIMB',
                'group' => 'VA',
                'midtrans' => ['cimb_va'],
                'default_active' => true,
                'default_min' => null,
                'default_max' => null,
            ],
            // Kode lama "BSI_VA" dipertahankan supaya pembayaran lama tetap terbaca. Snap memetakannya ke
            // Permata VA / VA bank lain (jaringan antarbank) — BSI & bank lain membayar lewat jalur ini,
            // jadi labelnya jujur menyebut itu (dulu tertulis "BSI Virtual Account" padahal yang muncul Permata).
            'BSI_VA' => [
                'label' => 'VA Bank Lain (Permata, BSI, dll.)',
                'description' => 'Transfer antarbank ke Virtual Account Permata — bisa dari BSI dan bank lain.',
                'badge' => 'VA',
                'group' => 'VA',
                'midtrans' => ['permata_va', 'other_va'],
                'default_active' => true,
                'default_min' => null,
                'default_max' => null,
            ],
            'CREDIT_CARD' => [
                'label' => 'Kartu Kredit / Debit Online (Visa, Mastercard, JCB)',
                'description' => 'Bayar dengan kartu berlogo Visa/Mastercard/JCB (3D Secure). Aktifkan hanya jika sudah aktif di dashboard Midtrans.',
                'badge' => 'CARD',
                'group' => 'CARD',
                'midtrans' => ['credit_card'],
                'default_active' => false,
                'default_min' => null,
                'default_max' => null,
            ],
        ];
    }

    public static function has(string $code): bool
    {
        return array_key_exists(strtoupper($code), self::all());
    }

    /** @return array<int, string>|null */
    public static function midtransChannels(string $code): ?array
    {
        return self::all()[strtoupper($code)]['midtrans'] ?? null;
    }

    public static function defaultLabel(string $code): ?string
    {
        return self::all()[strtoupper($code)]['label'] ?? null;
    }
}
