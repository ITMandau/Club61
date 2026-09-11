<?php

namespace App\Services\Payment\Drivers;

use App\Services\Payment\Contracts\PaymentGatewayInterface;
use Illuminate\Http\Request;

class MockSimulatorDriver implements PaymentGatewayInterface
{
    public function createPayment(array $params): array
    {
        $orderId = $params['order_id'];

        return [
            'driver' => 'mock',
            'order_id' => $orderId,
            'snap_token' => 'MOCK-SNAP-' . bin2hex(random_bytes(8)),
            'payment_url' => url("/customer/invoice?order_id={$orderId}&mock=true"),
            'redirect_url' => url("/customer/invoice?order_id={$orderId}&mock=true"),
            'is_mock' => true,
            'checkout_mode' => 'POPUP',
        ];
    }

    public function verifyWebhook(Request $request): array
    {
        $payload = $request->all();
        $orderId = $payload['order_id'] ?? $payload['external_id'] ?? 'MOCK-ORD';

        return [
            'is_valid' => true,
            'order_id' => $orderId,
            'status' => 'PAID',
            'raw_status' => 'mock_settlement',
            'gross_amount' => (float) ($payload['gross_amount'] ?? $payload['amount'] ?? 100000),
            'message' => 'Mock simulator payment verified.',
        ];
    }
}
