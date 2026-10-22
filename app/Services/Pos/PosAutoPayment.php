<?php

namespace App\Services\Pos;

use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\User;
use App\Services\Payment\Doku\DokuPosQrisService;
use App\Services\Payment\OnlineGatewayRouter;

/**
 * "Bayar Otomatis" di layar kasir (POS Walk-In, Jual Membership, F&B) — memilih gateway (Modul 27 §9):
 *   - tagihan BARU memakai gateway "QR otomatis di kasir" pilihan admin (Metode Pembayaran Online → Gateway Pembayaran);
 *   - tagihan yang sudah dibuat diurus gateway yang membuatnya (dibaca dari payment_gateway tagihan).
 * Status yang dikembalikan sama untuk kedua gateway: PAID / PENDING / EXPIRED / CANCELLED. cancel() bisa PENDING
 * (DOKU belum bisa dipastikan tertutup — QR tetap tampil, kasir coba lagi).
 */
class PosAutoPayment
{
    public const PAID = PosMidtransQrisService::PAID;

    public const PENDING = PosMidtransQrisService::PENDING;

    public const EXPIRED = PosMidtransQrisService::EXPIRED;

    public const CANCELLED = PosMidtransQrisService::CANCELLED;

    public function open(Order $order, string $counter, User $cashier, array $payloadLog = [], string $method = 'QRIS'): array
    {
        return app(OnlineGatewayRouter::class)->posQrisGateway() === OnlineGatewayRouter::DOKU
            ? app(DokuPosQrisService::class)->open($order, $counter, $cashier, $payloadLog, $method)
            : app(PosMidtransQrisService::class)->open($order, $counter, $cashier, $payloadLog, $method);
    }

    /** Kartu yang masih menunggu milik kasir ini (gateway mana pun) — yang terbaru. */
    public function resumeFor(string $counter, string $source, ?string $cashierId): ?array
    {
        $found = array_filter([
            app(PosMidtransQrisService::class)->resumeFor($counter, $source, $cashierId),
            app(DokuPosQrisService::class)->resumeFor($counter, $source, $cashierId),
        ]);
        usort($found, fn (array $a, array $b) => strcmp((string) $b['expires_at'], (string) $a['expires_at']));

        return $found[0] ?? null;
    }

    public function status(string $paymentId): string
    {
        return $this->isDoku($paymentId)
            ? app(DokuPosQrisService::class)->status($paymentId)
            : app(PosMidtransQrisService::class)->status($paymentId);
    }

    public function cancel(string $paymentId, string $reason = 'CASHIER_CANCEL'): string
    {
        return $this->isDoku($paymentId)
            ? app(DokuPosQrisService::class)->cancel($paymentId, $reason)
            : app(PosMidtransQrisService::class)->cancel($paymentId, $reason);
    }

    /** QR mock Midtrans di laptop developer saja (DOKU tidak punya mode mock). */
    public function simulatePaid(string $paymentId): void
    {
        abort_if($this->isDoku($paymentId), 403);

        app(PosMidtransQrisService::class)->simulatePaid($paymentId);
    }

    public function sweepAbandoned(): int
    {
        return app(PosMidtransQrisService::class)->sweepAbandoned() + app(DokuPosQrisService::class)->sweepAbandoned();
    }

    /** Pesan untuk kasir kalau cancel() mengembalikan PENDING. */
    public static function cannotCancelYetMessage(): string
    {
        return 'QR belum bisa dibatalkan karena gateway belum memastikan QR ini tertutup. QR tetap aktif — coba Batalkan lagi sebentar, atau tunggu sampai kedaluwarsa. Jangan terima pembayaran lain untuk pesanan ini.';
    }

    private function isDoku(string $paymentId): bool
    {
        return Payment::whereKey($paymentId)->value('payment_gateway') === OnlineGatewayRouter::DOKU;
    }
}
