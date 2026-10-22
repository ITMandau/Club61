<?php

namespace App\Services\Payment\Doku\Snap;

use App\Services\Payment\Doku\DokuClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP ke DOKU SNAP (Standar Nasional Open API Pembayaran) — satu-satunya tempat kunci SNAP dipakai. Rumus mengikuti
 * dokumentasi DOKU + SDK resmi doku/doku-php-library (TokenServices, VaController):
 *
 *   Keluar (kita → DOKU)
 *     - Token B2B   POST /authorization/v1/access-token/b2b, X-SIGNATURE = base64(SHA256withRSA(clientId|X-TIMESTAMP,
 *                   kunci privat kita)); berlaku 15 menit (disimpan di cache).
 *     - Transaksi   X-SIGNATURE = base64(HMAC-SHA512(clientSecret,
 *                   METHOD:path:accessToken:lowerhex(sha256(body minified)):X-TIMESTAMP)), + Authorization Bearer.
 *   Masuk (DOKU → kita, sebelum notifikasi bayar)
 *     - DOKU meminta token ke kita: X-SIGNATURE = SHA256withRSA(clientId|X-TIMESTAMP) dengan kunci privat DOKU →
 *       dicek dengan public key DOKU. Kita membalas JWT RS256 (kunci privat kita) yang lalu dibawa notifikasi.
 *
 * Kunci & token tidak pernah ditulis ke log. X-TIMESTAMP: ISO-8601 WIB (+07:00), sama dengan SDK.
 */
class DokuSnapClient
{
    public const TOKEN_PATH = '/authorization/v1/access-token/b2b';

    public const TOKEN_CACHE_KEY = 'doku_snap_b2b_token';

    /** Token kita untuk DOKU (notifikasi) berlaku 15 menit — sama dengan token DOKU untuk kita. */
    public const INBOUND_TOKEN_TTL = 900;

    /** Jam DOKU vs jam server boleh selisih sebanyak ini pada permintaan token masuk (tolak replay lama). */
    public const MAX_CLOCK_SKEW_SECONDS = 300;

    /**
     * Sama dengan DOKU Checkout & Midtrans (10 dtk): panggilan ini bisa berjalan di dalam transaksi DB yang mengunci baris
     * (audit G14, refactor ditunda) — jangan menunggu lama. Terburuk ± 40 dtk kalau token ditolak lalu diulang sekali.
     */
    public const TIMEOUT_SECONDS = 10;

    public function isEnabled(): bool
    {
        return (bool) config('services.doku.snap.enabled');
    }

    /** Kunci & data akun SNAP lengkap (tanpa ini SNAP tidak pernah dipakai untuk tagihan baru). */
    public function isConfigured(): bool
    {
        return $this->clientId() !== ''
            && trim((string) config('services.doku.snap.client_secret')) !== ''
            && $this->privateKey() !== null
            && $this->dokuPublicKey() !== null;
    }

    public function isProduction(): bool
    {
        return (bool) config('services.doku.is_production');
    }

    public function baseUrl(): string
    {
        return $this->isProduction() ? 'https://api.doku.com' : 'https://api-sandbox.doku.com';
    }

    public function clientId(): string
    {
        return trim((string) config('services.doku.snap.client_id'));
    }

    public static function timestamp(): string
    {
        return now()->timezone('Asia/Jakarta')->format('c');
    }

    // ── Keluar ──────────────────────────────────────────────────────────────────────────────────────────────────

