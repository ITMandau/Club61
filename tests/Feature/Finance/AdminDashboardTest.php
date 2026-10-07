<?php

namespace Tests\Feature\Finance;

use App\Filament\Pages\Dashboard;
use App\Models\Finance\LedgerEntry;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\LedgerReport;
use App\Services\Permission\Club61PermissionMatrix;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Dashboard admin memakai data asli. Dulu seluruh isinya contoh mati ("Rp 50.272.597", "132 Bookings",
 * booking "PXDL" 19 Mei 2026). Angka uang hanya untuk yang boleh membuka Analytics.
 */
class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected PadelCourt $court;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');
        LedgerReport::flushMemo();
        $this->travelTo(Carbon::parse('2026-10-20 10:00', LedgerReport::TIMEZONE));
        $this->owner = User::factory()->superAdmin()->create(['name' => 'Owner']);
        $this->court = PadelCourt::create(['name' => 'Court Emas', 'type' => 'INDOOR', 'hourly_rate_regular' => 200000, 'hourly_rate_prime' => 300000, 'is_active' => true, 'open_time' => '08:00', 'close_time' => '18:00']);
    }

    private function ledger(string $at, float $total): void
    {
        LedgerEntry::create([
            'occurred_at' => Carbon::parse($at, LedgerReport::TIMEZONE)->setTimezone(config('app.timezone')),
            'entry_type' => LedgerEntry::TYPE_PAYMENT, 'source' => 'POS_FNB', 'category' => 'FNB',
            'order_id' => (string) Str::ulid(), 'payment_id' => (string) Str::ulid(), 'dedupe_key' => Str::random(20),
            'order_number' => 'ORD-'.Str::upper(Str::random(5)), 'net_amount' => $total, 'gross_amount' => $total, 'total_amount' => $total,
        ]);
    }

    private function booking(string $code, string $status, string $start, string $customerName = 'Budi Santoso'): PadelBooking
    {
        $user = User::factory()->customer()->create(['name' => $customerName]);
        $s = Carbon::parse("2026-10-20 {$start}", LedgerReport::TIMEZONE)->setTimezone(config('app.timezone'));

        return PadelBooking::create([
            'booking_code' => $code, 'user_id' => $user->id, 'court_id' => $this->court->id, 'booking_date' => '2026-10-20',
            'start_time' => $s, 'end_time' => $s->copy()->addHour(), 'court_fee' => 300000, 'total_amount' => 300000, 'status' => $status,
        ]);
    }

    public function test_dashboard_shows_real_data_instead_of_the_old_mock(): void
    {
        $this->ledger('2026-10-20 09:00', 250000);
        $this->ledger('2026-10-19 12:00', 200000);
        $this->booking('BK-DASH-1', 'PAID', '14:00');
        $this->booking('BK-DASH-2', 'CANCELLED', '15:00');

        Livewire::actingAs($this->owner)->test(Dashboard::class)
            ->assertOk()
            ->assertDontSee('50.272.597')
            ->assertDontSee('PXDL')
            ->assertSee('Uang Masuk Hari Ini')
            ->assertSee('Rp 250.000')
            ->assertSee('↗ +25,0%')            // vs kemarin Rp 200.000
            ->assertSee('1 booking')
            ->assertSee('BK-DASH-1')
            ->assertDontSee('BK-DASH-2')         // booking batal tidak masuk jadwal
            ->assertSee('Okupansi 10%')          // 1 jam dari 10 jam buka
            ->assertSee('1 lapangan aktif');
    }

    public function test_schedule_search_and_trend_tabs_work(): void
    {
        $this->booking('BK-CARI-1', 'PAID', '11:00', 'Siti Rahma');
        $this->booking('BK-CARI-2', 'PAID', '12:00', 'Andi Wijaya');

        Livewire::actingAs($this->owner)->test(Dashboard::class)
            ->set('search', 'Siti')
            ->assertSee('BK-CARI-1')
            ->assertDontSee('BK-CARI-2')
            ->call('setTrendDays', 30)
            ->assertSet('trendDays', 30)
            ->assertSee('dalam 30 hari terakhir')
            ->call('setTrendDays', 999)
            ->assertSet('trendDays', 7);
    }

    public function test_trend_days_cannot_be_set_directly_from_the_browser(): void
    {
        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($this->owner)->test(Dashboard::class)->set('trendDays', 90);
    }

    public function test_staff_without_analytics_access_do_not_see_money(): void
    {
        $this->ledger('2026-10-20 09:00', 250000);
        $this->booking('BK-STAF-1', 'PAID', '14:00');
        $role = Role::findOrCreate('frontdesk_dash', 'web');
        $role->givePermissionTo('View:Dashboard');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $staff = User::factory()->create();
        $staff->assignRole($role);

        Livewire::actingAs($staff)->test(Dashboard::class)
            ->assertOk()
            ->assertDontSee('Uang Masuk Hari Ini')
            ->assertDontSee('Rp 250.000')
            ->assertDontSee('Rp 300.000')
            ->assertSee('BK-STAF-1');
    }

    public function test_attention_list_links_to_where_staff_resolve_it(): void
    {
        $order = Order::create(['order_number' => 'ORD-ATT', 'order_type' => 'WALK_IN', 'subtotal' => 150000, 'grand_total' => 150000, 'payment_status' => 'PARTIALLY_PAID']);
        Payment::create(['order_id' => $order->id, 'payment_gateway' => 'CASHIER_POS', 'transaction_id' => 'SUPP-ATT', 'amount' => 150000, 'payment_method' => 'MENUNGGU_PEMBAYARAN', 'status' => 'PENDING']);

        Livewire::actingAs($this->owner)->test(Dashboard::class)
            ->assertSee('Perlu Ditindaklanjuti')
            ->assertSee('1 tagihan menunggu dibayar di kasir')
            ->assertSee('Rp 150.000');
    }
}
