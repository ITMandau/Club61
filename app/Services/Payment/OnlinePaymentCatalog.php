<?php

namespace App\Services\Payment;

/**
 * Daftar RESMI metode pembayaran online bawaan beserta pemetaannya ke kanal tiap gateway (`enabled_payments` Midtrans
 * Snap, `payment_method_types` DOKU Checkout). Kode metode bawaan tetap (tidak bisa diubah / dihapus dari admin) supaya
 * transaksi lama tetap terbaca. Admin bisa MENAMBAH metode (jenis QRIS / VA) yang kanalnya dipilih dari daftar kode
 * resmi di bawah (MIDTRANS_CHANNELS / DOKU_CHANNELS) — tidak bisa mengetik kode bebas, karena kode yang tidak dikenal
 * gateway membuat pembayaran error. Pengaturan (aktif, nama tampilan, keterangan, urutan, batas nominal) disimpan di
 * tabel `online_payment_methods` — lihat OnlinePaymentMethodService.
 *
 * Batas nominal default mengikuti ketentuan resmi:
 *   - QRIS: maksimal Rp10.000.000 per transaksi (ketentuan Bank Indonesia).
 *   Metode lain tidak diberi batas default (tidak ada batas resmi yang tetap); admin bisa mengisinya
 *   kalau Midtrans / bank menetapkan.
 */
final class OnlinePaymentCatalog
{
    public const QRIS_MAX_AMOUNT = 10_000_000;

    /** Jenis metode yang boleh DITAMBAH dari admin. Kartu & e-wallet langsung tidak bisa dibatalkan (Modul 27 §8 no. 8). */
    public const ADDABLE_KINDS = ['QRIS' => 'QRIS', 'VA' => 'Virtual Account'];

    /**
     * Kode `enabled_payments` Midtrans Snap yang boleh dipilih admin (dokumentasi resmi "Request Body (JSON Parameter)",
     * dicek 15 Okt 2026). E-wallet langsung (ovo, dana) tidak dimasukkan — hanya QRIS & VA.
     */
    public const MIDTRANS_CHANNELS = [
        'QRIS' => [
            'other_qris' => 'QRIS (semua e-wallet & m-banking)',
            'gopay' => 'GoPay (QR)',
            'shopeepay' => 'ShopeePay (QR)',
        ],
        'VA' => [
            'bca_va' => 'BCA VA',
            'echannel' => 'Mandiri Bill Payment',
            'bni_va' => 'BNI VA',
            'bri_va' => 'BRI VA',
            'cimb_va' => 'CIMB Niaga VA',
            'permata_va' => 'Permata VA',
            'danamon_va' => 'Danamon VA',
            'bsi_va' => 'BSI VA',
            'seabank_va' => 'SeaBank VA',
            'saqu_va' => 'Saqu VA',
        ],
    ];

    /**
     * Kode `payment_method_types` DOKU Checkout yang boleh dipilih admin (dokumentasi resmi "Supported Payment Methods",
     * dicek 15 Okt 2026). Ditulis persis seperti di dokumentasi (termasuk huruf kecil di `VIRTUAL_ACCOUNT_Sinarmas`).
     * TIDAK dimasukkan: VA BTN, BNC & BPD (mis. BJB) — Cancel Order API DOKU tidak mendukungnya, jadi sesi lama tidak
     * bisa ditutup saat customer ganti metode (risiko dibayar dua kali).
     */
    public const DOKU_CHANNELS = [
        'QRIS' => [
            'QRIS' => 'QRIS',
        ],
        'VA' => [
            'VIRTUAL_ACCOUNT_BCA' => 'BCA VA',
            'VIRTUAL_ACCOUNT_BANK_MANDIRI' => 'Mandiri VA',
            'VIRTUAL_ACCOUNT_BANK_SYARIAH_MANDIRI' => 'BSI VA',
            'VIRTUAL_ACCOUNT_BRI' => 'BRI VA',
            'VIRTUAL_ACCOUNT_BNI' => 'BNI VA',
            'VIRTUAL_ACCOUNT_DOKU' => 'DOKU VA',
            'VIRTUAL_ACCOUNT_BANK_PERMATA' => 'Permata VA',
            'VIRTUAL_ACCOUNT_BANK_CIMB' => 'CIMB Niaga VA',
            'VIRTUAL_ACCOUNT_BANK_DANAMON' => 'Danamon VA',
            'VIRTUAL_ACCOUNT_BSS' => 'BSS VA',
            'VIRTUAL_ACCOUNT_Sinarmas' => 'Sinarmas VA',
        ],
    ];

    /**
     * @return array<string, array{label: string, description: string, badge: string, group: string, midtrans: array<int, string>, doku: array<int, string>, default_active: bool, default_min: ?int, default_max: ?int}>
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
                'doku' => ['QRIS'],
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
                'doku' => ['VIRTUAL_ACCOUNT_BCA'],
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
                'doku' => ['VIRTUAL_ACCOUNT_BANK_MANDIRI'],
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
                'doku' => ['VIRTUAL_ACCOUNT_BNI'],
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
                'doku' => ['VIRTUAL_ACCOUNT_BRI'],
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
                'doku' => ['VIRTUAL_ACCOUNT_BANK_CIMB'],
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
                'doku' => ['VIRTUAL_ACCOUNT_BANK_PERMATA', 'VIRTUAL_ACCOUNT_BANK_SYARIAH_MANDIRI'],
                'default_active' => true,
                'default_min' => null,
                'default_max' => null,
            ],
            // Tidak dipetakan ke DOKU: kartu tidak dipakai sampai ada pengaman kunci ganti metode (Modul 27 §8 no. 8).
            'CREDIT_CARD' => [
                'label' => 'Kartu Kredit / Debit Online (Visa, Mastercard, JCB)',
                'description' => 'Bayar dengan kartu berlogo Visa/Mastercard/JCB (3D Secure). Aktifkan hanya jika sudah aktif di dashboard Midtrans.',
                'badge' => 'CARD',
                'group' => 'CARD',
                'midtrans' => ['credit_card'],
                'doku' => [],
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

    /** @return array<int, string>|null */
    public static function dokuChannels(string $code): ?array
    {
        return self::all()[strtoupper($code)]['doku'] ?? null;
    }

    public static function defaultLabel(string $code): ?string
    {
        return self::all()[strtoupper($code)]['label'] ?? null;
    }

    /**
     * Kode kanal resmi yang boleh dipilih admin untuk satu gateway & jenis metode.
     *
     * @return array<string, string> kode => nama
     */
    public static function officialChannels(string $gateway, string $kind): array
    {
        $list = match (strtoupper($gateway)) {
            'MIDTRANS' => self::MIDTRANS_CHANNELS,
            'DOKU' => self::DOKU_CHANNELS,
            default => [],
        };

        return $list[strtoupper($kind)] ?? [];
    }
}
