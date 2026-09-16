<?php

namespace App\Services\Payment\Contracts;

use App\Models\Pos\Order;
use Illuminate\Support\Collection;

interface DomainFulfillmentHandlerInterface
{
    /**
     * Memproses pemenuhan pesanan spesifik domain setelah pembayaran berhasil.
     */
    public function fulfill(Order $order, Collection $items): void;
}
