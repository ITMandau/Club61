<?php

namespace App\Filament\Pages;

use App\Models\Padel\PadelBooking;
use App\Models\Pos\Payment;
use App\Services\Padel\PadelBookingService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use UnitEnum;

class Analytics extends Page
{
    use HasPageShield;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Analytics & Keuangan';

    protected static string | UnitEnum | null $navigationGroup = 'Main Menu';

    protected static ?string $title = 'Laporan Uang Masuk & Analytics';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.analytics';

    public string $period = 'ALL'; // ALL, THIS_MONTH, THIS_WEEK, TODAY

    public function setPeriod(string $period): void
    {
        $this->period = $period;
    }

    protected function getViewData(): array
    {
        // REAKTIF FAIL-SAFE: Sinkronkan status tiket terlebih dahulu
        app(PadelBookingService::class)->syncExpiredAndCompletedBookings();

        $now = now();
        $queryFinancialBookings = PadelBooking::whereIn('status', ['PAID', 'CHECKED_IN', 'COMPLETED', 'EXPIRED']);
        $queryOccupancyBookings = PadelBooking::whereIn('status', ['PAID', 'CHECKED_IN', 'COMPLETED', 'EXPIRED']);
        $queryRefunds = DB::table('refunds');
        $queryPayments = DB::table('payments')->where('status', 'SUCCESS');
        // Pemasukan penjualan paket Membership: KANAL TERPISAH dari booking (item_type = 'MEMBERSHIP' di order_items),
        // wajib dijumlahkan sendiri agar tidak tercampur dengan omzet booking lapangan (lihat $memberBenefit di bawah).
        $queryMembershipOrders = \App\Models\Pos\Order::whereHas('items', fn ($q) => $q->where('item_type', 'MEMBERSHIP'))
            ->where('payment_status', 'PAID');

        if ($this->period === 'TODAY') {
            $today = $now->format('Y-m-d');
            // CASH BASIS: Uang diakui saat kas diterima (created_at)
            $queryFinancialBookings->whereDate('created_at', $today);
            $queryOccupancyBookings->whereDate('booking_date', $today);
            $queryRefunds->whereDate('created_at', $today);
            $queryPayments->whereDate('created_at', $today);
            $queryMembershipOrders->whereDate('created_at', $today);
            $periodLabel = 'Hari Ini (' . $now->translatedFormat('d M Y') . ')';
        } elseif ($this->period === 'THIS_WEEK') {
            $startOfWeek = $now->copy()->startOfWeek()->format('Y-m-d');
            $queryFinancialBookings->whereDate('created_at', '>=', $startOfWeek);
            $queryOccupancyBookings->whereDate('booking_date', '>=', $startOfWeek);
            $queryRefunds->whereDate('created_at', '>=', $startOfWeek);
            $queryPayments->whereDate('created_at', '>=', $startOfWeek);
            $queryMembershipOrders->whereDate('created_at', '>=', $startOfWeek);
            $periodLabel = 'Minggu Ini (Sejak ' . $now->copy()->startOfWeek()->translatedFormat('d M') . ')';
        } elseif ($this->period === 'THIS_MONTH') {
            $startOfMonth = $now->copy()->startOfMonth()->format('Y-m-d');
            $queryFinancialBookings->whereDate('created_at', '>=', $startOfMonth);
            $queryOccupancyBookings->whereDate('booking_date', '>=', $startOfMonth);
            $queryRefunds->whereDate('created_at', '>=', $startOfMonth);
            $queryPayments->whereDate('created_at', '>=', $startOfMonth);
            $queryMembershipOrders->whereDate('created_at', '>=', $startOfMonth);
            $periodLabel = 'Bulan Ini (' . $now->translatedFormat('F Y') . ')';
        } else {
            $periodLabel = 'Semua Waktu (All-Time)';
        }

        // Agregat Finansial Riil (Cash Basis)
        $financials = (clone $queryFinancialBookings)->selectRaw("
            COALESCE(SUM(total_amount), 0) as gross_revenue,
            COALESCE(SUM(court_fee), 0) as court_revenue,
            COALESCE(SUM(equipment_fee), 0) as equipment_revenue,
            COALESCE(SUM(coach_fee), 0) as coach_revenue,
            COUNT(*) as total_bookings
        ")->first();

        $grossRevenue = (float) ($financials->gross_revenue ?? 0);
        $courtRevenue = (float) ($financials->court_revenue ?? 0);
        $equipmentRevenue = (float) ($financials->equipment_revenue ?? 0);
        $coachRevenue = (float) ($financials->coach_revenue ?? 0);
        $totalBookings = (int) ($financials->total_bookings ?? 0);

        $totalRefund = (float) ($queryRefunds->sum('refund_amount') ?? 0);
        $netRevenue = max(0, $grossRevenue - $totalRefund);

        // Pemasukan Penjualan Membership (Kanal Kas Terpisah — Uang Diterima Saat Paket Dibeli, Bukan Saat Dipakai)
        $membershipSalesRevenue = (float) $queryMembershipOrders->sum('grand_total');

        // Nilai Benefit Membership yang Diredeem (INFORMASIONAL — BUKAN pendapatan baru, jangan dijumlahkan ke omzet).
        // Uangnya sudah diakui SEKALI saat paket dibeli ($membershipSalesRevenue di atas); angka ini hanya menunjukkan
        // berapa nilai manfaat yang benar-benar dipakai member dari booking pada periode ini (dipakai untuk analisis
        // utilisasi kapasitas, BUKAN untuk laporan uang masuk). Filter status booking mengikuti $queryFinancialBookings
        // (PAID/CHECKED_IN/COMPLETED/EXPIRED) sehingga booking yang dibatalkan & sudah dikembalikan kuotanya otomatis
        // tidak ikut terhitung di sini.
        $memberBenefit = (clone $queryFinancialBookings)->selectRaw("
            COALESCE(SUM(member_discount_court), 0) as benefit_value,
            COALESCE(SUM(member_hours_consumed), 0) as hours_consumed,
            COUNT(CASE WHEN membership_balance_id IS NOT NULL THEN 1 END) as bookings_with_membership
        ")->first();

        $memberBenefitRedeemedValue = (float) ($memberBenefit->benefit_value ?? 0);
        $memberBenefitHoursConsumed = (float) ($memberBenefit->hours_consumed ?? 0);
        $bookingsUsingMembership = (int) ($memberBenefit->bookings_with_membership ?? 0);

        // Omzet Gabungan Venue (Booking + Penjualan Membership) — angka "total uang masuk" yang sesungguhnya,
        // dipisah dari $netRevenue lama agar dashboard yang sudah ada tidak berubah arti tanpa disengaja.
        $combinedRevenue = $netRevenue + $membershipSalesRevenue;

        // Breakdown Metode Pembayaran (Sesuai Periode Aktif)
        $cashTotal = (float) (clone $queryPayments)->where('payment_method', 'CASH')->sum('amount');
        $midtransTotal = (float) (clone $queryPayments)->where('payment_gateway', 'MIDTRANS')->sum('amount');
        
        // Sisa transaksi booking default tunai kasir / manual
        $settledFromBookings = max(0, $grossRevenue - ($cashTotal + $midtransTotal));
        $cashTotal += $settledFromBookings;

        // Okupansi Lapangan Estimasi (Sesuai Jadwal Lapangan booking_date)
        $totalHoursBooked = 0;
        foreach ((clone $queryOccupancyBookings)->get(['start_time', 'end_time']) as $b) {
            $totalHoursBooked += max(1, (int) $b->start_time->diffInHours($b->end_time));
        }
        // 4 Lapangan x 18 jam/hari (06:00 - 24:00) = 72 jam/hari
        $daysInPeriod = match($this->period) {
            'TODAY' => 1,
            'THIS_WEEK' => 7,
            'THIS_MONTH' => $now->daysInMonth,
            default => 30,
        };
        $totalCapacityHours = 4 * 18 * $daysInPeriod;
        $occupancyRate = $totalCapacityHours > 0 ? round(($totalHoursBooked / $totalCapacityHours) * 100, 1) : 0;

        // 10 Transaksi Uang Masuk Terkini (Live Audit Log untuk PM)
        $latestTransactions = PadelBooking::with(['user', 'court'])
            ->whereIn('status', ['PAID', 'CHECKED_IN', 'COMPLETED', 'EXPIRED'])
            ->latest('created_at')
            ->take(10)
            ->get();

        // 5 Refund Terkini
        $latestRefunds = DB::table('refunds')
            ->leftJoin('orders', 'refunds.order_id', '=', 'orders.id')
            ->leftJoin('users', 'orders.user_id', '=', 'users.id')
            ->select('refunds.*', 'users.name as customer_name', 'orders.order_number')
            ->latest('refunds.created_at')
            ->take(5)
            ->get();

        return [
            'periodLabel' => $periodLabel,
            'grossRevenue' => $grossRevenue,
            'totalRefund' => $totalRefund,
            'netRevenue' => $netRevenue,
            'courtRevenue' => $courtRevenue,
            'equipmentRevenue' => $equipmentRevenue,
            'coachRevenue' => $coachRevenue,
            'totalBookings' => $totalBookings,
            'totalHoursBooked' => $totalHoursBooked,
            'occupancyRate' => $occupancyRate,
            'cashTotal' => $cashTotal,
            'midtransTotal' => $midtransTotal,
            'membershipSalesRevenue' => $membershipSalesRevenue,
            'memberBenefitRedeemedValue' => $memberBenefitRedeemedValue,
            'memberBenefitHoursConsumed' => $memberBenefitHoursConsumed,
            'bookingsUsingMembership' => $bookingsUsingMembership,
            'combinedRevenue' => $combinedRevenue,
            'latestTransactions' => $latestTransactions,
            'latestRefunds' => $latestRefunds,
        ];
    }
}
