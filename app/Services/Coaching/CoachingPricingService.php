<?php

namespace App\Services\Coaching;

use App\Models\Coaching\CoachingRate;
use App\Models\Coaching\CoachingSetting;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * SATU-SATUNYA rumus harga coaching (Modul 24 Opsi B): layar POS, checkout, & reschedule memakai fungsi yang sama,
 * jadi angka di layar kasir = angka yang dicatat server.
 *
 * Per jam slot: tarif setoran aturan aktif pertama (prioritas terkecil) yang cocok dengan hari & jam mulai jam itu
 * + biaya coach tambahan / jam × jumlah coach tambahan. Jam tanpa aturan tarif → ditolak (tidak pernah jatuh ke 0).
 */
class CoachingPricingService
{
    public const CACHE_KEY = 'coaching_pricing';

    public const CACHE_TTL_SECONDS = 300;

    /** @var array{rates: array<int, array{name: string, days: array<int, int>, start: string, end: string, price: float}>, extra_fee: float}|null */
    private ?array $config = null;

    /**
     * Harga satu rentang slot (jam bulat). Exception 422 kalau ada jam tanpa tarif.
     *
     * @return array{fee: float, hours: int, lines: array<int, array{hour: string, rate: string, price: float, extra: float}>}
     */
    public function feeFor(CarbonInterface $start, CarbonInterface $end, int $extraCoaches): array
    {
        $extraCoaches = max(0, $extraCoaches);
        $extraFee = $this->extraFeePerHour();
        $lines = [];
        $fee = 0.0;

        for ($hour = Carbon::instance($start)->copy(); $hour->lt($end); $hour->addHour()) {
            $rate = $this->rateFor($hour);
            if ($rate === null) {
                throw new HttpException(422, 'Tarif coaching untuk '.$hour->translatedFormat('l').' jam '.$hour->format('H:i')
                    .' belum diatur. Tambahkan di menu Coaching → Tarif Setoran.');
            }
            $extra = round($extraFee * $extraCoaches, 2);
            $lines[] = ['hour' => $hour->format('Y-m-d H:i'), 'rate' => $rate['name'], 'price' => $rate['price'], 'extra' => $extra];
            $fee += $rate['price'] + $extra;
        }

        return ['fee' => round($fee, 2), 'hours' => count($lines), 'lines' => $lines];
    }

    /**
     * Harga beberapa slot kasir (court_id, start_time, end_time "H:i" di $date).
     *
     * @param  array<int, array{court_id: string, start_time: string, end_time: string}>  $slots
     * @return array{total: float, slots: array<int, array{court_id: string, start_time: string, end_time: string, fee: float, hours: int, lines: array}>}
     */
    public function quoteSlots(array $slots, string $date, int $extraCoaches): array
    {
        $total = 0.0;
        $out = [];
        foreach ($slots as $slot) {
            [$start, $end] = self::slotRange($date, (string) $slot['start_time'], (string) $slot['end_time']);
            $quote = $this->feeFor($start, $end, $extraCoaches);
            $out[] = ['court_id' => (string) $slot['court_id'], 'start_time' => (string) $slot['start_time'], 'end_time' => (string) $slot['end_time']] + $quote;
            $total += $quote['fee'];
        }

        return ['total' => round($total, 2), 'slots' => $out];
    }

    /** Aturan aktif yang berlaku untuk jam mulai ini (null = belum ada tarif). */
    public function rateFor(CarbonInterface $hour): ?array
    {
        $day = (int) $hour->isoWeekday();
        $time = $hour->format('H:i');
        foreach ($this->config()['rates'] as $rate) {
            if (in_array($day, $rate['days'], true) && $time >= $rate['start'] && $time < $rate['end']) {
                return $rate;
            }
        }

        return null;
    }

    public function extraFeePerHour(): float
    {
        return $this->config()['extra_fee'];
    }

    /** @return array{0: Carbon, 1: Carbon} rentang slot; jam selesai ≤ jam mulai (mis. "00:00") = hari berikutnya. */
    public static function slotRange(string $date, string $startTime, string $endTime): array
    {
        $start = Carbon::parse($date.' '.substr($startTime, 0, 5));
        $end = in_array(substr($endTime, 0, 5), ['24:00', '00:00'], true)
            ? Carbon::parse($date)->addDay()->startOfDay()
            : Carbon::parse($date.' '.substr($endTime, 0, 5));
        if ($end->lte($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }

    public function flush(): void
    {
        $this->config = null;
        Cache::forget(self::CACHE_KEY);
    }

    private function config(): array
    {
        if ($this->config !== null) {
            return $this->config;
        }

        try {
            return $this->config = Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, fn () => [
                'rates' => CoachingRate::query()->where('is_active', true)->orderBy('priority')->orderBy('start_time')->get()
                    ->map(fn (CoachingRate $r) => [
                        'name' => $r->name,
                        'days' => array_values(array_map('intval', (array) $r->days)),
                        'start' => substr((string) $r->start_time, 0, 5),
                        'end' => substr((string) $r->end_time, 0, 5),
                        'price' => (float) $r->price_per_hour,
                    ])->all(),
                'extra_fee' => (float) CoachingSetting::current()->extra_coach_fee_per_hour,
            ]);
        } catch (QueryException $e) {
            // Tabel belum dimigrasi: tidak ada tarif → booking coaching ditolak, booking reguler tidak terpengaruh.
            report($e);

            return $this->config = ['rates' => [], 'extra_fee' => 0.0];
        }
    }
}
