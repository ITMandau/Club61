<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\BookingSystem;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\Order;
use App\Models\User;
use App\Services\Padel\ScheduleOverview;
use App\Services\Permission\Club61PermissionMatrix;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Modul 28 Batch 3: Monitoring Lapangan punya tampilan Mingguan & Bulanan (hanya baca) + Rekap Pemain — siapa booking
 * jam berapa dalam seminggu / sebulan. Tampilan harian tetap seperti sebelumnya.
 */
class MonitoringOverviewTest extends TestCase
{
    use RefreshDatabase;

    private PadelCourt $bca;

    private PadelCourt $indosat;

    private Carbon $monday;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');
        $this->actingAs(User::factory()->admin()->create());

        $court = fn (string $name) => PadelCourt::create(['name' => $name, 'type' => 'INDOOR', 'hourly_rate_regular' => 200000, 'hourly_rate_prime' => 300000, 'is_active' => true, 'open_time' => '06:00', 'close_time' => '00:00']);
        $this->bca = $court('Court BCA');
        $this->indosat = $court('Court Indosat');
        // Minggu depan: booking masih akan datang, jadi tidak disentuh sinkronisasi status selesai / kedaluwarsa.
        $this->monday = now()->addWeek()->startOfWeek(Carbon::MONDAY);
    }

    private function booking(string $name, PadelCourt $court, Carbon $day, int $hour, int $hours = 1, array $attrs = [], ?string $orderType = null): PadelBooking
    {
        $user = User::factory()->customer()->create(['name' => $name, 'phone' => '0812'.random_int(10000000, 99999999)]);
        $order = $orderType ? Order::create(['order_number' => 'ORD-'.Str::random(8), 'user_id' => $user->id, 'order_type' => $orderType, 'subtotal' => 0, 'grand_total' => 0, 'payment_status' => 'PAID']) : null;
        $start = $day->copy()->setTime($hour, 0);

        return PadelBooking::create(array_merge([
            'booking_code' => 'BK-'.strtoupper(Str::random(6)), 'order_id' => $order?->id, 'user_id' => $user->id, 'court_id' => $court->id,
            'booking_date' => $day->toDateString(), 'start_time' => $start, 'end_time' => $start->copy()->addHours($hours),
            'court_fee' => 200000, 'total_amount' => 200000, 'status' => 'PAID',
        ], $attrs));
    }

    public function test_booking_types_are_recognised_for_colours_and_filters(): void
    {
        $type = fn (array $attrs, ?string $orderType = null) => ScheduleOverview::type(
            (new PadelBooking)->forceFill($attrs)->setRelation('order', $orderType ? (new Order)->forceFill(['order_type' => $orderType]) : null)
        );

        $this->assertSame('COACHING', $type(['coaching_booking_id' => 'x', 'membership_balance_id' => 'y']));
        $this->assertSame('RECURRING', $type(['recurring_schedule_id' => 'x', 'membership_balance_id' => 'y']));
        $this->assertSame('CORPORATE', $type(['sponsor_member_voucher_id' => 'x']));
        $this->assertSame('MEMBER', $type(['membership_balance_id' => 'x'], 'WALK_IN'));
        $this->assertSame('WALK_IN', $type([], 'WALK_IN'));
        $this->assertSame('ONLINE', $type([], 'ONLINE_BOOKING'));
    }

    public function test_week_view_shows_who_plays_at_which_hour_and_filters(): void
    {
        $andi = $this->booking('Andi Online', $this->bca, $this->monday, 19, 2, orderType: 'ONLINE_BOOKING');
        $this->booking('Budi Walkin', $this->indosat, $this->monday->copy()->addDays(2), 8, orderType: 'WALK_IN');
        $this->booking('Citra Hold', $this->indosat, $this->monday->copy()->addDays(3), 10, attrs: ['status' => 'LOCKED']);
        $this->booking('Dodi Batal', $this->bca, $this->monday, 7, attrs: ['status' => 'CANCELLED']);
        $this->booking('Eko Minggu Lain', $this->bca, $this->monday->copy()->addWeek(), 9);

        $week = app(ScheduleOverview::class)->week($this->monday->copy()->addDays(4));
        $mon = $this->monday->toDateString();
        $this->assertSame('Andi Online', $week['cells'][$mon][19][0]['name']);
        $this->assertSame('BCA', $week['cells'][$mon][19][0]['court']);
        $this->assertFalse($week['cells'][$mon][19][0]['continued']);
        $this->assertTrue($week['cells'][$mon][20][0]['continued'], 'jam ke-2 booking 2 jam ditandai lanjutan');
        $this->assertArrayNotHasKey(7, $week['cells'][$mon] ?? [], 'booking batal tidak ikut');
        $this->assertSame(3, $week['bookings']->count(), 'booking minggu lain tidak ikut');

        $page = Livewire::test(BookingSystem::class)
            ->set('selectedDate', $this->monday->copy()->addDays(2)->toDateString())
            ->call('setViewMode', 'week')
            ->assertSee('Jadwal Mingguan')->assertSee('Andi Online')->assertSee('Budi Walkin')->assertSee('Citra Hold')
            ->assertDontSee('Dodi Batal')->assertDontSee('Eko Minggu Lain')
            ->assertSee('Rekap Pemain');

        $page->set('typeFilter', 'WALK_IN')->assertSee('Budi Walkin')->assertDontSee('Andi Online');
        $page->set('typeFilter', 'all')->set('search', 'andi')->assertSee('Andi Online')->assertDontSee('Budi Walkin');
        $page->set('search', '')->set('courtFilter', $this->indosat->id)->assertSee('Budi Walkin')->assertDontSee('Andi Online');

        // Klik nama → panel detail yang sama dengan tampilan harian.
        $page->set('courtFilter', 'all')->call('inspectBooking', $andi->id)->assertSet('showInspectorDrawer', true)->assertSet('inspectData.customer_name', 'Andi Online');

        // Navigasi per minggu.
        $page->call('shiftPeriod', 1)->assertSee('Eko Minggu Lain')->assertDontSee('Andi Online');
    }

    public function test_month_view_counts_bookings_per_day_and_lists_a_chosen_day(): void
    {
        $day = $this->monday->copy();
        $this->booking('Andi Online', $this->bca, $day, 19, 2, orderType: 'ONLINE_BOOKING');
        $this->booking('Budi Walkin', $this->indosat, $day, 8, orderType: 'WALK_IN');

        $month = app(ScheduleOverview::class)->month($day);
        $cell = collect($month['weeks'])->flatten(1)->firstWhere('date', $day->toDateString());
        $this->assertSame(2, $cell['count']);
        $this->assertEquals(3.0, $cell['hours']);
        $this->assertSame(8, $cell['rate'], '3 jam dari 2 lapangan × 18 jam');
        $this->assertCount(7, $month['weeks'][0], 'kalender Senin–Minggu');

        Livewire::test(BookingSystem::class)
            ->set('selectedDate', $day->toDateString())
            ->call('setViewMode', 'month')
            ->assertSee('Kalender Bulanan')->assertSee('2 booking')
            ->call('selectMonthDay', $day->toDateString())
            ->assertSee('Andi Online')->assertSee('19:00–21:00')->assertSee('Budi Walkin')
            ->call('openDay', $day->toDateString())
            ->assertSet('viewMode', 'day')
            ->assertSet('selectedDate', $day->toDateString())
            ->assertSee('Timetable Jadwal Lapangan Padel');
    }

    public function test_player_recap_sums_bookings_and_hours_per_person(): void
    {
        $andi = $this->booking('Andi Rutin', $this->bca, $this->monday, 19, 2, orderType: 'ONLINE_BOOKING');
        PadelBooking::create([
            'booking_code' => 'BK-AR-2', 'user_id' => $andi->user_id, 'court_id' => $this->indosat->id,
            'booking_date' => $this->monday->copy()->addDay()->toDateString(), 'start_time' => $this->monday->copy()->addDay()->setTime(7, 0),
            'end_time' => $this->monday->copy()->addDay()->setTime(8, 0), 'court_fee' => 0, 'total_amount' => 0, 'status' => 'PAID',
            'membership_balance_id' => null,
        ]);
        $this->booking('Budi Sekali', $this->indosat, $this->monday, 8);

        $overview = app(ScheduleOverview::class);
        $players = $overview->players($overview->week($this->monday)['bookings']);
        $this->assertSame('Andi Rutin', $players[0]['name']);
        $this->assertSame(2, $players[0]['count']);
        $this->assertEquals(3.0, $players[0]['hours']);
        $this->assertCount(2, $players);
    }

    public function test_daily_view_still_works_and_can_filter_one_court(): void
    {
        $this->booking('Andi Harian', $this->bca, $this->monday, 19);
        $this->booking('Budi Harian', $this->indosat, $this->monday, 19);

        Livewire::test(BookingSystem::class)
            ->set('selectedDate', $this->monday->toDateString())
            ->assertSet('viewMode', 'day')
            ->assertSee('Timetable Jadwal Lapangan Padel')->assertSee('Andi Harian')->assertSee('Budi Harian')
            ->set('courtFilter', $this->bca->id)
            ->assertSee('Andi Harian')->assertDontSee('Budi Harian');
    }

    public function test_daily_view_shrinks_past_hours_except_those_with_bookings_and_has_no_indoor_outdoor_filter(): void
    {
        $yesterday = now()->subDay()->startOfDay();
        $this->booking('Andi Kemarin', $this->bca, $yesterday, 10, attrs: ['status' => 'COMPLETED']);

        Livewire::test(BookingSystem::class)
            ->set('selectedDate', $yesterday->toDateString())
            ->assertSee('Andi Kemarin')
            ->assertDontSee('Indoor Only')->assertDontSee('Outdoor Only')
            ->assertViewHas('operationalHours', function (array $hours) {
                $dead = collect($hours)->pluck('is_dead', 'hour');

                return $dead[10] === false && $dead[9] === true && $dead[23] === true;
            });

        // Hari depan: belum ada jam yang lewat, semua kolom tampil penuh.
        Livewire::test(BookingSystem::class)
            ->set('selectedDate', $this->monday->toDateString())
            ->assertViewHas('operationalHours', fn (array $hours) => collect($hours)->every(fn ($h) => $h['is_dead'] === false));
    }
}
