<?php

namespace App\Services\Payment\Doku;

use App\Exceptions\PaymentGatewayUnavailableException;
use App\Services\Payment\GatewayCustomer;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * DOKU Checkout: bikin tagihan (satu metode per sesi), cek status, batalkan. Semua pemanggil di luar folder DOKU
 * memakai OnlineGatewayRouter, bukan kelas ini langsung.
 *
 * Status sesi (hasil state()):
 *   PAID      — transaction.status SUCCESS untuk invoice ini
 *   PENDING   — masih bisa dibayar (PENDING / TIMEOUT / REDIRECT, atau FAILED sebelum kedaluwarsa)
 *   CLOSED    — tidak bisa dibayar lagi (EXPIRED, order kedaluwarsa, FAILED setelah kedaluwarsa)
 *   REFUNDED  — dulu dibayar lalu dikembalikan (dicatat [ALERT], booking tidak diubah otomatis)
 *   NOT_FOUND — DOKU tidak mengenal invoice ini (HTTP 404)
 *   ERROR     — DOKU tidak bisa dihubungi / jawaban janggal; JANGAN diartikan "belum bayar"
 */
class DokuCheckoutService
{
    public const PAID = 'PAID';

    public const PENDING = 'PENDING';

    public const CLOSED = 'CLOSED';

    public const REFUNDED = 'REFUNDED';

    public const NOT_FOUND = 'NOT_FOUND';

    public const ERROR = 'ERROR';

    public const CHECKOUT_PATH = '/checkout/v1/payment';

    public const CANCEL_PATH = '/checkout/v3/cancellations';

    public const STATUS_PATH = '/orders/v1/status/';

    /** Batas dokumentasi: invoice_number maks. 64 karakter (30 kalau kartu dipakai — kartu tidak dipakai). */
    public const INVOICE_MAX_LENGTH = 64;

    public function __construct(protected DokuClient $client) {}

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * Nomor invoice DOKU untuk satu sesi bayar: "{nomor order}-DK-{waktu}{acak}". Tidak pernah dipakai ulang. Hanya
     * huruf, angka & strip; nomor order bisa dibaca balik dari bagian sebelum "-DK-" terakhir (lihat orderNumberOf).
     */
    public static function newInvoiceNumber(string $orderNumber): string
    {
        $base = preg_replace('/[^A-Za-z0-9-]/', '-', $orderNumber);
        $suffix = '-DK-'.strtoupper(base_convert((string) now()->getTimestamp(), 10, 36)).strtoupper(\Illuminate\Support\Str::random(6));

        return substr($base, 0, self::INVOICE_MAX_LENGTH - strlen($suffix)).$suffix;
    }

    public static function orderNumberOf(string $invoiceNumber): ?string
    {
        $pos = strrpos($invoiceNumber, '-DK-');

        return $pos === false || $pos === 0 ? null : substr($invoiceNumber, 0, $pos);
    }

