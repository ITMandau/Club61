<?php

namespace Tests\Feature\Auth;

use App\Filament\Pages\Analytics;
use App\Filament\Pages\BookingSystem;
use App\Filament\Pages\KelolaPemesanan;
use App\Models\User;
use App\Services\Permission\Club61PermissionMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminPermissionMatrixAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');
    }

    public function test_pure_admin_role_can_access_filament_pages(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'pureadmin@club61.test',
        ]);

        $this->assertFalse($admin->hasRole('super_admin'));
        $this->assertTrue($admin->hasRole('admin'));

        $this->actingAs($admin);

        Livewire::test(BookingSystem::class)->assertStatus(200);
        Livewire::test(KelolaPemesanan::class)->assertStatus(200);
        Livewire::test(Analytics::class)->assertStatus(200);
    }

    public function test_revoking_permission_from_admin_restricts_access(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'restrictedadmin@club61.test',
        ]);

        $adminRole = Role::findByName('admin', 'web');
        $adminRole->revokePermissionTo('View:BookingSystem');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->actingAs($admin);

        $this->assertFalse(BookingSystem::canAccess());
        $this->assertTrue(KelolaPemesanan::canAccess());
    }

    public function test_super_admin_bypasses_matrix_permissions_via_gate_before(): void
    {
        $superAdmin = User::factory()->superAdmin()->create([
            'email' => 'boss@club61.test',
        ]);

        $superAdminRole = Role::findByName('super_admin', 'web');
        $superAdminRole->revokePermissionTo('View:BookingSystem');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->actingAs($superAdmin);

        $this->assertTrue(BookingSystem::canAccess());
        Livewire::test(BookingSystem::class)->assertStatus(200);
    }

    public function test_deploy_grants_only_brand_new_permissions_and_keeps_manual_edits(): void
    {
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(array_diff(Club61PermissionMatrix::getAllPermissionSlugs(), ['View:SponsorDashboard']));
        $admin->syncPermissions(['View:KelolaPemesanan']); // diatur manual lewat menu Roles

        // Server lama: izin modul baru belum pernah dibuat.
        \Spatie\Permission\Models\Permission::whereIn('name', ['manage_booking_time_limits', 'View:LogAktivitas'])->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $created = Club61PermissionMatrix::grantNewPermissionsToPresetRoles('web');

        $this->assertEqualsCanonicalizing(['manage_booking_time_limits', 'View:LogAktivitas'], $created);
        $superAdmin->refresh();
        $this->assertTrue($superAdmin->hasPermissionTo('manage_booking_time_limits'));
        $this->assertTrue($superAdmin->hasPermissionTo('View:LogAktivitas'));
        $this->assertFalse($superAdmin->hasPermissionTo('View:SponsorDashboard'), 'centangan yang dilepas manual tidak dikembalikan');
        $this->assertSame(['View:KelolaPemesanan'], $admin->refresh()->permissions->pluck('name')->all(), 'izin backdoor tidak masuk ke admin');

        $this->assertSame([], Club61PermissionMatrix::grantNewPermissionsToPresetRoles('web'), 'aman dijalankan ulang');
    }
}
