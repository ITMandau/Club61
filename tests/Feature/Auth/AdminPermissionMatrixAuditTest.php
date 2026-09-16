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
}
