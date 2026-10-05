<?php

namespace App\Filament\Pages;

use App\Models\Finance\LedgerEntry;
use App\Models\Padel\PadelBooking;
use App\Services\Finance\LedgerAnalytics;
use App\Services\Finance\LedgerReport;
use App\Services\Padel\CourtOccupancy;
use App\Services\Padel\PadelBookingService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
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

    /** Hanya diubah lewat setPreset() — nilai kiriman browser yang diubah manual ditolak Livewire. */
    /**
     * Lini layanan di kartu "Rincian Pendapatan" (kode = kategori Buku Transaksi). `soon` = modulnya belum ada,
     * ditandai "Menyusul" selama belum ada transaksi. Coaching belum punya kategori sendiri di buku.
     */
    public const SERVICE_LINES = [
        'SEWA_LAPANGAN' => ['label' => 'Sewa Lapangan Padel (Court Rental)', 'sub' => 'Sewa slot jam lapangan padel', 'soon' => false],
        'ADDON_PADEL' => ['label' => 'Sewa Alat & Add-on Bola (Equipment)', 'sub' => 'Sewa raket, bola & perlengkapan padel', 'soon' => false],
        'FNB' => ['label' => 'Food & Beverage (F&B)', 'sub' => 'Penjualan kafe / bar dari POS F&B', 'soon' => false],
        'WELLNESS' => ['label' => 'Wellness & Sauna', 'sub' => 'Akses sauna di luar paket membership', 'soon' => true],
        'COACHING' => ['label' => 'Pelatih & Coaching Session', 'sub' => 'Sesi privat / kelas bersama pelatih', 'soon' => true],
    ];

    /** Sumber uang yang dibayar online lewat Midtrans; sumber lain = dibayar di kasir. */
    public const ONLINE_SOURCES = ['ONLINE_PADEL', 'RESCHEDULE_DELTA_ONLINE', 'ONLINE_MEMBERSHIP'];

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
        $byCategory = collect(LedgerAnalytics::breakdown($base, 'category'))->keyBy('key');
        $summary = LedgerReport::summary(clone $base, $from, $until);
        $membership = (float) ($byCategory['MEMBERSHIP']['money_net'] ?? 0);

        return [
            'periodLabel' => BukuTransaksi::periodLabel($this->preset, ...$this->customDates()),
            'summary' => $summary,
            'channels' => $this->channels(LedgerAnalytics::breakdown($base, 'source')),
            'byMethod' => LedgerAnalytics::breakdown($base, 'method'),
            'serviceLines' => $this->serviceLines($byCategory->all()),
            'membership' => ['sales' => $membership, 'others' => $summary['money_net'] - $membership],
            'occupancy' => CourtOccupancy::calculate($from, $until),
            'memberUsage' => $this->memberUsage($from, $until),
            'latest' => $latest,
            'canSeeRefundQueue' => AntrianRefund::canAccess(),
        ];
    }

    /**
     * Uang masuk bersih per kanal: kasir (semua POS) vs online (Midtrans).
     *
     * @param  list<array<string, mixed>>  $bySource
     * @return array{cashier: array{money_net: float, transactions: int}, online: array{money_net: float, transactions: int}}
     */
    private function channels(array $bySource): array
    {
        $channels = ['cashier' => ['money_net' => 0.0, 'transactions' => 0], 'online' => ['money_net' => 0.0, 'transactions' => 0]];
        foreach ($bySource as $row) {
            $key = in_array($row['key'], self::ONLINE_SOURCES, true) ? 'online' : 'cashier';
            $channels[$key]['money_net'] += $row['money_net'];
            $channels[$key]['transactions'] += $row['transactions'];
        }

        return $channels;
    }

    /**
     * Baris "Rincian Pendapatan per Lini Layanan": lini tetap + "Lainnya" (gym, merchandise, salon, dll.) bila ada.
     * Membership punya kartu sendiri. Jumlah semua baris + membership = Pendapatan Bersih.
     *
     * @param  array<string, array<string, mixed>>  $byCategory
     * @return list<array{code: string, label: string, sub: string, money_net: float, transactions: int, soon: bool, filter: list<string>}>
     */
    private function serviceLines(array $byCategory): array
    {
        $lines = [];
        foreach (self::SERVICE_LINES as $code => $line) {
            $row = $byCategory[$code] ?? null;
            $lines[] = [
                'code' => $code,
                'label' => $line['label'],
                'sub' => $line['sub'],
                'money_net' => (float) ($row['money_net'] ?? 0),
                'transactions' => (int) ($row['transactions'] ?? 0),
                'soon' => $line['soon'] && $row === null,
                'filter' => array_key_exists($code, LedgerEntry::CATEGORIES) ? [$code] : [],
            ];
        }

        $others = collect($byCategory)->except([...array_keys(self::SERVICE_LINES), 'MEMBERSHIP']);
        if ($others->isNotEmpty()) {
            $lines[] = [
                'code' => 'LAINNYA',
                'label' => 'Lainnya ('.$others->pluck('label')->implode(', ').')',
                'sub' => 'Penjualan di luar lini utama',
                'money_net' => (float) $others->sum('money_net'),
                'transactions' => (int) $others->sum('transactions'),
                'soon' => false,
                'filter' => $others->keys()->all(),
            ];
        }

        return $lines;
    }

    /** Pemakaian kuota membership di lapangan (informasi utilisasi — bukan uang masuk baru). */
    private function memberUsage(?string $from, ?string $until): array
    {
        $row = PadelBooking::query()->whereIn('status', CourtOccupancy::OCCUPYING_STATUSES)
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
