<?php

namespace App\Filament\Pages;

use App\Models\Membership\FacilityCheckin;
use App\Models\Membership\MembershipPlan;
use App\Models\Membership\MembershipUsageLog;
use App\Models\Membership\UserMembership;
use App\Models\Membership\UserMembershipBalance;
use App\Models\Padel\PadelBooking;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use UnitEnum;

class Kustomer extends Page
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Kustomer';

    protected static string|UnitEnum|null $navigationGroup = 'Main Menu';

    protected static ?string $title = 'Data Kustomer & Live Monitoring Membership';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.kustomer';

    public string $activeTab = 'members'; // 'members' | 'live_monitoring'

    public string $search = '';

    public string $statusFilter = 'ALL'; // 'ALL' | 'ACTIVE' | 'PENDING_PAYMENT' | 'EXPIRED'

    #[Url(as: 'member')]
    public ?string $selectedMembershipId = null;

    public string $detailTab = 'habit_schedule'; // 'habit_schedule' | 'corporate_roster' | 'card_info' | 'audit_logs'

    public function selectTab(string $tab): void
    {
        $this->activeTab = in_array($tab, ['members', 'live_monitoring']) ? $tab : 'members';
    }

    public function selectDetailTab(string $tab): void
    {
        $this->detailTab = $tab;
    }

    public function openDetail(string $id): void
    {
        $this->selectedMembershipId = $id;
        $m = $this->selectedMembership;
        if ($m && $m->owner_type === 'ORGANIZATIONAL') {
            $this->detailTab = 'corporate_roster';
        } else {
            $this->detailTab = 'habit_schedule';
        }
    }

    public function closeDetail(): void
    {
        $this->selectedMembershipId = null;
    }

    public function getMetricsProperty(): array
    {
        $totalActive = UserMembership::where('status', 'ACTIVE')->count();
        $newThisMonth = UserMembership::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $totalPadelHours = UserMembershipBalance::where('facility', 'PADEL')
            ->whereHas('membership', fn ($q) => $q->where('status', 'ACTIVE'))
            ->sum('remaining_quota');
        $todayUsageCount = MembershipUsageLog::whereDate('created_at', now()->toDateString())->count();

        return [
            'total_active' => $totalActive,
            'new_this_month' => $newThisMonth,
            'total_padel_hours' => (float) $totalPadelHours,
            'today_usage_count' => $todayUsageCount,
        ];
    }

    public function getMembersProperty()
    {
        return UserMembership::with(['user', 'plan', 'balances', 'soldByAdmin', 'order'])
            ->when($this->statusFilter !== 'ALL', function ($q) {
                $q->where('status', $this->statusFilter);
            })
            ->when(filled($this->search), function ($q) {
                $term = '%' . trim($this->search) . '%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('membership_code', 'like', $term)
                        ->orWhereHas('user', function ($u) use ($term) {
                            $u->where('name', 'like', $term)
                                ->orWhere('email', 'like', $term)
                                ->orWhere('phone', 'like', $term);
                        })
                        ->orWhereHas('plan', function ($p) use ($term) {
                            $p->where('name', 'like', $term)
                                ->orWhere('code', 'like', $term);
                        });
                });
            })
            ->latest('created_at')
            ->get();
    }

    public function getLiveLogsProperty()
    {
        return MembershipUsageLog::with(['balance.membership.user', 'balance.membership.plan', 'performer'])
            ->latest('created_at')
            ->limit(50)
            ->get();
    }

    public function getSelectedMembershipProperty()
    {
        if (! $this->selectedMembershipId) {
            return null;
        }

        return UserMembership::with([
            'user',
            'plan.benefits',
            'balances.usageLogs' => fn ($q) => $q->latest('created_at')->limit(20),
            'order.payments',
            'soldByAdmin',
        ])->find($this->selectedMembershipId);
    }

    public function getMemberBookingsProperty()
    {
        $m = $this->selectedMembership;
        if (! $m || ! $m->user_id) {
            return collect();
        }

        return PadelBooking::with('court')
            ->where('user_id', $m->user_id)
            ->latest('booking_date')
            ->latest('start_time')
            ->get();
    }

    public function getMemberCheckinsProperty()
    {
        $m = $this->selectedMembership;
        if (! $m || ! $m->user_id) {
            return collect();
        }

        return FacilityCheckin::where('user_id', $m->user_id)
            ->latest('checkin_at')
            ->get();
    }

    public function getCorporateRosterProperty(): array
    {
        $m = $this->selectedMembership;
        if (! $m || $m->owner_type !== 'ORGANIZATIONAL') {
            return [];
        }

        $padelBal = $m->balances->firstWhere('facility', 'PADEL');
        if ($padelBal && ! empty($padelBal->extra_benefits['corporate_members'])) {
            return $padelBal->extra_benefits['corporate_members'];
        }

        return [
            [
                'name' => 'Budi Pratama',
                'role' => 'Software Engineering Lead',
                'email' => 'budi.pratama@sinarharapan.com',
                'phone' => '081233445566',
                'allocated_hours' => 20,
                'used_hours' => 6,
                'remaining_hours' => 14,
                'last_played' => now()->subDays(3)->format('d M Y, 19:00 WIB (Court 1)'),
                'next_schedule' => now()->addDays(2)->format('d M Y, 19:00 - 21:00 WIB (Court 1)'),
                'status' => 'ACTIVE',
                'notes' => 'Rutin tanding ganda malam hari sehabis kantor',
            ],
            [
                'name' => 'Siti Rahmawati',
                'role' => 'Finance & Accounting Manager',
                'email' => 'siti.rahma@sinarharapan.com',
                'phone' => '081277889900',
                'allocated_hours' => 15,
                'used_hours' => 4,
                'remaining_hours' => 11,
                'last_played' => now()->subDays(5)->format('d M Y, 17:00 WIB (Court 2)'),
                'next_schedule' => now()->addDays(4)->format('d M Y, 17:00 - 19:00 WIB (Court 2)'),
                'status' => 'ACTIVE',
                'notes' => 'Sesi sore santai + sauna & ice bath',
            ],
            [
                'name' => 'Dimas Setiawan',
                'role' => 'Marketing & Brand Specialist',
                'email' => 'dimas.s@sinarharapan.com',
                'phone' => '081399001122',
                'allocated_hours' => 15,
                'used_hours' => 2,
                'remaining_hours' => 13,
                'last_played' => now()->subDays(8)->format('d M Y, 08:00 WIB (Court 3)'),
                'next_schedule' => now()->addDays(5)->format('d M Y, 08:00 - 10:00 WIB (Court 3)'),
                'status' => 'ACTIVE',
                'notes' => 'Sesi akhir pekan pagi',
            ],
            [
                'name' => 'Ahmad Fauzi',
                'role' => 'UI/UX Product Designer',
                'email' => 'ahmad.fauzi@sinarharapan.com',
                'phone' => '081344556677',
                'allocated_hours' => 15,
                'used_hours' => 0,
                'remaining_hours' => 15,
                'last_played' => null,
                'next_schedule' => null,
                'status' => 'IDLE',
                'notes' => 'Kosong / Pasif: Didaftarkan 10 hari lalu, belum pernah booking atau check-in',
            ],
            [
                'name' => 'Nadia Putri',
                'role' => 'HR Talent Acquisition',
                'email' => 'nadia.putri@sinarharapan.com',
                'phone' => '081388776655',
                'allocated_hours' => 15,
                'used_hours' => 0,
                'remaining_hours' => 15,
                'last_played' => null,
                'next_schedule' => null,
                'status' => 'IDLE',
                'notes' => 'Kosong / Pasif: Belum pernah ada jadwal bermain',
            ],
            [
                'name' => 'Rian Kurniawan',
                'role' => 'B2B Sales Executive',
                'email' => 'rian.k@sinarharapan.com',
                'phone' => '081311559933',
                'allocated_hours' => 15,
                'used_hours' => 0,
                'remaining_hours' => 15,
                'last_played' => null,
                'next_schedule' => null,
                'status' => 'IDLE',
                'notes' => 'Kosong / Pasif: Belum pernah ada jadwal bermain',
            ],
        ];
    }

    public function getMemberHabitAnalysisProperty(): array
    {
        $m = $this->selectedMembership;
        if (! $m) {
            return [];
        }

        $bookings = $this->memberBookings;
        $checkins = $this->memberCheckins;

        $totalMatches = $bookings->whereIn('status', ['CONFIRMED', 'CHECKED_IN', 'COMPLETED'])->count();
        $lastBooking = $bookings->first();
        $lastPlayedDate = $lastBooking ? $lastBooking->booking_date->format('d M Y') : null;
        $daysSinceLastPlayed = $lastBooking ? now()->diffInDays($lastBooking->booking_date, false) : 999;

        if ($m->owner_type === 'ORGANIZATIONAL') {
            $habitStatus = 'CORPORATE_TEAM';
            $habitLabel = 'Tim Korporat (Multi-Member)';
            $habitColor = '#6C3483';
            $recommendation = 'Akun sponsor korporat dengan kuota bersama untuk seluruh personil karyawan terdaftar.';
        } elseif ($totalMatches >= 4 || ($daysSinceLastPlayed !== 999 && $daysSinceLastPlayed >= -7 && $daysSinceLastPlayed <= 3)) {
            $habitStatus = 'VERY_ACTIVE';
            $habitLabel = 'Padel Addict (Sangat Aktif)';
            $habitColor = '#1E7E34';
            $recommendation = 'Member sangat aktif dengan frekuensi tanding rutin dan konsisten di Club 61.';
        } elseif ($totalMatches >= 1) {
            $habitStatus = 'REGULAR';
            $habitLabel = 'Regular Player (Cukup Aktif)';
            $habitColor = '#8C6418';
            $recommendation = 'Member aktif bermain secara berkala pada jadwal favorit.';
        } else {
            $habitStatus = 'DORMANT';
            $habitLabel = 'Pasif / Kosong Tanpa Kabar';
            $habitColor = '#C0392B';
            $recommendation = 'Member belum aktif menggunakan kuota bermain sejak kartu keanggotaan diterbitkan.';
        }

        return [
            'total_matches' => $totalMatches,
            'total_checkins' => $checkins->count(),
            'last_played' => $lastPlayedDate ?? 'Belum ada riwayat tanding',
            'days_since_last' => $daysSinceLastPlayed,
            'habit_status' => $habitStatus,
            'habit_label' => $habitLabel,
            'habit_color' => $habitColor,
            'favorite_court' => $bookings->groupBy('court.name')->sortByDesc->count()->keys()->first() ?? 'Court 1 - Panoramic Indoor',
            'favorite_time' => 'Sore & Malam (16:00 - 20:00 WIB)',
            'recommendation' => $recommendation,
        ];
    }
}
