<?php

namespace App\Services\Payment\Drivers;

use App\Services\Payment\Contracts\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XenditDriver implements PaymentGatewayInterface
{
    protected string $secretKey;
    protected string $publicKey;
    protected string $callbackToken;
    protected bool $isProduction;
    protected string $invoiceApiUrl;

    public function __construct()
    {
        $this->secretKey = config('services.xendit.secret_key') ?? '';
        $this->publicKey = config('services.xendit.public_key') ?? '';
        $this->callbackToken = config('services.xendit.callback_token') ?? 'xendit_webhook_token_club61';
        $this->isProduction = (bool) config('services.xendit.is_production', false);
        $this->invoiceApiUrl = 'https://api.xendit.co/v2/invoices';
    }

    public function createPayment(array $params): array
    {
        $orderId = $params['order_id'];
        $grossAmount = (int) $params['gross_amount'];
        $customer = $params['customer_details'] ?? [];
        $items = $params['item_details'] ?? [];

        // Sandbox Mock Fallback jika Secret Key belum dikonfigurasi
        if (empty($this->secretKey)) {
            Log::info("Xendit: Secret key kosong, mengaktifkan Sandbox Mock Simulator", ['order_id' => $orderId]);
            return [
                'driver' => 'xendit',
                'order_id' => $orderId,
                'snap_token' => null,
                'payment_url' => url("/customer/invoice?order_id={$orderId}&mock_xendit=true"),
                'redirect_url' => url("/customer/invoice?order_id={$orderId}&mock_xendit=true"),
                'is_mock' => true,
                'checkout_mode' => 'REDIRECT',
            ];
        }

        $xenditItems = [];
        foreach ($items as $item) {
            $xenditItems[] = [
                'name' => substr($item['name'], 0, 50),
                'price' => (int) $item['price'],
                'quantity' => (int) $item['quantity'],
                'category' => 'PADEL_SPORT',
            ];
        }

        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->timeout(15)
                ->post($this->invoiceApiUrl, [
                    'external_id' => $orderId,
                    'amount' => $grossAmount,
                    'payer_email' => $customer['email'] ?? 'guest@club61.com',
                    'description' => "Reservasi Court Club 61 - {$orderId}",
                    'invoice_duration' => 600, // 10 menit hold
                    'items' => $xenditItems,
                    'customer' => [
                        'given_names' => $customer['first_name'] ?? 'Member',
                        'email' => $customer['email'] ?? null,
                        'mobile_number' => $customer['phone'] ?? null,
                    ],
                    'success_redirect_url' => url('/customer/invoice'),
                    'failure_redirect_url' => url('/customer/checkout'),
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'driver' => 'xendit',
                    'order_id' => $orderId,
                    'snap_token' => null,
                    'payment_url' => $data['invoice_url'] ?? '',
                    'redirect_url' => $data['invoice_url'] ?? '',
                    'is_mock' => false,
                    'checkout_mode' => 'REDIRECT',
                ];
            }

            Log::error("Xendit API Error Response", [
                'order_id' => $orderId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('Xendit error: ' . ($response->json('message') ?? $response->body()));
        } catch (\Throwable $e) {
            Log::warning("Gagal koneksi ke Xendit API, fallback ke mock", [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return [
                'driver' => 'xendit',
                'order_id' => $orderId,
                'snap_token' => null,
                'payment_url' => url("/customer/invoice?order_id={$orderId}&mock_xendit=true"),
                'redirect_url' => url("/customer/invoice?order_id={$orderId}&mock_xendit=true"),
                'is_mock' => true,
                'checkout_mode' => 'REDIRECT',
            ];
        }
    }

    public function verifyWebhook(Request $request): array
    {
        $incomingToken = (string) $request->header('x-callback-token', '');
        $expectedToken = $this->callbackToken;

        // 🛡️ Timing-Attack Safe Verification untuk token webhook Xendit
        $isValid = ! empty($expectedToken) && hash_equals($expectedToken, $incomingToken);

        $payload = $request->all();
        $orderId = $payload['external_id'] ?? ($payload['order_id'] ?? '');
        $rawStatus = strtoupper((string) ($payload['status'] ?? ''));
        $amount = (float) ($payload['amount'] ?? 0);

        if (! $isValid) {
            return [
                'is_valid' => false,
                'order_id' => $orderId,
                'status' => 'INVALID',
                'raw_status' => $rawStatus,
                'gross_amount' => $amount,
                'message' => 'Invalid Xendit callback token.',
            ];
        }

        $normalizedStatus = match ($rawStatus) {
            'PAID', 'SETTLED' => 'PAID',
            'PENDING' => 'PENDING',
            'EXPIRED', 'CANCELLED' => 'CANCELLED',
            default => 'UNKNOWN',
        };

        return [
            'is_valid' => true,
            'order_id' => $orderId,
            'status' => $normalizedStatus,
            'raw_status' => $rawStatus,
            'gross_amount' => $amount,
            'message' => "Xendit invoice status: {$normalizedStatus}",
        ];
    }
}
