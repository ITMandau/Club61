<?php

namespace App\Services\Payment\Doku;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * HTTP ke DOKU (non-SNAP). Satu-satunya tempat kunci DOKU dipakai; kunci & header Signature tidak pernah ditulis ke log.
 * Body dikirim sebagai string JSON mentah supaya Digest dihitung dari byte yang persis dikirim.
 */
class DokuClient
{
    /** Panggilan ini bisa berjalan di dalam transaksi DB yang mengunci baris booking — jangan menunggu lama. */
    public const TIMEOUT_SECONDS = 10;

    protected string $clientId;

    protected string $secretKey;

    protected bool $isProduction;

    public function __construct()
    {
        $this->clientId = trim((string) config('services.doku.client_id'));
        $this->secretKey = trim((string) config('services.doku.secret_key'));
        $this->isProduction = (bool) config('services.doku.is_production', false);
    }

    /** Kunci terisi = bisa bicara dengan DOKU (bikin, cek, batal). Dipakai untuk tagihan baru hanya kalau DOKU_ENABLED. */
    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->secretKey !== '';
    }

    public function isProduction(): bool
    {
        return $this->isProduction;
    }

    public function clientId(): string
    {
        return $this->clientId;
    }

    public function baseUrl(): string
    {
        return $this->isProduction ? 'https://api.doku.com' : 'https://api-sandbox.doku.com';
    }

    /** Verifikasi tanda tangan notifikasi masuk (Request-Target = path URL notifikasi kita). */
    public function verifyNotification(string $signature, string $clientId, string $requestId, string $timestamp, string $target, string $rawBody): bool
    {
        if (! $this->isConfigured() || ! hash_equals($this->clientId, $clientId)) {
            return false;
        }

        return DokuSignature::verify($signature, $clientId, $requestId, $timestamp, $target, $rawBody, $this->secretKey);
    }

    /**
     * POST bertanda tangan. Koneksi putus / timeout → diulang SEKALI dengan Request-Id & body yang sama: kalau DOKU
     * sudah memprosesnya, DOKU membalas respons aslinya dengan HTTP 409 (idempotensi Request-Id).
     *
     * @return array{status: int, json: ?array, request_id: string}|null null = DOKU tidak bisa dihubungi
     */
    public function post(string $path, array $payload, ?string $requestId = null): ?array
    {
        $requestId ??= (string) Str::uuid();
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return $this->send('POST', $path, $body, $requestId)
            ?? $this->send('POST', $path, $body, $requestId);
    }

    /** @return array{status: int, json: ?array, request_id: string}|null */
    public function get(string $path): ?array
    {
        return $this->send('GET', $path, null, (string) Str::uuid());
    }

    /** [ALERT] ditulis ke log utama (dipantau) DAN log DOKU. */
    public static function alert(string $message, array $context = []): void
    {
        Log::warning("[ALERT] {$message}", $context);
        Log::channel('doku')->warning("[ALERT] {$message}", $context);
    }

    private function send(string $method, string $path, ?string $body, string $requestId): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $timestamp = now()->utc()->format('Y-m-d\TH:i:s\Z');
        $headers = [
            'Client-Id' => $this->clientId,
            'Request-Id' => $requestId,
            'Request-Timestamp' => $timestamp,
            'Signature' => DokuSignature::make($this->clientId, $requestId, $timestamp, $path, $body, $this->secretKey),
            'Accept' => 'application/json',
        ];

        try {
            $request = Http::withHeaders($headers)->timeout(self::TIMEOUT_SECONDS);
            $response = $method === 'POST'
                ? $request->withBody($body, 'application/json')->post($this->baseUrl().$path)
                : $request->get($this->baseUrl().$path);
        } catch (\Throwable $e) {
            Log::channel('doku')->warning("{$method} {$path} gagal terhubung: ".$e->getMessage(), ['request_id' => $requestId]);

            return null;
        }

        $json = $response->json();
        $error = $response->successful() ? null : self::errorDetail(is_array($json) ? $json : null, $response->body());
        Log::channel('doku')->info("{$method} {$path} → HTTP {$response->status()}", array_filter([
            'request_id' => $requestId,
            'error' => $error,
        ]));

        return ['status' => $response->status(), 'json' => is_array($json) ? $json : null, 'request_id' => $requestId, 'error' => $error];
    }

    /**
     * Alasan penolakan dari DOKU untuk log. Bentuk error DOKU tidak seragam (error_messages / error / message), jadi
     * hanya kunci-kunci error itu yang diambil — bukan seluruh body (bisa memuat data customer). Body bukan JSON
     * (mis. halaman error proxy) → 300 karakter pertama.
     */
    public static function errorDetail(?array $json, string $rawBody = ''): mixed
    {
        if ($json !== null) {
            $picked = array_intersect_key($json, array_flip(['error_messages', 'error', 'errors', 'message', 'code', 'status', 'error_code', 'error_message']));

            return $picked !== [] ? $picked : ['keys' => array_keys($json)];
        }

        $text = trim(preg_replace('/\s+/', ' ', strip_tags($rawBody)) ?? '');

        return $text === '' ? null : mb_substr($text, 0, 300);
    }

    /** Penolakan karena kunci / tanda tangan kita (masalah pengaturan server), bukan karena isi tagihan. */
    public static function isCredentialError(int $status, mixed $error): bool
    {
        if (in_array($status, [401, 403], true)) {
            return true;
        }
        $text = strtolower(is_string($error) ? $error : (string) json_encode($error));

        return str_contains($text, 'client_id') || str_contains($text, 'client-id') || str_contains($text, 'signature');
    }
}
