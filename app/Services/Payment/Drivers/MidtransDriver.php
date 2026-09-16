<?php

namespace App\Services\Payment\Drivers;

use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Services\Payment\MidtransService;
use Illuminate\Http\Request;

class MidtransDriver implements PaymentGatewayInterface
{
    protected MidtransService $service;

    public function __construct(?MidtransService $service = null)
    {
        $this->service = $service ?? app(MidtransService::class);
    }

    public function createPayment(array $params): array
    {
        $res = $this->service->createSnapTransaction($params);

        return [
            'driver' => 'midtrans',
            'order_id' => $params['order_id'],
            'snap_token' => $res['snap_token'],
            'payment_url' => $res['redirect_url'],
            'redirect_url' => $res['redirect_url'],
            'is_mock' => $res['is_mock'],
            'checkout_mode' => 'POPUP', // Midtrans Snap UI
        ];
    }

    public function verifyWebhook(Request $request): array
    {
        $payload = $request->all();
        $orderId = $payload['order_id'] ?? '';
        $statusCode = (string) ($payload['status_code'] ?? '');
        $grossAmount = (string) ($payload['gross_amount'] ?? '');
        $incomingSignature = $payload['signature_key'] ?? '';
        $transactionStatus = $payload['transaction_status'] ?? '';
        $fraudStatus = $payload['fraud_status'] ?? 'accept';

        // QA DEFENSE 1: Timing Attack Safe Signature Verification
        $isValid = $this->service->verifySignature($orderId, $statusCode, $grossAmount, $incomingSignature);

        if (! $isValid) {
            return [
                'is_valid' => false,
                'order_id' => $orderId,
                'status' => 'INVALID',
                'raw_status' => $transactionStatus,
                'gross_amount' => (float) $grossAmount,
                'message' => 'Akses ditolak: Signature Key tidak valid (Spoofing rejected).',
            ];
        }

        $normalizedStatus = match ($transactionStatus) {
            'capture' => ($fraudStatus === 'challenge') ? 'CHALLENGE' : 'PAID',
            'settlement' => 'PAID',
            'pending' => 'PENDING',
            'deny', 'expire', 'cancel' => 'CANCELLED',
            default => 'UNKNOWN',
        };

        return [
            'is_valid' => true,
            'order_id' => $orderId,
            'status' => $normalizedStatus,
            'raw_status' => $transactionStatus,
            'gross_amount' => (float) $grossAmount,
            'message' => "Midtrans transaction status: {$normalizedStatus}",
        ];
    }
}
