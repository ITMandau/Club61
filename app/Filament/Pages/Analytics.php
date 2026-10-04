<?php

namespace App\Filament\Pages;

use App\Models\Finance\LedgerEntry;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Services\Finance\LedgerAnalytics;
use App\Services\Finance\LedgerReport;
use App\Services\Padel\PadelBookingService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Pages\Page;
use Livewire\Attributes\Locked;
use UnitEnum;

/**
 * Modul 17 Fase 3 — Analytics & Keuangan membaca dari Buku Transaksi (ledger_entries). Dulu omzet dihitung dari
 * padel_bookings (F&B tidak terhitung sama sekali, refund PENDING ikut mengurangi, tanggal memakai created_at
 * booking) sehingga angkanya tidak pernah sama dengan Buku Transaksi.
 */
class Analytics extends Page
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Analytics & Keuangan';

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?string $title = 'Laporan Uang Masuk & Analytics';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.analytics';

    /** Status booking yang memakai slot lapangan (okupansi). */
    private const OCCUPYING_STATUSES = ['PAID', 'CHECKED_IN', 'COMPLETED', 'EXPIRED'];

    /** Hanya diubah lewat setPreset() — nilai kiriman browser yang diubah manual ditolak Livewire. */
    #[Locked]
    public string $preset = LedgerReport::DEFAULT_PRESET;

    public ?string $dari = null;

    public ?string $sampai = null;

    public function setPreset(string $preset): void
    {
        $this->preset = array_key_exists($preset, LedgerReport::PRESETS) ? $preset : LedgerReport::DEFAULT_PRESET;
    }

    /**
     * Tanggal kustom dari input browser: selain Y-m-d yang valid diabaikan (teks bebas dulu → Carbon::parse error 500).
     *
     * @return array{0: ?string, 1: ?string}
     */
    public function customDates(): array
    {
        $date = fn (?string $v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && checkdate((int) substr($v, 5, 2), (int) substr($v, 8, 2), (int) substr($v, 0, 4)) ? $v : null;

        return [$date($this->dari), $date($this->sampai)];
    }

    /** @return array{0: ?string, 1: ?string} */
    public function range(): array
    {
        return LedgerReport::resolveRange($this->preset, ...$this->customDates());
    }

    /** Tautan ke Buku Transaksi dengan periode yang sama (+ filter rincian yang diklik). */
    public function bukuUrl(array $filters = []): string
    {
        [$dari, $sampai] = $this->customDates();

        return BukuTransaksi::getUrl(array_filter([
            'periode' => $this->preset,
            'dari' => $this->preset === 'kustom' ? $dari : null,
            'sampai' => $this->preset === 'kustom' ? $sampai : null,
        ] + $filters, fn ($v) => filled($v)));
    }

    protected function getViewData(): array
    {
        // Sinkronkan status tiket (PAID lewat jadwal → EXPIRED/COMPLETED) sebelum menghitung okupansi.
        app(PadelBookingService::class)->syncExpiredAndCompletedBookings();

        [$from, $until] = $this->range();
        $base = LedgerReport::applyDateRange(LedgerEntry::query(), $from, $until);

        $latest = LedgerReport::orderNewestFirst(LedgerReport::grouped(clone $base))->limit(10)->get();

        return [
            'periodLabel' => BukuTransaksi::periodLabel($this->preset, ...$this->customDates()),
            'summary' => LedgerReport::summary(clone $base, $from, $until),
            'byCategory' => LedgerAnalytics::breakdown($base, 'category'),
            'bySource' => LedgerAnalytics::breakdown($base, 'source'),
            'byMethod' => LedgerAnalytics::breakdown($base, 'method'),
            'trend' => LedgerAnalytics::trend($base, $from, $until),
            'occupancy' => $this->occupancy($from, $until),
            'memberUsage' => $this->memberUsage($from, $until),
            'latest' => $latest,
            'canSeeRefundQueue' => AntrianRefund::canAccess(),
        ];
    }

    /**
     * Okupansi = jam terpakai ÷ (jam buka lapangan aktif × jumlah hari). Dulu kapasitas ditulis tetap 4 lapangan × 18 jam
     * dan "semua waktu" dianggap 30 hari.
     *
     * @return array{rate: float, hours_booked: float, capacity_hours: float, courts: int}
     */
    private function occupancy(?string $from, ?string $until): array
    {
        $bookings = PadelBooking::query()->whereIn('status', self::OCCUPYING_STATUSES)
            ->when($from, fn ($q) => $q->whereDate('booking_date', '>=', $from))
            ->when($until, fn ($q) => $q->whereDate('booking_date', '<=', $until));

        $hoursBooked = 0.0;
        foreach ((clone $bookings)->get(['start_time', 'end_time']) as $b) {
            $hoursBooked += max(0, $b->start_time->diffInMinutes($b->end_time)) / 60;
        }

        $start = $from ?? (clone $bookings)->min('booking_date');
        $end = $until ?? Carbon::now(LedgerReport::TIMEZONE)->toDateString();
        $days = $start ? max(1, (int) Carbon::parse($start)->startOfDay()->diffInDays(Carbon::parse($end)->startOfDay()) + 1) : 1;

        $courts = PadelCourt::query()->where('is_active', true)->get(['open_time', 'close_time']);
        $hoursPerDay = $courts->sum(function (PadelCourt $court) {
            $open = Carbon::createFromTimeString($court->open_time ?: '06:00');
            $close = Carbon::createFromTimeString(in_array($court->close_time, [null, '', '00:00', '24:00'], true) ? '23:59' : $court->close_time);

            return max(0, $open->diffInMinutes($close) + ($close->format('H:i') === '23:59' ? 1 : 0)) / 60;
        });
        $capacity = $hoursPerDay * $days;

        return [
            'rate' => $capacity > 0 ? round($hoursBooked / $capacity * 100, 1) : 0.0,
            'hours_booked' => round($hoursBooked, 1),
            'capacity_hours' => round($capacity, 1),
            'courts' => $courts->count(),
        ];
    }

    /** Pemakaian kuota membership di lapangan (informasi utilisasi — bukan uang masuk baru). */
    private function memberUsage(?string $from, ?string $until): array
    {
        $row = PadelBooking::query()->whereIn('status', self::OCCUPYING_STATUSES)
            ->when($from, fn ($q) => $q->whereDate('booking_date', '>=', $from))
            ->when($until, fn ($q) => $q->whereDate('booking_date', '<=', $until))
            ->toBase()
            ->selectRaw('COALESCE(SUM(member_discount_court), 0) as value, COALESCE(SUM(member_hours_consumed), 0) as hours,
                COUNT(CASE WHEN membership_balance_id IS NOT NULL THEN 1 END) as bookings')
            ->first();

        return ['value' => (float) $row->value, 'hours' => (float) $row->hours, 'bookings' => (int) $row->bookings];
    }

    public static function rupiah(float|int|null $value): string
    {
        return BukuTransaksi::rupiah($value);
    }
}
