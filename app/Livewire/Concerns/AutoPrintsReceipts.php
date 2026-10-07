<?php

namespace App\Livewire\Concerns;

use App\Models\Pos\Order;
use Livewire\Attributes\Locked;

/**
 * Cetak struk otomatis begitu Bayar Otomatis (Midtrans) lunas — dipakai POS Walk-In, Jual Membership & F&B.
 *
 * Alur: server memastikan lunas (webhook / cek status Midtrans) → completePendingQris() memanggil queueAutoPrint() →
 * event 'club61-auto-print' hanya ke layar kasir yang membuat transaksi ini, berisi HTML struk (partial yang SAMA
 * dengan modal & tombol Cetak Struk). Script cetak (pos.partials.receipt-print-script) hanya jalan di aplikasi
 * Club61 (window.Club61Print), mengklaim order lewat claimAutoPrint() — kolom orders.receipt_printed_at, sekali per
 * order — baru mencetak, lalu memanggil $next untuk menyiapkan transaksi berikutnya.
 */
trait AutoPrintsReceipts
{
    /** Order yang baru lunas di layar ini — satu-satunya order yang boleh diklaim untuk cetak otomatis. */
    #[Locked]
    public ?string $autoPrintOrderId = null;

    protected function queueAutoPrint(string $orderId, string $view, array $data, string $next): void
    {
        $this->autoPrintOrderId = $orderId;

        $this->dispatch(
            'club61-auto-print',
            key: $orderId,
            wireId: $this->getId(),
            html: view($view, $data)->render(),
            next: $next,
        );
    }

    /** true = layar ini yang mencetak; false = sudah dicetak (tab lain / refresh / dobel event) → jangan cetak. */
    public function claimAutoPrint(string $orderId): bool
    {
        if ($this->autoPrintOrderId === null || ! hash_equals($this->autoPrintOrderId, $orderId)) {
            return false;
        }

        return Order::whereKey($orderId)
            ->where('payment_status', 'PAID')
            ->whereNull('receipt_printed_at')
            ->update(['receipt_printed_at' => now()]) === 1;
    }
}
