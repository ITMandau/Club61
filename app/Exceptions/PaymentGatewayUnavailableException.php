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
    public function __construct(string $message = 'Gateway pembayaran sedang tidak dapat dihubungi. Belum ada tagihan yang dibuat, silakan coba lagi beberapa saat lagi.', ?\Throwable $previous = null)
    {
        parent::__construct(503, $message, $previous);
    }
}
