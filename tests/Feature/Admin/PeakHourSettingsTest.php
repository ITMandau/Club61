<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\MasterData;
use App\Models\Audit\ActivityLog;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Padel\PadelHoliday;
use App\Models\Padel\PadelPeakHourRule;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\Padel\PadelBookingService;
use App\Services\Padel\PeakHourService;
use App\Services\Permission\Club61PermissionMatrix;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Jam peak (prime time) dinamis dari Master Data — dulu hard-code "akhir pekan ATAU jam >= 17". */
class PeakHourSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected PadelCourt $court;

    protected Carbon $wednesday;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');
        $this->admin = User::factory()->superAdmin()->create();
        $this->court = PadelCourt::create([
            'name' => 'Court Peak', 'type' => 'INDOOR', 'hourly_rate_regular' => 200000, 'hourly_rate_prime' => 300000,
            'open_time' => '06:00', 'close_time' => '24:00', 'is_active' => true,
        ]);
        $this->wednesday = now()->next(Carbon::WEDNESDAY)->startOfDay();
    }

    private function peak(): PeakHourService
    {
        return app(PeakHourService::class);
    }

    private function at(Carbon $day, int $hour): Carbon
    {
        return $day->copy()->setTime($hour, 0);
    }

    /** Harga slot jam tertentu dari jadwal yang dilihat customer (API getScheduleMatrix). */
    private function matrixPrice(Carbon $day, string $hour): float
    {
        $matrix = app(PadelBookingService::class)->getScheduleMatrix($day->format('Y-m-d'));
        $court = collect($matrix['courts'])->firstWhere('court_id', $this->court->id);

        return (float) collect($court['slots'])->firstWhere('local_start', $hour)['price'];
    }

    /** Isi grid editor (24 kotak jam per hari) dari daftar rentang, lalu simpan seperti tombol "Simpan Jam Peak". */
    private function saveRules(array $rules): \Livewire\Features\SupportTesting\Testable
    {
        $this->actingAs($this->admin);
        $grid = [];
        foreach (array_keys(MasterData::DAY_LABELS) as $day) {
            $grid[$day] = array_fill(0, 24, false);
            foreach ($rules[$day] ?? [] as $range) {
                for ($h = (int) substr($range['start'], 0, 2); $h < (int) substr($range['end'], 0, 2); $h++) {
                    $grid[$day][$h] = true;
                }
            }
        }

        return Livewire::test(MasterData::class)->call('setActiveTab', 'peak_hours')->call('savePeakGrid', $grid);
    }

    public function test_default_rules_match_the_previous_hardcoded_behaviour(): void
    {
        $this->assertFalse($this->peak()->isPeak($this->at($this->wednesday, 16)));
        $this->assertTrue($this->peak()->isPeak($this->at($this->wednesday, 17)));
        $this->assertTrue($this->peak()->isPeak($this->at($this->wednesday, 23)));
        $this->assertTrue($this->peak()->isPeak($this->at($this->wednesday->copy()->next(Carbon::SATURDAY), 8)));
    }

    public function test_admin_can_make_the_late_night_slot_regular_and_every_price_path_follows(): void
    {
        $weekday = ['start' => '17:00', 'end' => '22:00'];
        $this->saveRules([1 => [$weekday], 2 => [$weekday], 3 => [$weekday], 4 => [$weekday], 5 => [$weekday], 6 => [['start' => '07:00', 'end' => '22:00']], 0 => [['start' => '07:00', 'end' => '22:00']]])
            ->assertHasNoErrors();

        $this->assertSame(5 + 2, PadelPeakHourRule::count());

        // Jadwal customer / API
        $this->assertEquals(300000, $this->matrixPrice($this->wednesday, '21:00'));
        $this->assertEquals(200000, $this->matrixPrice($this->wednesday, '23:00'), '23:00-24:00 sekarang reguler');

        // Hold booking (harga yang benar-benar ditagih)
        $customer = User::factory()->customer()->create();
        $hold = app(PadelBookingService::class)->holdBatchSlots([
            ['court_id' => $this->court->id, 'start_time' => '21:00', 'end_time' => '23:00'],
        ], $this->wednesday->format('Y-m-d'), $customer);
        $this->assertEquals(300000 + 200000, (float) $hold['subtotal'], '21:00 peak + 22:00 reguler');

        // Selisih reschedule
        $booking = $hold['bookings'][0];
        $quote = app(PadelBookingService::class)->quoteReschedule($booking, $this->court, $this->at($this->wednesday, 22), $this->at($this->wednesday, 24));
        $this->assertEquals(400000, $quote['gross_new']);
    }

    public function test_multiple_ranges_per_day(): void
    {
        $this->saveRules([3 => [['start' => '06:00', 'end' => '08:00'], ['start' => '17:00', 'end' => '22:00']]])->assertHasNoErrors();

        $this->assertTrue($this->peak()->isPeak($this->at($this->wednesday, 7)));
        $this->assertFalse($this->peak()->isPeak($this->at($this->wednesday, 12)));
        $this->assertTrue($this->peak()->isPeak($this->at($this->wednesday, 18)));
        $this->assertFalse($this->peak()->isPeak($this->wednesday->copy()->next(Carbon::SATURDAY)->setTime(10, 0)), 'hari tanpa rentang = reguler seharian');
    }

    public function test_holiday_uses_sunday_rules(): void
    {
        $this->saveRules([3 => [['start' => '17:00', 'end' => '22:00']], 0 => [['start' => '08:00', 'end' => '22:00']]])->assertHasNoErrors();
        $this->assertFalse($this->peak()->isPeak($this->at($this->wednesday, 10)));

        Livewire::test(MasterData::class)
            ->call('setActiveTab', 'peak_hours')
            ->set('holidayDate', $this->wednesday->format('Y-m-d'))
            ->set('holidayName', 'Libur Nasional Tes')
            ->call('addHoliday')
            ->assertHasNoErrors()
            ->assertSee('Libur Nasional Tes');

        $this->assertTrue($this->peak()->isPeak($this->at($this->wednesday, 10)));
        $this->assertEquals(300000, $this->matrixPrice($this->wednesday, '10:00'));

        Livewire::test(MasterData::class)->call('deleteHoliday', PadelHoliday::first()->id);
        $this->assertFalse($this->peak()->isPeak($this->at($this->wednesday, 10)));
    }

    public function test_malformed_grid_from_the_browser_is_rejected_and_nothing_is_saved(): void
    {
        $this->actingAs($this->admin);
        $before = PadelPeakHourRule::count();

        $missingDay = [1 => array_fill(0, 24, false)];
        $shortDay = array_fill_keys(array_keys(MasterData::DAY_LABELS), array_fill(0, 23, true));
        foreach ([$missingDay, $shortDay, []] as $grid) {
            Livewire::test(MasterData::class)->call('savePeakGrid', $grid)->assertReturned(false);
        }

        $this->assertSame($before, PadelPeakHourRule::count());
        $this->assertTrue($this->peak()->isPeak($this->at($this->wednesday, 23)), 'aturan lama tetap berlaku');
    }

    public function test_editor_grid_reflects_saved_rules_and_contiguous_hours_become_one_range(): void
    {
        $this->saveRules([3 => [['start' => '06:00', 'end' => '08:00'], ['start' => '20:00', 'end' => '24:00']]])->assertReturned(true);

        $this->assertEquals([[6, 8], [20, 24]], PadelPeakHourRule::where('day_of_week', 3)->orderBy('start_hour')->get()
            ->map(fn ($r) => [$r->start_hour, $r->end_hour])->all());

        $grid = Livewire::test(MasterData::class)->instance()->peakGrid;
        $this->assertTrue($grid[3][7]);
        $this->assertFalse($grid[3][12]);
        $this->assertTrue($grid[3][23]);

        Livewire::test(MasterData::class)->call('setActiveTab', 'peak_hours')
            ->assertSee('Atur Jam Ramai (Peak)')
            ->assertSee('Rp 200.000/jam')
            ->assertSee('Rp 300.000/jam');
    }

    public function test_change_is_audited(): void
    {
        $this->saveRules([3 => [['start' => '18:00', 'end' => '22:00']]])->assertHasNoErrors();

        $log = ActivityLog::where('event', 'peak_hours.updated')->sole();
        $this->assertSame('MASTER_DATA', $log->module);
        $this->assertSame('18:00–22:00', $log->changes['Rabu']['new']);
    }

    public function test_paid_bookings_keep_their_price_after_rules_change(): void
    {
        $customer = User::factory()->customer()->create();
        $order = Order::create(['order_number' => 'ORD-PEAK', 'user_id' => $customer->id, 'order_type' => 'ONLINE_BOOKING', 'subtotal' => 300000, 'grand_total' => 300000, 'payment_status' => 'PAID']);
        Payment::create(['order_id' => $order->id, 'payment_gateway' => 'MIDTRANS', 'transaction_id' => 'ORD-PEAK', 'amount' => 300000, 'payment_method' => 'QRIS', 'status' => 'SUCCESS']);
        $booking = PadelBooking::create(['booking_code' => 'BK-PEAK', 'order_id' => $order->id, 'user_id' => $customer->id, 'court_id' => $this->court->id, 'booking_date' => $this->wednesday->format('Y-m-d'), 'start_time' => $this->at($this->wednesday, 23), 'end_time' => $this->at($this->wednesday, 24), 'court_fee' => 300000, 'total_amount' => 300000, 'status' => 'PAID']);

        $this->saveRules([3 => [['start' => '17:00', 'end' => '22:00']]])->assertHasNoErrors();

        $this->assertEquals(300000, (float) $booking->fresh()->court_fee);
        $this->assertEquals(300000, (float) $order->fresh()->grand_total);
    }

    public function test_staff_without_pricing_permission_cannot_change_peak_hours(): void
    {
        $kitchen = User::factory()->kitchen()->create();
        Role::findByName('kitchen', 'web')->givePermissionTo('View:MasterData');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($kitchen);

        Livewire::test(MasterData::class)
            ->call('setActiveTab', 'peak_hours')
            ->assertSee('Atur Jam Ramai (Peak)')
            ->assertDontSee('Simpan Jam Peak')
            ->call('savePeakGrid', array_fill_keys(array_keys(MasterData::DAY_LABELS), array_fill(0, 24, true)))
            ->assertForbidden();
    }
}
