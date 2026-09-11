<?php

namespace App\Services\Payment\Contracts;

use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    /**
     * Membuat sesi pembayaran / invoice / snap token.
     *
     * @param array $params [
     *     'order_id' => string,
     *     'gross_amount' => int,
     *     'item_details' => array,
     *     'customer_details' => array,
     * ]
     * @return array [
     *     'driver' => string,         // midtrans, xendit, mock
     *     'order_id' => string,
     *     'snap_token' => ?string,    // Khusus Midtrans Snap
     *     'payment_url' => string,    // URL invoice (Xendit) atau redirect Midtrans
     *     'redirect_url' => string,
     *     'is_mock' => bool,
     *     'checkout_mode' => string,  // POPUP (Midtrans Snap) atau REDIRECT / IFRAME (Xendit)
     * ]
     */
    public function createPayment(array $params): array;

    /**
     * Memvalidasi webhook masuk dan mengembalikan status ternormalisasi.
     *
     * @param Request $request
     * @return array [
     *     'is_valid' => bool,
     *     'order_id' => string,
     *     'status' => string,        // PAID, PENDING, CANCELLED
     *     'raw_status' => string,
     *     'gross_amount' => float,
     *     'message' => string,
     * ]
     */
    public function verifyWebhook(Request $request): array;
}