    /** Token B2B dari DOKU (cache sampai 1 menit sebelum habis). Null = gagal (dicatat [ALERT]). */
    public function accessToken(bool $fresh = false): ?string
    {
        if ($fresh) {
            Cache::forget(self::TOKEN_CACHE_KEY);
        }
        if (is_string($cached = Cache::get(self::TOKEN_CACHE_KEY)) && $cached !== '') {
            return $cached;
        }

        $privateKey = $this->privateKey();
        if (! $privateKey) {
            return null;
        }

        $timestamp = self::timestamp();
        $signature = self::asymmetricSignature($this->clientId().'|'.$timestamp, $privateKey);
        if ($signature === null) {
            DokuClient::alert('DOKU SNAP: kunci privat tidak bisa dipakai untuk tanda tangan token.');

            return null;
        }

        try {
            $response = Http::withHeaders([
                'X-CLIENT-KEY' => $this->clientId(),
                'X-TIMESTAMP' => $timestamp,
                'X-SIGNATURE' => $signature,
                'Accept' => 'application/json',
            ])->timeout(self::TIMEOUT_SECONDS)
                ->withBody(json_encode(['grantType' => 'client_credentials', 'additionalInfo' => (object) []]), 'application/json')
                ->post($this->baseUrl().self::TOKEN_PATH);
        } catch (\Throwable $e) {
            Log::channel('doku')->warning('SNAP token gagal terhubung: '.$e->getMessage());

            return null;
        }

        $json = $response->json();
        $token = is_array($json) ? ($json['accessToken'] ?? null) : null;
        Log::channel('doku')->info('SNAP POST '.self::TOKEN_PATH.' → HTTP '.$response->status(), array_filter([
            'responseCode' => is_array($json) ? ($json['responseCode'] ?? null) : null,
            'error' => $response->successful() ? null : DokuClient::errorDetail(is_array($json) ? $json : null, $response->body()),
        ]));

        if (! $response->successful() || ! is_string($token) || $token === '') {
            DokuClient::alert('DOKU SNAP: token B2B ditolak', ['http' => $response->status(), 'responseCode' => $json['responseCode'] ?? null, 'responseMessage' => $json['responseMessage'] ?? null]);

            return null;
        }

        $ttl = max(60, (int) ($json['expiresIn'] ?? 900) - 60);
        Cache::put(self::TOKEN_CACHE_KEY, $token, $ttl);

        return $token;
    }

    /**
     * Request transaksi bertanda tangan. Token ditolak (401) → token baru, diulang SEKALI dengan X-EXTERNAL-ID yang sama.
     *
     * @return array{status: int, json: ?array, external_id: string, error: mixed}|null null = tidak terhubung / tanpa token
     */
    public function request(string $method, string $path, array $body, string $channelId, ?string $externalId = null): ?array
    {
        $externalId ??= self::externalId();
        $payload = self::minify($body);

        foreach ([false, true] as $retry) {
            $token = $this->accessToken(fresh: $retry);
            if (! $token) {
                return null;
            }

            $timestamp = self::timestamp();
            $headers = [
                'X-PARTNER-ID' => $this->clientId(),
                'X-EXTERNAL-ID' => $externalId,
                'X-TIMESTAMP' => $timestamp,
                'X-SIGNATURE' => self::symmetricSignature($method, $path, $token, $payload, $timestamp, (string) config('services.doku.snap.client_secret')),
                'CHANNEL-ID' => $channelId,
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json',
            ];

            try {
                $response = Http::withHeaders($headers)->timeout(self::TIMEOUT_SECONDS)
                    ->withBody($payload, 'application/json')
                    ->send($method, $this->baseUrl().$path);
            } catch (\Throwable $e) {
                Log::channel('doku')->warning("SNAP {$method} {$path} gagal terhubung: ".$e->getMessage(), ['external_id' => $externalId]);

                return null;
            }

            $json = $response->json();
            $json = is_array($json) ? $json : null;
            $error = $response->successful() ? null : DokuClient::errorDetail($json, $response->body());
            Log::channel('doku')->info("SNAP {$method} {$path} → HTTP {$response->status()}", array_filter([
                'external_id' => $externalId,
                'responseCode' => $json['responseCode'] ?? null,
                'error' => $error,
            ]));

            if ($response->status() === 401 && ! $retry) {
                continue;
            }

            return ['status' => $response->status(), 'json' => $json, 'external_id' => $externalId, 'error' => $error];
        }

        return null;
    }

    // ── Masuk ───────────────────────────────────────────────────────────────────────────────────────────────────

