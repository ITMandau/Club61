<?php

namespace App\Livewire\Concerns;

use App\Services\Audit\ActivityLogger;
use App\Services\Pos\DailySalesRecapService;
use Livewire\Attributes\Locked;

/**
 * Tombol "Cetak Rekap" di tab Riwayat Transaksi POS (Walk-In Padel, Jual Membership, F&B): Rekap Penjualan Harian
 * (X-Report) loket ini → modal pratinjau → dicetak ke printer struk 58mm (pos.partials.sales-recap-modal).
 * Izin print_sales_recap dicek di server setiap kali dibuka; setiap pembukaan tercatat di Log Aktivitas.
 */
trait PrintsDailySalesRecap
{
    // #[Locked]: diisi server (openSalesRecap) — browser tidak boleh mengganti angka yang dicetak.
    #[Locked]
    public bool $showSalesRecapModal = false;

    #[Locked]
    public ?array $salesRecapData = null;

    /** Loket rekap: PADEL_FRONTDESK (Walk-In & Jual Membership) atau FNB_COUNTER. */
    abstract protected function salesRecapCounter(): string;

    abstract protected function salesRecapNotice(string $level, string $title, string $body): void;

    /** Tanggal rekap (Y-m-d). Bawaan hari ini; halaman dengan filter tanggal riwayat memakai tanggal itu. */
    protected function salesRecapDate(): string
    {
        return now('Asia/Jakarta')->toDateString();
    }

    public function getCanPrintSalesRecapProperty(): bool
    {
        return (bool) auth()->user()?->can(DailySalesRecapService::PERMISSION);
    }

    public function openSalesRecap(): void
    {
        $user = auth()->user();

        if (! $user?->can(DailySalesRecapService::PERMISSION)) {
            ActivityLogger::accessDenied('membuka Rekap Penjualan Harian POS tanpa izin '.DailySalesRecapService::PERMISSION);
            $this->salesRecapNotice('danger', 'Akses Ditolak', 'Anda tidak memiliki izin mencetak rekap penjualan harian.');

            return;
        }

        $service = app(DailySalesRecapService::class);
        $recap = $service->build($this->salesRecapCounter(), $this->salesRecapDate(), $user);

        if ($recap === null) {
            $this->salesRecapNotice('warning', 'Belum Ada Shift', 'Belum ada shift kasir yang dibuka di loket ini pada tanggal tersebut.');

            return;
        }

        $service->recordView($recap, $user);
        $this->salesRecapData = $recap;
        $this->showSalesRecapModal = true;
    }

    public function closeSalesRecap(): void
    {
        $this->showSalesRecapModal = false;
        $this->salesRecapData = null;
    }
}
