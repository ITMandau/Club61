<?php

namespace App\Observers;

use App\Models\Padel\PadelCourt;
use App\Services\Realtime\RealtimeSignal;
use Illuminate\Support\Facades\Cache;

/** Lapangan ditambah / diubah (jam buka, tarif, aktif) / dihapus → semua tanggal di layar jadwal berubah. */
class PadelCourtObserver
{
    public function __construct(private RealtimeSignal $signal) {}

    public function saved(PadelCourt $court): void
    {
        $this->signal->bookingDates('*');
        $this->forgetCourtList();
    }

    public function deleted(PadelCourt $court): void
    {
        $this->signal->bookingDates('*');
        $this->forgetCourtList();
    }

    public function restored(PadelCourt $court): void
    {
        $this->signal->bookingDates('*');
        $this->forgetCourtList();
    }

    /** Dropdown lapangan Kelola Pemesanan (cache 2 menit) langsung ikut urutan / status terbaru. */
    private function forgetCourtList(): void
    {
        Cache::forget('active_padel_courts_list');
    }
}
