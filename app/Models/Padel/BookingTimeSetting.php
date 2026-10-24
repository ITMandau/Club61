<?php

namespace App\Models\Padel;

use App\Services\Padel\BookingTimeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Satu baris pengaturan: berapa lama slot ditahan sebelum checkout, dan berapa lama customer boleh membayar
 * online setelah klik bayar, dan batas reschedule (jam sebelum main, semua booking). Dibaca lewat BookingTimeService
 * (tervalidasi & ter-cache).
 */
class BookingTimeSetting extends Model
{
    protected $fillable = ['slot_hold_minutes', 'payment_window_minutes', 'reschedule_cutoff_hours'];

    protected function casts(): array
    {
        return [
            'slot_hold_minutes' => 'integer',
            'payment_window_minutes' => 'integer',
            'reschedule_cutoff_hours' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function () {
            throw new \LogicException('Pengaturan waktu booking tidak boleh dihapus.');
        });

        // Flush setelah commit — lihat catatan yang sama di OnlinePaymentMethod.
        static::saved(fn () => DB::afterCommit(fn () => app(BookingTimeService::class)->flush()));
    }
}
