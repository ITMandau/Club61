<?php

namespace App\Services\Payment\Doku;

/**
 * Tanda tangan DOKU non-SNAP (Checkout, cek status, batal, notifikasi masuk) — rumus dari dokumentasi resmi
 * "Signature Component from Request Header" + contoh kode PHP Jokul:
 *
 *   Digest    = base64( sha256(body mentah) )                  — hanya kalau ada body (POST)
 *   Komponen  = "Client-Id:{id}\nRequest-Id:{rid}\nRequest-Timestamp:{ts}\nRequest-Target:{path}[\nDigest:{digest}]"
 *               (tanpa baris baru di akhir)
 *   Signature = "HMACSHA256=" + base64( hmac_sha256(komponen, Secret Key) )
 *
 * Request-Target = path endpoint DOKU untuk request keluar, atau path URL notifikasi KITA untuk notifikasi masuk.
 * Digest SELALU dihitung dari byte yang persis dikirim / diterima — bukan dari array yang di-encode ulang.
 */
final class DokuSignature
{
    public const PREFIX = 'HMACSHA256=';

    public static function digest(string $body): string
    {
        return base64_encode(hash('sha256', $body, true));
    }

    public static function components(string $clientId, string $requestId, string $timestamp, string $target, ?string $body): string
    {
        $components = "Client-Id:{$clientId}\nRequest-Id:{$requestId}\nRequest-Timestamp:{$timestamp}\nRequest-Target:{$target}";

        return $body === null ? $components : $components."\nDigest:".self::digest($body);
    }

    public static function make(string $clientId, string $requestId, string $timestamp, string $target, ?string $body, string $secretKey): string
    {
        return self::PREFIX.base64_encode(hash_hmac('sha256', self::components($clientId, $requestId, $timestamp, $target, $body), $secretKey, true));
    }

    /** Perbandingan waktu-konstan. Kunci / header kosong = selalu gagal (fail-closed). */
    public static function verify(string $signature, string $clientId, string $requestId, string $timestamp, string $target, string $body, string $secretKey): bool
    {
        if ($signature === '' || $clientId === '' || $requestId === '' || $timestamp === '' || $secretKey === '') {
            return false;
        }

        return hash_equals(self::make($clientId, $requestId, $timestamp, $target, $body, $secretKey), $signature);
    }
}
