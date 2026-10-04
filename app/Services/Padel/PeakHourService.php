<?php

namespace App\Services\Padel;

use App\Models\Padel\PadelCourt;
use App\Models\Padel\PadelHoliday;
use App\Models\Padel\PadelPeakHourRule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * SATU-SATUNYA penentu jam peak (prime) vs reguler padel. Dipakai grid POS Walk-In, Booking System, jadwal &
 * hold booking customer, dan hitungan selisih reschedule — supaya harga yang tampil, yang di-hold, dan yang
 * ditagih selalu sama. Dulu aturan "akhir pekan ATAU jam >= 17" ditulis ulang di 7 tempat.
 *
 * Satu slot = satu jam; slot ikut peak kalau JAM MULAI-nya berada di salah satu rentang peak hari itu.
 * Tanggal merah memakai rentang hari Minggu.
 */
class PeakHourService
{
    public const CACHE_KEY = 'padel_peak_hour_schedule';

    public const HOLIDAY_DAY = 0; // Minggu

    /** @var array{rules: array<int, array<int, array{0: int, 1: int}>>, holidays: array<string, string>}|null */
    private ?array $schedule = null;

    public function isPeak(Carbon $slotStart): bool
    {
        $schedule = $this->schedule();
        $day = isset($schedule['holidays'][$slotStart->format('Y-m-d')]) ? self::HOLIDAY_DAY : $slotStart->dayOfWeek;
        $hour = $slotStart->hour;

        foreach ($schedule['rules'][$day] ?? [] as [$start, $end]) {
            if ($hour >= $start && $hour < $end) {
                return true;
            }
        }

        return false;
    }

    public function hourlyRate(PadelCourt $court, Carbon $slotStart): float
    {
        return $this->isPeak($slotStart) ? (float) $court->hourly_rate_prime : (float) $court->hourly_rate_regular;
    }

    /**
     * Tarif lapangan untuk rentang [start, end) per jam (rentang yang melewati batas reguler/peak dihitung per jam).
     *
     * @return array{fee: float, has_peak: bool}
     */
    public function courtFee(PadelCourt $court, Carbon $start, Carbon $end): array
    {
        $fee = 0.0;
        $hasPeak = false;
        for ($cursor = $start->copy(); $cursor->lt($end); $cursor->addHour()) {
            $peak = $this->isPeak($cursor);
            $hasPeak = $hasPeak || $peak;
            $fee += $peak ? (float) $court->hourly_rate_prime : (float) $court->hourly_rate_regular;
        }

        return ['fee' => $fee, 'has_peak' => $hasPeak];
    }

    public function holidayName(Carbon $date): ?string
    {
        return $this->schedule()['holidays'][$date->format('Y-m-d')] ?? null;
    }

    /** @return array<int, array<int, array{0: int, 1: int}>> rentang [jam mulai, jam selesai) per hari */
    public function rules(): array
    {
        return $this->schedule()['rules'];
    }

    /** Wajib dipanggil setiap kali aturan / tanggal merah diubah. */
    public function flush(): void
    {
        $this->schedule = null;
        Cache::forget(self::CACHE_KEY);
    }

    private function schedule(): array
    {
        if ($this->schedule !== null) {
            return $this->schedule;
        }

        try {
            return $this->schedule = $this->loadSchedule();
        } catch (\Illuminate\Database\QueryException $e) {
            // Tabel belum ada (deploy lupa `php artisan migrate`): pakai aturan lama supaya halaman booking & kasir
            // tetap jalan dengan harga yang sama seperti sebelumnya. Sengaja TIDAK di-cache.
            report($e);

            return [
                'rules' => [0 => [[0, 24]], 1 => [[17, 24]], 2 => [[17, 24]], 3 => [[17, 24]], 4 => [[17, 24]], 5 => [[17, 24]], 6 => [[0, 24]]],
                'holidays' => [],
            ];
        }
    }

    private function loadSchedule(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $rules = [];
            foreach (PadelPeakHourRule::orderBy('day_of_week')->orderBy('start_hour')->get() as $rule) {
                $rules[$rule->day_of_week][] = [$rule->start_hour, $rule->end_hour];
            }

            return [
                'rules' => $rules,
                // Hanya tanggal merah yang relevan (mulai kemarin) — daftar lama tidak perlu ikut dimuat.
                'holidays' => PadelHoliday::where('date', '>=', now()->subDay()->toDateString())
                    ->orderBy('date')->get()
                    ->mapWithKeys(fn (PadelHoliday $h) => [$h->date->format('Y-m-d') => $h->name])
                    ->all(),
            ];
        });
    }
}