    /** Permintaan token dari DOKU: X-CLIENT-KEY = client id kita, tanda tangan RSA DOKU sah, jam tidak terlalu jauh. */
    public function verifyInboundTokenRequest(string $clientKey, string $timestamp, string $signature): bool
    {
        $publicKey = $this->dokuPublicKey();
        if (! $publicKey || $clientKey === '' || ! hash_equals($this->clientId(), $clientKey) || $signature === '') {
            return false;
        }

        try {
            $sentAt = \Illuminate\Support\Carbon::parse($timestamp);
        } catch (\Throwable $e) {
            return false;
        }
        if (abs(now()->diffInSeconds($sentAt, false)) > self::MAX_CLOCK_SKEW_SECONDS) {
            return false;
        }

        $raw = base64_decode($signature, true);

        return $raw !== false && openssl_verify($clientKey.'|'.$timestamp, $raw, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    /** JWT RS256 untuk DOKU (dibawa sebagai Bearer pada notifikasi). */
    public function issueInboundToken(): ?string
    {
        $privateKey = $this->privateKey();
        if (! $privateKey) {
            return null;
        }

        $now = now()->getTimestamp();
        $segments = [
            self::base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            self::base64Url(json_encode(['iss' => 'club61', 'iat' => $now, 'exp' => $now + self::INBOUND_TOKEN_TTL, 'clientId' => $this->clientId()])),
        ];
        $signature = '';
        if (! openssl_sign(implode('.', $segments), $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            return null;
        }

        return implode('.', [...$segments, self::base64Url($signature)]);
    }

    /** Bearer pada notifikasi = JWT yang kita terbitkan: tanda tangan sah (public key kita), belum habis, client id cocok. */
    public function verifyInboundToken(?string $authorization): bool
    {
        $jwt = trim(preg_replace('/^Bearer\s+/i', '', (string) $authorization));
        $parts = explode('.', $jwt);
        $privateKey = $this->privateKey();
        if (count($parts) !== 3 || ! $privateKey) {
            return false;
        }

        $header = json_decode((string) self::base64UrlDecode($parts[0]), true);
        $claims = json_decode((string) self::base64UrlDecode($parts[1]), true);
        $signature = self::base64UrlDecode($parts[2]);
        $publicKey = openssl_pkey_get_details($privateKey)['key'] ?? null;
        if (! is_array($header) || ($header['alg'] ?? null) !== 'RS256' || ! is_array($claims) || $signature === null || ! $publicKey) {
            return false;
        }
        if (openssl_verify($parts[0].'.'.$parts[1], $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            return false;
        }

        return (int) ($claims['exp'] ?? 0) >= now()->getTimestamp()
            && hash_equals($this->clientId(), (string) ($claims['clientId'] ?? ''));
    }

    // ── Rumus (statis, diuji langsung) ──────────────────────────────────────────────────────────────────────────

    public static function asymmetricSignature(string $stringToSign, \OpenSSLAsymmetricKey $privateKey): ?string
    {
        $signature = '';

        return openssl_sign($stringToSign, $signature, $privateKey, OPENSSL_ALGO_SHA256) ? base64_encode($signature) : null;
    }

    public static function symmetricSignature(string $method, string $path, string $token, string $minifiedBody, string $timestamp, string $secret): string
    {
        $stringToSign = strtoupper($method).':'.$path.':'.$token.':'.strtolower(hash('sha256', $minifiedBody)).':'.$timestamp;

        return base64_encode(hash_hmac('sha512', $stringToSign, $secret, true));
    }

    public static function minify(array $body): string
    {
        return (string) json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** X-EXTERNAL-ID: angka unik (dokumentasi: numerik, unik per hari). */
    public static function externalId(): string
    {
        return now()->format('ymdHis').str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
    }

    // ── Kunci ───────────────────────────────────────────────────────────────────────────────────────────────────

    public function privateKey(): ?\OpenSSLAsymmetricKey
    {
        $pem = self::readKeyFile(config('services.doku.snap.private_key_path'));
        if ($pem === null) {
            return null;
        }
        $passphrase = (string) config('services.doku.snap.private_key_passphrase');
        $key = openssl_pkey_get_private($pem, $passphrase !== '' ? $passphrase : null);

        return $key === false ? null : $key;
    }

    public function dokuPublicKey(): ?\OpenSSLAsymmetricKey
    {
        $pem = self::readKeyFile(config('services.doku.snap.doku_public_key_path'));
        $key = $pem === null ? false : openssl_pkey_get_public($pem);

        return $key === false ? null : $key;
    }

    private static function readKeyFile(mixed $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            return null;
        }
        $pem = file_get_contents($path);

        return is_string($pem) && $pem !== '' ? $pem : null;
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);

        return $decoded === false ? null : $decoded;
    }
}
