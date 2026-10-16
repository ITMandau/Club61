<?php

namespace App\Services\Payment;

/**
 * Data customer yang dikirim ke gateway (Midtrans & DOKU memakai perapi yang sama). Nama di database tidak diubah —
 * yang dirapikan hanya salinan yang dikirim, supaya data lama (mis. "Andi Wijaya (Customer)", "Muh. Irham") tidak
 * membuat tagihan ditolak. DOKU menolak nama selain huruf ("Invalid customerName Format", sandbox 15 Okt 2026).
 */
class GatewayCustomer
{
    public const FALLBACK_NAME = 'Pelanggan Club';

    /**
     * @param  array{name?: ?string, email?: ?string, phone?: ?string}  $customer
     * @param  ?string  $fallbackName  nama pengganti kalau tidak ada huruf sama sekali; null = nama tidak dikirim
     * @return array{name?: string, email?: string, phone?: string} field kosong / tidak valid tidak disertakan
     */
    public static function clean(array $customer, ?string $fallbackName = self::FALLBACK_NAME): array
    {
        return array_filter([
            'name' => self::name($customer['name'] ?? null, $fallbackName),
            'email' => self::email($customer['email'] ?? null),
            'phone' => self::phone($customer['phone'] ?? null),
        ], fn ($v) => $v !== null);
    }

    /** Format customer_details Midtrans Snap. */
    public static function forMidtrans(array $customer, ?string $fallbackName = self::FALLBACK_NAME): array
    {
        $clean = self::clean($customer, $fallbackName);

        return array_filter([
            'first_name' => $clean['name'] ?? null,
            'email' => $clean['email'] ?? null,
            'phone' => $clean['phone'] ?? null,
        ], fn ($v) => $v !== null);
    }

    /** Hanya huruf & spasi, maks. 255. */
    public static function name(?string $name, ?string $fallback = self::FALLBACK_NAME): ?string
    {
        $clean = trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[^\p{L}\p{M}\s]+/u', ' ', (string) $name)));

        return $clean !== '' ? mb_substr($clean, 0, 255) : $fallback;
    }

    /** "{kode negara}{nomor}", mis. 6281122334455, maks. 16 digit. Tidak valid → tidak dikirim. */
    public static function phone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        $digits = match (true) {
            str_starts_with($digits, '62') => $digits,
            str_starts_with($digits, '0') => '62'.substr($digits, 1),
            str_starts_with($digits, '8') => '62'.$digits,
            default => $digits,
        };

        return strlen($digits) >= 10 && strlen($digits) <= 16 && str_starts_with($digits, '62') ? $digits : null;
    }

    /** Maks. 128; email tidak valid tidak dikirim (lebih baik kosong daripada tagihan ditolak). */
    public static function email(?string $email): ?string
    {
        $email = trim((string) $email);

        return $email !== '' && strlen($email) <= 128 && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
