<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\KelolaPemesanan;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\User;
use App\Services\Permission\Club61PermissionMatrix;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kelola Pemesanan tidak menumpuk: tampilan dibatasi periode tanggal main (bawaan: bulan ini & mendatang), tab "Semua
 * Aktif" tanpa yang batal / refund, angka tab ikut periode. Data lama tetap ada & tetap bisa dicari.
 */
class KelolaPemesananPeriodTest extends TestCase
{
    use RefreshDatabase;

    private PadelCourt $court;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');
        $this->actingAs(User::factory()->admin()->create());
        $this->court = PadelCourt::create(['name' => 'Court BCA', 'type' => 'INDOOR', 'hourly_rate_regular' => 200000, 'hourly_rate_prime' => 300000, 'is_active' => true]);
    }

    private function booking(string $name, Carbon $day, string $status): PadelBooking
    {
        $start = $day->copy()->setTime(10, 0);

        return PadelBooking::create([
            'booking_code' => 'BK-'.strtoupper(Str::random(6)), 'user_id' => User::factory()->customer()->create(['name' => $name])->id,
            'court_id' => $this->court->id, 'booking_date' => $day->toDateString(), 'start_time' => $start, 'end_time' => $start->copy()->addHour(),
            'court_fee' => 200000, 'total_amount' => 200000, 'status' => $status,
        ]);
    }

    public function test_default_shows_this_month_and_upcoming_without_old_or_cancelled_bookings(): void
    {
        $lastMonth = now()->subMonthNoOverflow()->startOfMonth()->addDays(2);
        $this->booking('Lama Bulan Lalu', $lastMonth, 'COMPLETED');
        $this->booking('Awal Bulan Ini', now()->startOfMonth(), 'COMPLETED');
        $this->booking('Minggu Depan', now()->addDays(8), 'PAID');
        $this->booking('Batal Bulan Ini', now()->startOfMonth(), 'CANCELLED');

        $page = Livewire::test(KelolaPemesanan::class)
            ->assertSet('period', 'current')
            ->assertSee('Semua Aktif')
            ->assertSee('Awal Bulan Ini')->assertSee('Minggu Depan')
            ->assertDontSee('Lama Bulan Lalu')->assertDontSee('Batal Bulan Ini')
            ->assertViewHas('counts', fn (array $c) => $c['ALL'] === 2 && $c['CANCELLED'] === 1 && $c['COMPLETED'] === 1);

        // Tab dibatalkan tetap menampilkan yang batal.
        $page->call('setTab', 'CANCELLED')->assertSee('Batal Bulan Ini')->assertDontSee('Minggu Depan');

        // Bulan lalu lewat pilihan Bulanan + ‹.
        $page->call('setTab', 'ALL')->set('period', 'month')->call('shiftPeriod', -1)
            ->assertSee('Lama Bulan Lalu')->assertDontSee('Awal Bulan Ini')
            ->assertViewHas('counts', fn (array $c) => $c['ALL'] === 1);

        // Pencarian selalu mencakup semua periode.
        $page->set('period', 'current')->set('search', 'Lama Bulan')->assertSee('Lama Bulan Lalu')->assertSee('Pencarian mencakup semua periode');

        // Semua Data & rentang tanggal.
        $page->set('search', '')->set('period', 'all')->assertSee('Lama Bulan Lalu')->assertSee('Minggu Depan');
        $page->set('period', 'range')->set('rangeStart', $lastMonth->toDateString())->set('rangeEnd', $lastMonth->toDateString())
            ->assertSee('Lama Bulan Lalu')->assertDontSee('Awal Bulan Ini');
    }

    public function test_booking_date_status_index_exists(): void
    {
        $this->assertTrue(Schema::hasIndex('padel_bookings', 'idx_padel_booking_date_status'));
    }
}
