<?php

namespace App\Models\Padel;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan satu minggu × satu lapangan dari Jadwal Tetap Mingguan. occurrence_date = tanggal ASLI jadwal (booking yang
 * di-reschedule ke tanggal lain tetap tercatat di sini, jadi tidak dibuat ulang).
 *   PENDING = baru dicatat, booking sedang dibuat;
 *   BOOKED  = booking sudah dibuat (booking_id);
 *   SKIPPED = dilewati admin (mis. lapangan dipakai acara) — tidak pernah dibuat;
 *   FAILED  = gagal dibuat (bentrok / kuota habis / membership berakhir) — dicoba lagi di putaran berikutnya.
 */
class PadelRecurringOccurrence extends Model
{
    use HasUlids;

    /** Baru dicatat, booking sedang dibuat. */
    public const STATUS_PENDING = 'PENDING';

    public const STATUS_BOOKED = 'BOOKED';

    public const STATUS_SKIPPED = 'SKIPPED';

    public const STATUS_FAILED = 'FAILED';

    protected $fillable = [
        'recurring_schedule_id',
        'court_id',
        'occurrence_date',
        'booking_id',
        'status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'occurrence_date' => 'date:Y-m-d',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(PadelRecurringSchedule::class, 'recurring_schedule_id');
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(PadelCourt::class, 'court_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(PadelBooking::class, 'booking_id');
    }
}
