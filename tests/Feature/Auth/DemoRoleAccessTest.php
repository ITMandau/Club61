<?php

namespace Tests\Feature\Auth;

use App\Filament\Pages\Analytics;
use App\Filament\Pages\BookingSystem;
use App\Filament\Pages\BookOfflineCourt;
use App\Filament\Pages\KelolaPemesanan;
use App\Filament\Pages\Kustomer;
use App\Filament\Pages\MasterData;
use App\Filament\Pages\PengaturanBiayaPajak;
use App\Filament\Resources\Roles\RoleResource;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Role;
use App\Models\User;
use App\Services\Permission\Club61PermissionMatrix;
use Carbon\Carbon;
use Database\Seeders\DemoAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class DemoRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');
    }

    private function paidBooking(?Carbon $start = null): PadelBooking
    {
        $court = PadelCourt::create([
            'name' => 'Court Demo',
            'type' => 'INDOOR',
            'hourly_rate_regular' => 200000,
            'hourly_rate_prime' => 300000,
            'is_active' => true,
        ]);
        $start ??= Carbon::parse(now()->addDays(2)->format('Y-m-d').' 08:00:00');

        return PadelBooking::create([
            'booking_code' => 'BK-DEMO-001',
            'user_id' => User::factory()->customer()->create()->id,
            'court_id' => $court->id,
            'booking_date' => $start->format('Y-m-d'),
            'start_time' => $start,
            'end_time' => $start->copy()->addHour(),
            'court_fee' => 200000,
            'total_amount' => 200000,
            'status' => 'PAID',
            'qr_code_hash' => 'hash_demo',
        ]);
    }

    public function test_admin_preset_contains_no_backdoor_permission(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (Club61PermissionMatrix::BACKDOOR_PERMISSIONS as $permission) {
            $this->assertFalse($admin->can($permission), "Admin tidak boleh punya izin [{$permission}]");
        }

        $superAdmin = User::factory()->superAdmin()->create();
        foreach (Club61PermissionMatrix::BACKDOOR_PERMISSIONS as $permission) {
            $this->assertTrue($superAdmin->can($permission));
        }
    }

    public function test_admin_does_not_see_or_run_refund_and_reschedule_but_super_admin_does(): void
    {
        $booking = $this->paidBooking();

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(KelolaPemesanan::class)
            ->assertSee('BK-DEMO-001')
            ->assertDontSee('Batalkan Reservasi &amp; Refund', false)
            ->assertDontSee('Pindah Jadwal (Reschedule)')
            ->assertSee('Check-In Customer (Scan QR)')
            ->call('openCancelRefundModal', $booking->id)
            ->assertSet('showCancelRefundModal', false)
            ->call('openRescheduleModal', $booking->id)
            ->assertSet('showRescheduleModal', false);

        $this->actingAs(User::factory()->superAdmin()->create());
        Livewire::test(KelolaPemesanan::class)
            ->assertSee('Batalkan Reservasi &amp; Refund', false)
            ->assertSee('Pindah Jadwal (Reschedule)')
            ->call('openCancelRefundModal', $booking->id)
            ->assertSet('showCancelRefundModal', true);
    }

    public function test_customer_page_is_super_admin_only_while_admin_keeps_monitoring_and_bookings(): void
    {
        foreach (['admin', 'receptionist', 'cashier', 'kitchen'] as $role) {
            $this->actingAs(User::factory()->role($role)->create());
            $this->assertFalse(Kustomer::canAccess(), "Role [{$role}] tidak boleh membuka halaman Customer");
        }

        $this->actingAs(User::factory()->admin()->create());
        $this->assertTrue(BookingSystem::canAccess());
        $this->assertTrue(KelolaPemesanan::canAccess());

        $this->actingAs(User::factory()->superAdmin()->create());
        $this->assertTrue(Kustomer::canAccess());
    }

    public function test_admin_cannot_open_tax_and_role_matrix_pages(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->assertFalse(PengaturanBiayaPajak::canAccess());
        $this->assertFalse(RoleResource::canViewAny());
        $this->assertTrue(Analytics::canAccess());
    }

    public function test_admin_manages_equipment_but_not_court_pricing(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(MasterData::class)
            ->assertSet('canManageEquipment', true)
            ->assertSet('canManageCourts', false)
            ->assertDontSee('Atur Jam Buka-Tutup Massal')
            ->call('openCreateCourtModal')
            ->assertForbidden();
    }

    public function test_admin_cannot_edit_or_delete_super_admin_account(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $staff = User::factory()->cashier()->create();
        $admin = User::factory()->admin()->create();
        Role::findByName('admin', 'web')->givePermissionTo('delete_users');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->assertFalse(Gate::forUser($admin)->allows('update', $superAdmin));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $superAdmin));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $staff));

        $this->assertTrue(Gate::forUser(User::factory()->superAdmin()->create())->allows('update', $superAdmin));
    }

    public function test_receptionist_handles_walk_in_and_check_in_only(): void
    {
        // Check-in baru dibuka 45 menit sebelum sesi dimulai.
        $booking = $this->paidBooking(now()->addMinutes(10)->startOfMinute());
        $receptionist = User::factory()->receptionist()->create();
        $this->actingAs($receptionist);

        $this->assertTrue(BookOfflineCourt::canAccess());
        $this->assertTrue(KelolaPemesanan::canAccess());
        $this->assertTrue($receptionist->can('process_walkin_booking'));
        $this->assertFalse(MasterData::canAccess());
        $this->assertFalse(Analytics::canAccess());

        Livewire::test(KelolaPemesanan::class)
            ->assertSee('Scan QR / Check-In Gate')
            ->assertDontSee('Batalkan Reservasi &amp; Refund', false)
            ->assertDontSee('Pindah Jadwal (Reschedule)')
            ->set('checkInQuery', 'BK-DEMO-001')
            ->call('executeCheckIn');

        $this->assertSame('CHECKED_IN', $booking->fresh()->status);

        $this->get('/pos')->assertForbidden();
        $this->get('/kitchen')->assertForbidden();
    }

    public function test_emptied_role_permissions_do_not_grow_back_when_new_user_is_created(): void
    {
        User::factory()->admin()->create();
        Role::findByName('admin', 'web')->syncPermissions([]);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $newAdmin = User::create([
            'name' => 'Admin Baru',
            'email' => 'admin.baru@club61.test',
            'password' => bcrypt('secret-123'),
            'role' => 'ADMIN',
            'is_active' => true,
        ]);

        $this->assertCount(0, Role::findByName('admin', 'web')->permissions);
        $this->assertFalse($newAdmin->fresh()->can('View:Analytics'));
    }

    public function test_demo_access_seeder_keeps_manual_role_edits_but_strips_backdoors(): void
    {
        $this->seed(DemoAccessSeeder::class);

        $adminRole = Role::findByName('admin', 'web');
        $adminRole->syncPermissions(['View:KelolaPemesanan', 'View:SponsorDashboard', 'cancel_refund_padel']);

        $this->seed(DemoAccessSeeder::class);

        $this->assertEqualsCanonicalizing(
            ['View:KelolaPemesanan', 'View:SponsorDashboard'],
            $adminRole->fresh()->permissions->pluck('name')->all()
        );
    }

    public function test_demo_access_seeder_is_idempotent_and_keeps_existing_passwords(): void
    {
        $existing = User::factory()->create([
            'email' => 'manager@club61.com',
            'password' => Hash::make('sandi-milik-user'),
        ]);

        $this->seed(DemoAccessSeeder::class);
        $this->seed(DemoAccessSeeder::class);

        $this->assertSame(1, User::where('email', 'manager@club61.com')->count());
        $this->assertTrue(Hash::check('sandi-milik-user', $existing->fresh()->password));
        $this->assertTrue($existing->fresh()->hasRole('admin'));

        $receptionist = User::where('email', 'resepsionis@club61.com')->firstOrFail();
        $this->assertTrue($receptionist->hasRole('receptionist'));
        $this->assertTrue(Hash::check(DemoAccessSeeder::DEFAULT_PASSWORD, $receptionist->password));

        $this->assertTrue(User::where('email', 'admin@club61.com')->firstOrFail()->hasRole('super_admin'));
        $this->assertFalse(User::where('email', 'manager@club61.com')->firstOrFail()->can('cancel_refund_padel'));
    }
}
