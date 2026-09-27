<?php

namespace App\Models\Sponsor;

use App\Models\Padel\PadelBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SponsorAccessSchedule extends Model
{
    use HasUlids;

    protected $fillable = [
        'sponsor_organization_id',
        'valid_from',
        'valid_until',
        'days_of_week',
        'time_start',
        'time_end',
        'max_concurrent_courts',
        'created_by_user_id',
    ];

    protected $casts = [
        'valid_from' => 'date:Y-m-d',
        'valid_until' => 'date:Y-m-d',
        'days_of_week' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(SponsorOrganization::class, 'sponsor_organization_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Apakah aturan ini mengizinkan booking pada tanggal & jam tertentu. Seluruh rentang
     * booking (mulai s.d. selesai) wajib berada di dalam jendela waktu aturan ini
     * (all-or-nothing, sama seperti pengecekan time_window membership individual).
     */
    public function covers(Carbon $bookingDate, string $bookingStartTime, string $bookingEndTime): bool
    {
        $dateStr = $bookingDate->format('Y-m-d');

        if ($dateStr < $this->valid_from->format('Y-m-d') || $dateStr > $this->valid_until->format('Y-m-d')) {
            return false;
        }

        if ($this->days_of_week && ! in_array($bookingDate->dayOfWeekIso, $this->days_of_week, true)) {
            return false;
        }

        return $bookingStartTime >= (string) $this->time_start && $bookingEndTime <= (string) $this->time_end;
    }

    /**
     * Berapa booking sponsor ini (di lapangan manapun) yang jam-nya BENTROK (overlap) dengan
     * rentang waktu yang diminta, pada tanggal yang sama. Dipakai untuk menegakkan
     * max_concurrent_courts — supaya 1 sponsor tidak menyapu semua lapangan sekaligus di jam
     * yang sama lewat booking gratis, walau jam itu sendiri sudah diizinkan oleh covers().
     */
    public function countOverlappingBookings(Carbon $bookingDate, string $bookingStartTime, string $bookingEndTime): int
    {
        return PadelBooking::where('sponsor_organization_id', $this->sponsor_organization_id)
            ->where('booking_date', $bookingDate->format('Y-m-d'))
            ->whereNotIn('status', ['CANCELLED'])
            ->whereTime('start_time', '<', $bookingEndTime)
            ->whereTime('end_time', '>', $bookingStartTime)
            ->count();
    }

    /**
     * Apakah masih ada slot lapangan tersisa buat sponsor ini di jam tsb, mengacu ke
     * max_concurrent_courts (null = tanpa batas, selalu true).
     */
    public function hasCapacityFor(Carbon $bookingDate, string $bookingStartTime, string $bookingEndTime): bool
    {
        if ($this->max_concurrent_courts === null) {
            return true;
        }

        return $this->countOverlappingBookings($bookingDate, $bookingStartTime, $bookingEndTime) < $this->max_concurrent_courts;
    }
}
