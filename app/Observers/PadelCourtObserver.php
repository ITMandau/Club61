<?php

namespace App\Observers;

use App\Models\Padel\PadelCourt;
use App\Services\Realtime\RealtimeSignal;

/** Lapangan ditambah / diubah (jam buka, tarif, aktif) / dihapus → semua tanggal di layar jadwal berubah. */
class PadelCourtObserver
{
    public function __construct(private RealtimeSignal $signal) {}

    public function saved(PadelCourt $court): void
    {
        $this->signal->bookingDates('*');
    }

    public function deleted(PadelCourt $court): void
    {
        $this->signal->bookingDates('*');
    }

    public function restored(PadelCourt $court): void
    {
        $this->signal->bookingDates('*');
    }
}
