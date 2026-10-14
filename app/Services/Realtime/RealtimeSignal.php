<?php

namespace App\Services\Realtime;

use App\Events\BookingBoardChanged;
use App\Events\SignatureChanged;
use App\Models\Padel\PadelBooking;
use App\Support\Realtime;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mengumpulkan sinyal "ada yang berubah" selama satu request / job / command, lalu mengirimnya SEKALI setelah respons
 * terkirim (lihat AppServiceProvider: terminating & Queue::after). Checkout walk-in 3 jam = 1 sinyal, bukan 3.
 *
 * Reverb mati / belum dipasang tidak pernah menggagalkan transaksi: layar otomatis memakai polling cadangan.
 */
class RealtimeSignal
{
    /** Cache hitungan tab Kelola Pemesanan — dihapus tiap ada perubahan booking supaya refresh layar tidak basi. */
    public const TAB_COUNTS_CACHE = 'kelola_pemesanan_tab_counts';

    private const DOWN_WARNING_CACHE = 'realtime:down-warned';

    private const DOWN_UNTIL_CACHE = 'realtime:down-until';

    /** Setelah gagal, sinyal berikutnya baru dicoba lagi sesudah sekian detik. */
    private const RETRY_AFTER_SECONDS = 10;

    /** @var array<string, true> */
    private array $dates = [];

    /** @var array<string, SignatureChanged> */
    private array $signature = [];

    /** Tanggal booking yang berubah (Carbon / "Y-m-d" / "*" = semua tanggal). */
    public function bookingDates(DateTimeInterface|string|null ...$dates): void
    {
        foreach ($dates as $date) {
            if ($date === null || $date === '') {
                continue;
            }
            $this->dates[$date instanceof DateTimeInterface ? $date->format('Y-m-d') : ($date === '*' ? '*' : substr($date, 0, 10))] = true;
        }
    }

    /**
     * Untuk update lewat query builder (tidak memicu observer model): tanggal diambil dari id booking-nya.
     *
     * @param  iterable<string>  $bookingIds
     */
    public function bookings(iterable $bookingIds): void
    {
        $ids = collect($bookingIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }

        $this->bookingDates(...PadelBooking::withTrashed()->whereIn('id', $ids)->distinct()->pluck('booking_date')->all());
    }

    public function signaturePublished(): void
    {
        $this->signature['published'] = new SignatureChanged('published');
    }

    public function signatureScreenSeen(string $screenId, DateTimeInterface $seenAt): void
    {
        $this->signature["seen:{$screenId}"] = new SignatureChanged('seen', $screenId, $seenAt->format(DATE_ATOM));
    }

    /** @return list<string> */
    public function pendingDates(): array
    {
        $dates = array_keys($this->dates);
        sort($dates);

        return isset($this->dates['*']) ? ['*'] : $dates;
    }

    public function flush(): void
    {
        $dates = $this->pendingDates();
        $events = array_values($this->signature);
        $this->dates = [];
        $this->signature = [];

        if ($dates !== []) {
            $this->quietly(fn () => Cache::forget(self::TAB_COUNTS_CACHE));
            array_unshift($events, new BookingBoardChanged($dates));
        }

        // Baru saja gagal → jangan coba lagi dulu: di Windows (php artisan serve) koneksi yang ditolak makan ±2 dtk
        // dan request ikut menunggu. Layar sedang di mode polling cadangan, jadi tidak ada yang terlewat.
        if ($events === [] || ! Realtime::enabled() || $this->quietly(fn () => Cache::has(self::DOWN_UNTIL_CACHE))) {
            return;
        }

        foreach ($events as $event) {
            try {
                event($event);
            } catch (Throwable $e) {
                $this->markDown($e);

                return; // server Reverb tidak terjangkau → sisa sinyal juga pasti gagal
            }
        }
    }

    /** Operasi cache pendukung tidak boleh menggagalkan apa pun (flush jalan di akhir request / job / command). */
    private function quietly(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable) {
            return null;
        }
    }

    private function markDown(Throwable $e): void
    {
        try {
            Cache::put(self::DOWN_UNTIL_CACHE, true, now()->addSeconds(self::RETRY_AFTER_SECONDS));
            if (Cache::add(self::DOWN_WARNING_CACHE, true, now()->addMinutes(5))) {
                Log::warning('Realtime (Reverb) tidak terjangkau — layar memakai polling cadangan sampai Reverb hidup lagi.', ['error' => $e->getMessage()]);
            }
        } catch (Throwable) {
            // Log / cache pun gagal: tetap jangan ganggu request.
        }
    }
}
