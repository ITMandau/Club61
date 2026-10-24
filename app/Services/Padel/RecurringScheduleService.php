<?php

namespace App\Services\Padel;

use App\Models\Membership\UserMembership;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Padel\PadelRecurringOccurrence;
use App\Models\Padel\PadelRecurringSchedule;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Jadwal Tetap Mingguan: membuat booking tiap minggu (paling jauh weeks_ahead minggu ke depan) lewat
 * PadelBookingService::createRecurringBooking, mencatat hasilnya per (lapangan, tanggal) di padel_recurring_occurrences,
 * dan melewati tanggal tertentu (mis. lapangan dipakai acara). Dipanggil tombol admin & perintah terjadwal harian
 * (padel:generate-recurring). Gagal (bentrok / kuota habis / membership berakhir) dicatat FAILED dan dicoba lagi.
 */
class RecurringScheduleService
{
    public function __construct(private PadelBookingService $bookings) {}

    /**
     * Membership aktif yang punya kuota jam PADEL — pilihan "pemilik kuota" di form (corporate & individu).
     *
     * @return array<string, string> [user_membership_id => label]
     */
    public function membershipOptions(?string $includeId = null): array
    {
        return UserMembership::with(['user', 'plan', 'sponsorOrganization', 'balances'])
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('status', 'ACTIVE')
                ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', now()->toDateString())))
                ->when($includeId, fn ($q) => $q->orWhere('id', $includeId)))
            ->whereHas('balances', fn ($q) => $q->where('facility', 'PADEL')->where('quota_type', 'HOURS'))
            ->get()
            ->mapWithKeys(function (UserMembership $m) {
                $balance = $m->balanceFor('PADEL');
                $owner = $m->sponsorOrganization
                    ? 'Corporate · '.$m->sponsorOrganization->name
                    : 'Member · '.($m->user?->name ?? '-');

                return [$m->id => $owner.' — '.($m->plan?->name ?? 'Paket').' (sisa '.self::hours((float) $balance?->remaining_quota).' jam)'];
            })
            ->sort()
            ->all();
    }

    /**
     * Rapikan & periksa isian form jadwal di server (isian Livewire bisa direkayasa).
     *
     * @throws HttpException 422 dengan pesan untuk admin
     */
    public function normalize(array $data, ?PadelRecurringSchedule $record = null): array
    {
        $fail = fn (string $message) => throw new HttpException(422, $message);

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) {
            $fail('Nama jadwal wajib diisi (maks. 100 karakter).');
        }

        $membershipId = (string) ($data['user_membership_id'] ?? $record?->user_membership_id);
        if ($record && $record->user_membership_id !== $membershipId && $record->occurrences()->exists()) {
            $fail('Pemilik kuota tidak bisa diganti setelah booking dibuat. Buat jadwal baru.');
        }
        if (! array_key_exists($membershipId, $this->membershipOptions($record?->user_membership_id))) {
            $fail('Pilih membership aktif yang punya kuota jam Padel.');
        }

        $day = (int) ($data['day_of_week'] ?? 0);
        if (! isset(PadelRecurringSchedule::DAYS[$day])) {
            $fail('Hari tidak valid.');
        }

        $start = (string) ($data['start_time'] ?? '');
        $end = (string) ($data['end_time'] ?? '');
        if (! preg_match('/^([01]\d|2[0-3]):00$/', $start) || ! preg_match('/^(([01]\d|2[0-3]):00|24:00)$/', $end)
            || PadelRecurringSchedule::hourOf($end) <= PadelRecurringSchedule::hourOf($start)) {
            $fail('Jam main harus jam bulat dan jam selesai setelah jam mulai.');
        }

        $courtIds = array_values(array_unique(array_map('strval', (array) ($data['court_ids'] ?? []))));
        $courts = PadelCourt::whereIn('id', $courtIds)->where('is_active', true)->get();
        if ($courtIds === [] || $courts->count() !== count($courtIds)) {
            $fail('Pilih minimal satu lapangan aktif.');
        }
        foreach ($courts as $court) {
            $open = (int) substr($court->open_time ?: '06:00', 0, 2);
            $closeVal = $court->close_time ?: '23:00';
            $close = in_array($closeVal, ['00:00', '24:00'], true) ? 24 : (int) substr($closeVal, 0, 2);
            if (PadelRecurringSchedule::hourOf($start) < $open || PadelRecurringSchedule::hourOf($end) > $close) {
                $fail("Jam main di luar jam operasional {$court->name} ({$court->open_time} - {$court->close_time}).");
            }
        }

        try {
            $startsOn = Carbon::parse((string) ($data['starts_on'] ?? ''))->toDateString();
            $endsOn = filled($data['ends_on'] ?? null) ? Carbon::parse((string) $data['ends_on'])->toDateString() : null;
        } catch (\Throwable) {
            $fail('Tanggal tidak valid.');
        }
        if ($endsOn !== null && $endsOn < $startsOn) {
            $fail('Tanggal berakhir harus sama atau setelah tanggal mulai.');
        }

        $weeks = (int) ($data['weeks_ahead'] ?? 0);
        if ($weeks < 1 || $weeks > PadelRecurringSchedule::MAX_WEEKS_AHEAD) {
            $fail('Booking dibuat 1 sampai '.PadelRecurringSchedule::MAX_WEEKS_AHEAD.' minggu ke depan.');
        }

        return [
            'name' => $name,
            'user_membership_id' => $membershipId,
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => $end,
            'court_ids' => $courtIds,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'weeks_ahead' => $weeks,
            'notes' => filled($data['notes'] ?? null) ? mb_substr(trim((string) $data['notes']), 0, 1000) : null,
        ];
    }

    /** Isi form dari data tersimpan. */
    public static function formState(PadelRecurringSchedule $schedule): array
    {
        return $schedule->only(['name', 'user_membership_id', 'day_of_week', 'start_time', 'end_time', 'court_ids', 'weeks_ahead', 'notes']) + [
            'starts_on' => $schedule->starts_on?->toDateString(),
            'ends_on' => $schedule->ends_on?->toDateString(),
        ];
    }

    /**
     * Buat booking minggu-minggu yang belum dibuat (termasuk yang sebelumnya gagal).
     *
     * @return array{booked: int, failed: array<int, string>, busy: bool}
     */
    public function generate(PadelRecurringSchedule $schedule, ?User $actor = null): array
    {
        $result = ['booked' => 0, 'failed' => [], 'busy' => false];
        if (! $schedule->isActive()) {
            return $result;
        }

        // Satu proses per jadwal (tombol admin & perintah terjadwal bersamaan): yang kedua cukup dilewati.
        $lock = Cache::lock('padel-recurring:'.$schedule->id, 300);
        if (! $lock->get()) {
            $result['busy'] = true;

            return $result;
        }

        try {
            $actor ??= $this->systemActor($schedule);
            if (! $actor) {
                $result['failed'][] = 'Tidak ada akun staf untuk mencatat booking.';

                return $result;
            }

            $courts = PadelCourt::whereIn('id', (array) $schedule->court_ids)->get()->keyBy('id');

            foreach ($this->dueDates($schedule) as $date) {
                foreach ((array) $schedule->court_ids as $courtId) {
                    $occurrence = $this->occurrenceFor($schedule, $courtId, $date);
                    if (! in_array($occurrence->status, [PadelRecurringOccurrence::STATUS_PENDING, PadelRecurringOccurrence::STATUS_FAILED], true)) {
                        continue;
                    }

                    $label = $date->translatedFormat('d M Y').' · '.($courts[$courtId]->name ?? 'Lapangan');
                    try {
                        $this->bookings->createRecurringBooking($schedule, $courtId, $date->toDateString(), $actor);
                        $result['booked']++;
                    } catch (\Throwable $e) {
                        if (! $e instanceof HttpException) {
                            report($e);
                        }
                        $note = $e instanceof HttpException ? $e->getMessage() : 'Gagal dibuat (kesalahan sistem).';
                        // Jangan menimpa minggu yang ternyata sudah BOOKED oleh proses lain.
                        PadelRecurringOccurrence::whereKey($occurrence->id)
                            ->whereIn('status', [PadelRecurringOccurrence::STATUS_PENDING, PadelRecurringOccurrence::STATUS_FAILED])
                            ->update(['status' => PadelRecurringOccurrence::STATUS_FAILED, 'note' => mb_substr($note, 0, 255)]);
                        $result['failed'][] = $label.': '.$note;
                    }
                }
            }
        } finally {
            $lock->release();
        }

        if ($result['booked'] > 0 || $result['failed'] !== []) {
            ActivityLogger::record(
                module: 'PADEL',
                event: 'recurring.generated',
                description: "Jadwal Tetap \"{$schedule->name}\": {$result['booked']} booking dibuat".($result['failed'] !== [] ? ', '.count($result['failed']).' gagal' : ''),
                subject: $schedule,
                meta: array_filter([
                    'jadwal' => $schedule->scheduleLabel(),
                    'booking_dibuat' => $result['booked'],
                    'gagal' => $result['failed'] !== [] ? implode(' | ', array_slice($result['failed'], 0, 10)) : null,
                ], fn ($v) => $v !== null),
                causer: $actor,
            );
        }

        return $result;
    }

    /** Semua jadwal aktif — perintah terjadwal harian. */
    public function generateAll(): array
    {
        $summary = ['schedules' => 0, 'booked' => 0, 'failed' => 0];
        foreach (PadelRecurringSchedule::where('status', PadelRecurringSchedule::STATUS_ACTIVE)->get() as $schedule) {
            $r = $this->generate($schedule);
            $summary['schedules']++;
            $summary['booked'] += $r['booked'];
            $summary['failed'] += count($r['failed']);
        }

        return $summary;
    }

    /**
     * Lewati satu tanggal (semua lapangan jadwal ini). Booking yang sudah dibuat dibatalkan lewat jalur pembatalan
     * biasa (kuota jam kembali) — hanya kalau jam mainnya belum mulai.
     *
     * @return int jumlah booking yang dibatalkan
     */
    public function skip(PadelRecurringSchedule $schedule, string $date, string $reason, User $actor): int
    {
        $day = Carbon::parse($date)->startOfDay();
        if ($day->dayOfWeekIso !== $schedule->day_of_week) {
            throw new HttpException(422, 'Tanggal itu bukan hari '.PadelRecurringSchedule::DAYS[$schedule->day_of_week].'.');
        }
        if (Carbon::parse($day->toDateString().' '.$schedule->start_time)->lte(now())) {
            throw new HttpException(422, 'Jam main tanggal itu sudah dimulai / lewat, jadi tidak bisa dilewati.');
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new HttpException(422, 'Alasan wajib diisi.');
        }

        // Cek dulu semua lapangan supaya tanggal ini tidak dilewati setengah jalan.
        $started = PadelBooking::where('recurring_schedule_id', $schedule->id)->whereDate('booking_date', $day->toDateString())
            ->whereIn('status', ['CHECKED_IN', 'COMPLETED'])->value('booking_code');
        if ($started) {
            throw new HttpException(422, "Booking {$started} sudah check-in / selesai — tanggal ini tidak bisa dilewati.");
        }

        $cancelled = 0;
        foreach ((array) $schedule->court_ids as $courtId) {
            $occurrence = $this->occurrenceFor($schedule, $courtId, $day);
            if ($occurrence->status === PadelRecurringOccurrence::STATUS_SKIPPED) {
                continue;
            }

            if ($occurrence->status === PadelRecurringOccurrence::STATUS_BOOKED && $occurrence->booking_id) {
                $booking = PadelBooking::find($occurrence->booking_id);
                // Booking yang sudah di-reschedule ke tanggal lain tidak ikut dibatalkan — tanggal ini sudah kosong.
                if ($booking && $booking->booking_date->toDateString() !== $day->toDateString()) {
                    $booking = null;
                }
                if ($booking && in_array($booking->status, ['PAID', 'LOCKED'], true)) {
                    // Jalur pembatalan Kelola Pemesanan: booking Rp0 langsung CANCELLED + kuota jam kembali.
                    $this->bookings->requestCancelAndRefund($booking->id, 'JADWAL_TETAP_DILEWATI', $reason, $actor);
                    $cancelled++;
                }
            }

            $occurrence->update(['status' => PadelRecurringOccurrence::STATUS_SKIPPED, 'note' => mb_substr($reason, 0, 255)]);
        }

        ActivityLogger::record(
            module: 'PADEL',
            event: 'recurring.skipped',
            description: "Jadwal Tetap \"{$schedule->name}\" dilewati tanggal ".$day->translatedFormat('d M Y').($cancelled ? " ({$cancelled} booking dibatalkan, kuota jam dikembalikan)" : ''),
            subject: $schedule,
            meta: ['tanggal' => $day->toDateString(), 'alasan' => $reason, 'booking_dibatalkan' => $cancelled],
            causer: $actor,
        );

        return $cancelled;
    }

    /** Batalkan "lewati": tanggal itu dibuatkan booking lagi di putaran berikutnya (kalau masih dalam jangkauan). */
    public function unskip(PadelRecurringSchedule $schedule, string $date, User $actor): int
    {
        $count = PadelRecurringOccurrence::where('recurring_schedule_id', $schedule->id)
            ->whereDate('occurrence_date', $date)
            ->where('status', PadelRecurringOccurrence::STATUS_SKIPPED)
            ->delete();

        if ($count > 0) {
            ActivityLogger::record(
                module: 'PADEL',
                event: 'recurring.unskipped',
                description: "Jadwal Tetap \"{$schedule->name}\" tanggal ".Carbon::parse($date)->translatedFormat('d M Y').' tidak jadi dilewati',
                subject: $schedule,
                meta: ['tanggal' => $date],
                causer: $actor,
            );
        }

        return $count;
    }

    /**
     * Tanggal main yang sudah waktunya dibuatkan booking: mulai hari ini / starts_on, sampai weeks_ahead minggu ke depan
     * / ends_on. Jam main yang sudah lewat tidak dibuat.
     *
     * @return Collection<int, Carbon>
     */
    public function dueDates(PadelRecurringSchedule $schedule): Collection
    {
        $today = now()->startOfDay();
        $from = $schedule->starts_on->copy()->startOfDay();
        $from = $from->lt($today) ? $today : $from;
        $until = $today->copy()->addWeeks(max(1, min(PadelRecurringSchedule::MAX_WEEKS_AHEAD, (int) $schedule->weeks_ahead)));
        if ($schedule->ends_on && $schedule->ends_on->copy()->startOfDay()->lt($until)) {
            $until = $schedule->ends_on->copy()->startOfDay();
        }

        $dates = collect();
        for ($date = $schedule->firstDateOnOrAfter($from); $date->lte($until); $date = $date->copy()->addWeek()) {
            if (Carbon::parse($date->toDateString().' '.$schedule->start_time)->gt(now())) {
                $dates->push($date->copy());
            }
        }

        return $dates;
    }

    /** Perkiraan minggu yang masih tertutup sisa kuota (null = kuota tidak terbaca). */
    public function weeksLeft(PadelRecurringSchedule $schedule): ?array
    {
        $balance = $schedule->padelBalance();
        $perWeek = $schedule->hoursPerWeek();
        if (! $balance || $perWeek <= 0) {
            return null;
        }
        $remaining = (float) $balance->remaining_quota;

        return [
            'remaining' => $remaining,
            'weeks' => (int) floor($remaining / $perWeek),
            'leftover' => round($remaining - floor($remaining / $perWeek) * $perWeek, 2),
        ];
    }

    private function occurrenceFor(PadelRecurringSchedule $schedule, string $courtId, Carbon $date): PadelRecurringOccurrence
    {
        $keys = ['recurring_schedule_id' => $schedule->id, 'court_id' => $courtId, 'occurrence_date' => $date->toDateString()];
        $find = fn () => PadelRecurringOccurrence::where('recurring_schedule_id', $schedule->id)->where('court_id', $courtId)
            ->whereDate('occurrence_date', $date->toDateString())->first();

        try {
            return $find() ?? PadelRecurringOccurrence::create($keys + ['status' => PadelRecurringOccurrence::STATUS_PENDING]);
        } catch (UniqueConstraintViolationException) {
            return $find();
        }
    }

    /** Pencatat booking dari perintah terjadwal: pembuat jadwal, atau super_admin pertama kalau akunnya sudah tidak ada. */
    private function systemActor(PadelRecurringSchedule $schedule): ?User
    {
        return $schedule->createdBy ?? User::role('super_admin')->orderBy('created_at')->first();
    }

    private static function hours(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }
}
