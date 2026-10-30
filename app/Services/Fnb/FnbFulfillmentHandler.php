<?php

namespace App\Services\Fnb;

use App\Models\Pos\Order;
use App\Services\Payment\Contracts\DomainFulfillmentHandlerInterface;
use Illuminate\Support\Collection;

/** Order F&B lunas → slip pesanan untuk tiap stasiun yang punya menu di order ini (idempoten). */
class FnbFulfillmentHandler implements DomainFulfillmentHandlerInterface
{
    public function __construct(private StationTicketService $tickets, private FnbStockService $stock) {}

    public function fulfill(Order $order, Collection $items): void
    {
        // Hanya order yang benar-benar LUNAS (bukan PARTIALLY_PAID) — sama dengan "Kirim Ulang ke Stasiun".
        if ($order->payment_status !== 'PAID') {
            \Illuminate\Support\Facades\Log::critical("[ALERT] Order F&B [{$order->order_number}] belum lunas penuh ({$order->payment_status}) — slip stasiun tidak dibuat.");

            return;
        }

        $this->tickets->createForOrder($order);

        // Modul 29: stok menu yang dilacak berkurang saat lunas (sekali per pesanan, boleh minus).
        $this->stock->deductForOrder($order);
    }
}
