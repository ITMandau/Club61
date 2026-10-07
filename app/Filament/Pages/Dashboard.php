<?php

namespace App\Filament\Pages;

use App\Models\Finance\LedgerEntry;
use App\Models\Membership\UserMembership;
use App\Models\Padel\PadelBooking;
use App\Models\Pos\Payment;
use App\Models\Pos\Refund;
use App\Models\User;
use App\Services\Finance\LedgerAnalytics;
use App\Services\Finance\LedgerReport;
use App\Services\Padel\CourtOccupancy;
use App\Services\Padel\PadelBookingService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use UnitEnum;

/**
 * Halaman utama admin: kondisi operasional HARI INI dari data asli. Dulu seluruh isinya angka & baris contoh yang
 * ditulis mati di view ("Rp 50.272.597", "132 Bookings", booking "PXDL" 19 Mei 2026).
 * Angka uang (dari Buku Transaksi) hanya tampil untuk yang boleh membuka Analytics & Keuangan.
 */
class Dashboard extends BaseDashboard
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?string $title = 'Dashboard';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.dashboard';

    public const TREND_OPTIONS = [7 => '7 hari', 30 => '30 hari', 90 => '90 hari'];

    /** Status booking → label & warna pill. */
    public const STATUS_LABELS = [
        'LOCKED' => ['Slot ditahan', 'gray'],
        'PENDING' => ['Menunggu bayar', 'amber'],
        'PENDING_PAYMENT' => ['Menunggu bayar', 'amber'],
        'PAID' => ['Lunas', 'green'],
        'CHECKED_IN' => ['Sudah check-in', 'gold'],
        'COMPLETED' => ['Selesai', 'green'],
        'EXPIRED' => ['Hangus (tidak datang)', 'red'],
        'CANCELLED' => ['Dibatalkan', 'red'],
        'REFUND_PENDING' => ['Refund diproses', 'red'],
        'REFUNDED' => ['Direfund', 'red'],
    ];

    #[Locked]
    public int $trendDays = 7;

    public string $search = '';

    public function getWidgets(): array
    {
        return [];
    }

    public function getColumns(): int|array
    {
        return 1;
    }

    public function setTrendDays(int $days): void
    {
        $this->trendDays = array_key_exists($days, self::TREND_OPTIONS) ? $days : 7;
    }

    protected function getViewData(): array
    {
        // Status tiket diselaraskan dulu (PAID lewat jadwal → EXPIRED/COMPLETED), sama seperti Analytics.
        app(PadelBookingService::class)->syncExpiredAndCompletedBookings();

        $now = Carbon::now(LedgerReport::TIMEZONE);
        $today = $now->toDateString();
        $canSeeMoney = Analytics::canAccess();

        $todayBookings = PadelBooking::query()->whereDate('booking_date', $today);

        return [
            'now' => $now,
            'canSeeMoney' => $canSeeMoney,
            'money' => $canSeeMoney ? $this->money($now) : null,
            'trend' => $canSeeMoney ? $this->trend($now) : null,
            'occupancy' => CourtOccupancy::calculate($today, $today),
            'bookingsToday' => (clone $todayBookings)->whereIn('status', CourtOccupancy::OCCUPYING_STATUSES)->count(),
            'checkedInToday' => (clone $todayBookings)->whereIn('status', ['CHECKED_IN', 'COMPLETED'])->count(),
            'playingNow' => PadelBooking::query()->where('status', 'CHECKED_IN')
                ->where('start_time', '<=', now())->where('end_time', '>', now())->count(),
            'customers' => $this->customers($now),
            'activeMembers' => UserMembership::query()->where('status', 'ACTIVE')
                ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $today))
                ->distinct()->count('user_id'),
            'attention' => $this->attention($today),
            'schedule' => $this->schedule($today),
        ];
    }

    /** Uang masuk (bersih setelah refund) hari ini vs kemarin, dari Buku Transaksi. */
    private function money(Carbon $now): array
    {
        $day = function (Carbon $date) {
            $d = $date->toDateString();

            return LedgerReport::summary(LedgerReport::applyDateRange(LedgerEntry::query(), $d, $d), $d, $d);
        };
        $today = $day($now);
        $yesterday = $day($now->copy()->subDay());
        $month = LedgerReport::summary(LedgerReport::applyDateRange(LedgerEntry::query(), $now->copy()->startOfMonth()->toDateString(), $now->toDateString()));

        return [
            'today' => $today['money_net'],
            'today_count' => $today['payments_count'],
            'yesterday' => $yesterday['money_net'],
            'change' => self::percentChange($today['money_net'], $yesterday['money_net']),
            'month' => $month['money_net'],
        ];
    }

    private function trend(Carbon $now): array
    {
        $from = $now->copy()->subDays($this->trendDays - 1)->toDateString();
        $until = $now->toDateString();
        $base = LedgerReport::applyDateRange(LedgerEntry::query(), $from, $until);
        $trend = LedgerAnalytics::trend($base, $from, $until, maxDailyPoints: 92);
        $summary = LedgerReport::summary(clone $base, $from, $until);

        return $trend + ['total' => $summary['money_net'], 'payments' => $summary['payments_count']];
    }

    /** Customer baru bulan ini vs periode yang sama bulan lalu (akun tanpa peran staf). */
    private function customers(Carbon $now): array
    {
        $customers = fn () => User::query()->whereDoesntHave('roles', fn ($q) => $q->where('name', '!=', 'customer'));
        $tz = config('app.timezone');
        $count = fn (Carbon $from, Carbon $until) => $customers()
            ->where('created_at', '>=', $from->copy()->setTimezone($tz))
            ->where('created_at', '<', $until->copy()->setTimezone($tz))->count();

        $thisMonth = $count($now->copy()->startOfMonth(), $now->copy()->addDay()->startOfDay());
        $lastMonthStart = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $lastMonth = $count($lastMonthStart, $lastMonthStart->copy()->addDays($now->day));

        return ['this_month' => $thisMonth, 'last_month' => $lastMonth, 'change' => self::percentChange($thisMonth, $lastMonth)];
    }

    /** Hal yang perlu ditindaklanjuti staf, masing-masing dengan tautan ke halaman tempat menyelesaikannya. */
    private function attention(string $today): array
    {
        $items = [];

        $cashierBills = Payment::query()->where('status', 'PENDING')->where('payment_gateway', 'CASHIER_POS');
        if (($n = (clone $cashierBills)->count()) > 0) {
            $items[] = [
                'label' => "{$n} tagihan menunggu dibayar di kasir",
                'amount' => (float) $cashierBills->sum('amount'),
                'url' => BookOfflineCourt::canAccess() ? BookOfflineCourt::getUrl() : null,
            ];
        }

        $refunds = Refund::query()->where('status', 'PENDING');
        if (($n = (clone $refunds)->count()) > 0) {
            $items[] = [
                'label' => "{$n} refund menunggu diproses",
                'amount' => (float) $refunds->sum('refund_amount'),
                'url' => AntrianRefund::canAccess() ? AntrianRefund::getUrl() : null,
            ];
        }

        $awaitingOnline = PadelBooking::query()->whereIn('status', ['PENDING', 'PENDING_PAYMENT'])->whereDate('booking_date', '>=', $today)->count();
        if ($awaitingOnline > 0) {
            $items[] = [
                'label' => "{$awaitingOnline} booking online menunggu pembayaran",
                'amount' => null,
                'url' => KelolaPemesanan::canAccess() ? KelolaPemesanan::getUrl() : null,
            ];
        }

        return $items;
    }

    /** Jadwal lapangan hari ini (urut jam mulai), bisa dicari per kode booking / nama / HP. */
    private function schedule(string $today): Collection
    {
        $search = trim($this->search);

        return PadelBooking::query()
            ->with(['court:id,name', 'user:id,name,phone', 'order:id,payment_status'])
            ->whereDate('booking_date', $today)
            ->whereNotIn('status', ['CANCELLED', 'LOCKED'])
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.mb_substr($search, 0, 100).'%';
                $q->where(fn ($w) => $w->where('booking_code', 'like', $like)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $like)->orWhere('phone', 'like', $like)));
            })
            ->orderBy('start_time')
            ->limit(100)
            ->get();
    }

    public static function percentChange(float|int $current, float|int $previous): ?float
    {
        if ((float) $previous == 0.0) {
            return null;
        }

        return round(($current - $previous) / abs($previous) * 100, 1);
    }

    public static function statusLabel(?string $status): array
    {
        return self::STATUS_LABELS[$status] ?? [(string) $status, 'gray'];
    }

    public static function rupiah(float|int|null $value): string
    {
        return BukuTransaksi::rupiah($value);
    }
}