    /**
     * Bikin tagihan DOKU Checkout. Fail-closed: gagal apa pun dilempar (transaksi checkout di-rollback), tidak pernah
     * dianggap lunas.
     *
     * @param  array{invoice: string, amount: int, channels: array<int, string>, items: array<int, array{id?: string, name: string, price: int, quantity: int}>, customer: array{name?: ?string, email?: ?string, phone?: ?string}, due_minutes: int, callback_url: string}  $params
     * @return array{session_id: string, payment_url: string, token_id: ?string, request_id: string, expires_at: CarbonInterface}
     */
    public function createSession(array $params): array
    {
        $invoice = (string) $params['invoice'];
        $amount = (int) $params['amount'];
        $channels = array_values(array_filter((array) $params['channels'], 'is_string'));

        if ($amount <= 0 || $channels === [] || $invoice === '' || strlen($invoice) > self::INVOICE_MAX_LENGTH || ! preg_match('/^[A-Za-z0-9-]+$/', $invoice)) {
            throw new InvalidArgumentException("Data tagihan DOKU tidak valid [{$invoice}].");
        }

        // Nominal integer IDR & jumlah baris = nominal (dicek sebelum dikirim, sama seperti Midtrans).
        $items = [];
        $sum = 0;
        foreach ((array) $params['items'] as $item) {
            $price = (int) $item['price'];
            $quantity = (int) $item['quantity'];
            $sum += $price * $quantity;
            $items[] = array_filter([
                'id' => isset($item['id']) ? mb_substr((string) $item['id'], 0, 50) : null,
                'name' => mb_substr((string) $item['name'], 0, 50),
                'price' => $price,
                'quantity' => $quantity,
            ], fn ($v) => $v !== null);
        }
        if ($sum !== $amount) {
            throw new InvalidArgumentException("Jumlah item DOKU ({$sum}) tidak sama dengan nominal tagihan ({$amount}) [{$invoice}].");
        }

        if (! $this->client->isConfigured()) {
            DokuClient::alert("DOKU dipilih tapi kuncinya kosong — checkout ditolak [{$invoice}].");
            throw new PaymentGatewayUnavailableException();
        }

        $customer = GatewayCustomer::clean($params['customer'] ?? []);

        $payload = [
            'order' => [
                'amount' => $amount,
                'invoice_number' => $invoice,
                'currency' => 'IDR',
                'callback_url' => $params['callback_url'],
                'callback_url_cancel' => $params['callback_url'],
                'auto_redirect' => true,
                'line_items' => $items,
            ],
            'payment' => [
                // Sisa waktu bayar tagihan (= batas pelepasan slot), bukan default DOKU 60 menit.
                'payment_due_date' => max(1, (int) $params['due_minutes']),
                'payment_method_types' => $channels,
            ],
        ];
        if ($customer !== []) {
            $payload['customer'] = $customer;
        }
        // Laptop developer (tunnel): URL notifikasi per request, path harus sama dengan URL di dashboard DOKU.
        // Tidak berlaku di production — notifikasi production hanya ke URL resmi yang terdaftar.
        $override = trim((string) config('services.doku.notification_url'));
        if ($override !== '' && ! $this->client->isProduction()) {
            $payload['additional_info'] = ['override_notification_url' => $override];
        }

        $response = $this->client->post(self::CHECKOUT_PATH, $payload);

        // 409 = request yang sama (Request-Id) sudah diproses sebelumnya → DOKU membalas respons aslinya.
        $paymentUrl = $response ? ($response['json']['response']['payment']['url'] ?? null) : null;
        if (! $response || ! in_array($response['status'], [200, 409], true) || ! is_string($paymentUrl) || ! self::isDokuUrl($paymentUrl)) {
            DokuClient::alert("DOKU Checkout gagal membuat tagihan [{$invoice}]", [
                'http' => $response['status'] ?? null,
                'error_messages' => $response['json']['error_messages'] ?? $response['json']['error'] ?? null,
            ]);
            throw new PaymentGatewayUnavailableException();
        }

        $expiresAt = self::parseExpiredDate($response['json']['response']['payment']['expired_date'] ?? null)
            ?? now()->addMinutes(max(1, (int) $params['due_minutes']));

        return [
            'session_id' => $invoice,
            'payment_url' => $paymentUrl,
            'token_id' => is_string($response['json']['response']['payment']['token_id'] ?? null) ? $response['json']['response']['payment']['token_id'] : null,
            'request_id' => $response['request_id'],
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Cek status satu invoice.
     *
     * @return array{state: string, data: ?array}
     */
    public function state(string $invoice, ?CarbonInterface $expiresAt = null): array
    {
        if (! $this->client->isConfigured()) {
            return ['state' => self::ERROR, 'data' => null];
        }

        $response = $this->client->get(self::STATUS_PATH.rawurlencode($invoice));
        if ($response === null) {
            return ['state' => self::ERROR, 'data' => null];
        }
        if ($response['status'] === 404) {
            return ['state' => self::NOT_FOUND, 'data' => $response['json']];
        }

        $data = $response['json'];
        if ($response['status'] < 200 || $response['status'] >= 300 || ! is_array($data)) {
            return ['state' => self::ERROR, 'data' => $data];
        }

        // Jawaban untuk invoice lain = janggal; jangan dipakai untuk melunasi / menutup apa pun.
        if (($data['order']['invoice_number'] ?? null) !== $invoice) {
            DokuClient::alert("DOKU status: invoice_number respons tidak cocok [{$invoice}]", ['invoice_respons' => $data['order']['invoice_number'] ?? null]);

            return ['state' => self::ERROR, 'data' => $data];
        }

        return ['state' => self::interpret($data, $expiresAt), 'data' => $data];
    }

    /** transaction.status / order.status DOKU → status sesi kita. */
    public static function interpret(array $data, ?CarbonInterface $expiresAt = null): string
    {
        $transaction = strtoupper((string) ($data['transaction']['status'] ?? ''));
        $order = strtoupper((string) ($data['order']['status'] ?? ''));

        return match (true) {
            $transaction === 'SUCCESS' => self::PAID,
            $transaction === 'REFUNDED' => self::REFUNDED,
            $transaction === 'EXPIRED', $order === 'ORDER_EXPIRED' => self::CLOSED,
            // FAILED bukan akhir selama tagihan belum kedaluwarsa (customer bisa mencoba lagi di halaman DOKU).
            $transaction === 'FAILED' => $expiresAt !== null && $expiresAt->isPast() ? self::CLOSED : self::PENDING,
            in_array($transaction, ['PENDING', 'TIMEOUT', 'REDIRECT'], true) => self::PENDING,
            default => self::ERROR,
        };
    }

    /**
     * Pastikan sesi ini TIDAK BISA dibayar lagi lalu buktikan lewat API status (jawaban API batal saja tidak dipercaya).
     *
     * @return array{result: string, data: ?array} CLOSED / NOT_FOUND / PAID / FAILED (jangan buka sesi baru)
     */
    public function close(string $invoice, ?string $originalRequestId, ?CarbonInterface $expiresAt = null): array
    {
        $before = $this->state($invoice, $expiresAt);
        $early = $this->closeResult($before);
        if ($early !== null) {
            return ['result' => $early, 'data' => $before['data']];
        }

        // Masih bisa dibayar → batalkan (fitur Order Cancellation harus aktif di dashboard DOKU; hanya QRIS & VA).
        $cancel = $originalRequestId ? $this->client->post(self::CANCEL_PATH, [
            'order' => ['invoice_number' => $invoice],
            'payment' => ['original_request_id' => $originalRequestId],
            'note' => 'Club 61: customer mengganti metode pembayaran / tagihan ditutup',
        ]) : null;

        $after = $this->state($invoice, $expiresAt);
        $result = $this->closeResult($after);
        if ($result !== null) {
            return ['result' => $result, 'data' => $after['data']];
        }

        DokuClient::alert("Sesi DOKU [{$invoice}] belum bisa ditutup (status ".($after['data']['transaction']['status'] ?? $after['state']).')', [
            'cancel_http' => $cancel['status'] ?? null,
            'cancel_error' => $cancel['json']['error_messages'] ?? $cancel['json']['error'] ?? $cancel['json']['message'] ?? null,
        ]);

        return ['result' => 'FAILED', 'data' => $after['data']];
    }

    /** Membatalkan tanpa menunggu bukti (best-effort, mis. setelah tagihan lunas lewat kasir). */
    public function cancel(string $invoice, string $originalRequestId): bool
    {
        $response = $this->client->post(self::CANCEL_PATH, [
            'order' => ['invoice_number' => $invoice],
            'payment' => ['original_request_id' => $originalRequestId],
            'note' => 'Club 61: tagihan sudah dilunasi / ditutup',
        ]);

        return $response !== null && $response['status'] >= 200 && $response['status'] < 300;
    }

    /** URL halaman bayar yang boleh diberikan ke customer: https + domain doku.com. */
    public static function isDokuUrl(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        return ($parts['scheme'] ?? null) === 'https'
            && ($host === 'doku.com' || str_ends_with($host, '.doku.com'));
    }

    /** expired_date DOKU: yyyyMMddHHmmss, waktu WIB (UTC+7). */
    public static function parseExpiredDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{14}$/', $value)) {
            return null;
        }

        $parsed = Carbon::createFromFormat('YmdHis', $value, 'Asia/Jakarta');

        return $parsed === false ? null : $parsed->utc();
    }

    private function closeResult(array $state): ?string
    {
        return match ($state['state']) {
            self::PAID => self::PAID,
            self::CLOSED => self::CLOSED,
            self::NOT_FOUND => self::NOT_FOUND,
            // Sudah pernah dibayar lalu dikembalikan / jawaban janggal: jangan dianggap aman untuk ditinggal.
            self::REFUNDED, self::ERROR => 'FAILED',
            default => null,
        };
    }
}
