<?php

namespace App\Observers;

use App\Models\Padel\PadelBooking;
use App\Services\Realtime\RealtimeSignal;

/**
 * Booking dibuat / diubah / dihapus lewat Eloquent → layar jadwal tanggal itu diberi tahu. Update lewat query builder
 * (PadelBooking::whereKey(...)->update()) TIDAK lewat sini — titik-titik itu memanggil RealtimeSignal sendiri.
 */
class PadelBookingObserver
{
    /** Perubahan yang tidak terlihat di layar mana pun (perpanjangan batas bayar). */
    private const SILENT = ['expires_at', 'updated_at'];

    public function __construct(private RealtimeSignal $signal) {}

    public function created(PadelBooking $booking): void
    {
        $this->signal->bookingDates($booking->booking_date);
    }

    public function updated(PadelBooking $booking): void
    {
        if (array_diff(array_keys($booking->getChanges()), self::SILENT) === []) {
            return;
        }

        // Pindah jadwal: tanggal lama ikut diberi tahu (slotnya kosong lagi).
        $this->signal->bookingDates($booking->booking_date, $booking->wasChanged('booking_date') ? $booking->getOriginal('booking_date') : null);
    }

    public function deleted(PadelBooking $booking): void
    {
        $this->signal->bookingDates($booking->booking_date);
    }

    public function restored(PadelBooking $booking): void
    {
        $this->signal->bookingDates($booking->booking_date);
    }
}
