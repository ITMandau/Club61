<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Gateway pembayaran (Midtrans) tidak bisa membuat sesi pembayaran — key belum diisi, jaringan
 * putus, atau request ditolak. Checkout WAJIB gagal (fail-closed) dan transaksi DB-nya di-rollback:
 * jangan pernah dianggap lunas, karena uangnya belum tentu (dan hampir pasti tidak) masuk.
 */
class PaymentGatewayUnavailableException extends HttpException
{
    public function __construct(?string $message = null, ?\Throwable $previous = null)
    {
        // Tanpa pesan khusus: teks standar lang/{id,en}/messages.php (payment.gateway_unavailable).
        parent::__construct(503, $message ?? \App\Support\SiteLocale::text('payment.gateway_unavailable'), $previous);
    }
}
