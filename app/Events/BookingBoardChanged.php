<?php

namespace App\Events;

use App\Support\Realtime;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Sinyal "jadwal tanggal ini berubah" untuk papan jadwal, TV, POS, Kelola Pemesanan & halaman Book Court. Hanya berisi
 * tanggal — tanpa nama / kode booking — jadi aman di channel publik; tiap layar mengambil ulang datanya sendiri.
 */
class BookingBoardChanged implements ShouldBroadcastNow
{
    /** @param  list<string>  $dates  Y-m-d, atau "*" = semua tanggal (mis. jam buka lapangan diubah). */
    public function __construct(public readonly array $dates) {}

    public function broadcastOn(): Channel
    {
        return new Channel(Realtime::BOARD_CHANNEL);
    }

    public function broadcastAs(): string
    {
        return Realtime::BOARD_EVENT;
    }

    public function broadcastWith(): array
    {
        return ['dates' => $this->dates];
    }
}
