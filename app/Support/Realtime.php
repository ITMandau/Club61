<?php

namespace App\Support;

/**
 * Update realtime (Reverb). Aktif hanya kalau BROADCAST_CONNECTION=reverb dan key-nya terisi; kalau tidak, semua layar
 * memakai polling cadangan. Alamat untuk browser / TV (REVERB_CLIENT_*) terpisah dari alamat internal yang dipakai
 * server untuk mengirim sinyal (REVERB_HOST/PORT): di VPS browser masuk lewat Nginx di domain aplikasi (443),
 * sedangkan aplikasi mengirim langsung ke 127.0.0.1:8080.
 */
final class Realtime
{
    public const BOARD_CHANNEL = 'booking-board';

    public const BOARD_EVENT = 'board.changed';

    public const SIGNATURE_CHANNEL = 'signature';

    public const SIGNATURE_EVENT = 'signature.changed';

    public static function enabled(): bool
    {
        return config('broadcasting.default') === 'reverb'
            && filled(config('broadcasting.connections.reverb.key'));
    }

    /** Konfigurasi koneksi untuk browser / TV, atau null kalau realtime mati (layar langsung pakai polling). */
    public static function client(): ?array
    {
        if (! self::enabled()) {
            return null;
        }

        $appUrl = (string) config('app.url');
        $client = (array) config('broadcasting.connections.reverb.client', []);
        $tls = (($client['scheme'] ?? null) ?: parse_url($appUrl, PHP_URL_SCHEME) ?: 'https') === 'https';

        return [
            'key' => (string) config('broadcasting.connections.reverb.key'),
            'host' => (string) (($client['host'] ?? null) ?: parse_url($appUrl, PHP_URL_HOST) ?: 'localhost'),
            'port' => (int) (($client['port'] ?? null) ?: parse_url($appUrl, PHP_URL_PORT) ?: ($tls ? 443 : 80)),
            'tls' => $tls,
        ];
    }

    /** Konfigurasi Laravel Echo untuk panel admin (Echo + Pusher bawaan Filament). */
    public static function echo(): ?array
    {
        $client = self::client();
        if (! $client) {
            return null;
        }

        return [
            'broadcaster' => 'reverb',
            'key' => $client['key'],
            'wsHost' => $client['host'],
            'wsPort' => $client['port'],
            'wssPort' => $client['port'],
            'forceTLS' => $client['tls'],
            'enabledTransports' => ['ws', 'wss'],
            'authEndpoint' => '/broadcasting/auth',
        ];
    }
}
