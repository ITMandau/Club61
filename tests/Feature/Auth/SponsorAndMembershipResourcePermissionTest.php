<?php

namespace Tests\Feature\Auth;

use App\Filament\Resources\Membership\MembershipPlanResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Sponsor\SponsorAccessScheduleResource;
use App\Filament\Resources\Sponsor\SponsorOrganizationResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\Permission\Club61PermissionMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Sebelum perbaikan ini, 3 Filament Resource (SponsorOrganizationResource,
 * SponsorAccessScheduleResource, MembershipPlanResource) sama sekali tidak punya Model Policy
 * terdaftar untuk model masing-masing — Filament fallback ke "Gate::before-only", yang berarti
 * viewAny/create/update/delete otomatis TERBUKA untuk SEMUA staf yang login, apapun rolenya
 * (lihat get_authorization_response() di vendor/filament/filament/src/helpers.php:80-93). Test
 * ini memverifikasi staf TANPA permission eksplisit sekarang ditolak.
 *
 * UserResource & RoleResource sebaliknya SUDAH punya Policy terdaftar, tapi mengecek slug gaya
 * Filament Shield ("ViewAny:User") yang tidak pernah disinkron ke Club61PermissionMatrix — jadi
 * cuma super_admin yang bisa akses walau role "admin" sudah diberi permission view_users dkk.
 * Test ini memverifikasi role "admin" (bukan super_admin) sekarang benar-benar bisa akses.
 */
class SponsorAndMembershipResourcePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');
    }

    public function test_staff_without_permission_cannot_manage_sponsor_organizations(): void
    {
        $cashier = User::factory()->cashier()->create();
        $this->actingAs($cashier);

        $this->assertFalse(SponsorOrganizationResource::canViewAny());
        $this->assertFalse(SponsorOrganizationResource::canCreate());
    }

    public function test_admin_role_can_manage_sponsor_organizations(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $this->assertTrue(SponsorOrganizationResource::canViewAny());
        $this->assertTrue(SponsorOrganizationResource::canCreate());
    }

    public function test_staff_without_permission_cannot_manage_sponsor_access_schedules(): void
    {
        $cashier = User::factory()->cashier()->create();
        $this->actingAs($cashier);

        $this->assertFalse(SponsorAccessScheduleResource::canViewAny());
        $this->assertFalse(SponsorAccessScheduleResource::canCreate());
    }

    public function test_admin_role_can_manage_sponsor_access_schedules(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $this->assertTrue(SponsorAccessScheduleResource::canViewAny());
        $this->assertTrue(SponsorAccessScheduleResource::canCreate());
    }

    public function test_staff_without_permission_cannot_manage_membership_plans(): void
    {
        $cashier = User::factory()->cashier()->create();
        $this->actingAs($cashier);

        $this->assertFalse(MembershipPlanResource::canViewAny());
        $this->assertFalse(MembershipPlanResource::canCreate());
    }

    public function test_admin_role_can_manage_membership_plans(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $this->assertTrue(MembershipPlanResource::canViewAny());
        $this->assertTrue(MembershipPlanResource::canCreate());
    }

    public function test_super_admin_bypasses_sponsor_and_membership_resource_checks_even_without_permission(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        Role::findByName('super_admin', 'web')->syncPermissions([]);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->actingAs($superAdmin);

        $this->assertTrue(SponsorOrganizationResource::canViewAny());
        $this->assertTrue(SponsorAccessScheduleResource::canViewAny());
        $this->assertTrue(MembershipPlanResource::canViewAny());
    }

    public function test_admin_role_can_access_users_but_role_matrix_stays_super_admin_only_by_default(): void
    {
        // Regresi: UserPolicy/RolePolicy dulu mengecek slug "ViewAny:User"/"ViewAny:Role" gaya
        // Filament Shield yang tidak pernah ada di Club61PermissionMatrix, jadi tetap ke-deny
        // walau role sudah diberi view_users/view_roles.
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $this->assertTrue(UserResource::canViewAny());

        // Matriks role = pintu belakang (admin yang bisa ubah matriks = bisa kasih dirinya akses penuh).
        $this->assertFalse(RoleResource::canViewAny());

        // Tapi policy-nya tetap membaca slug yang benar kalau super_admin sengaja memberikannya.
        Role::findByName('admin', 'web')->givePermissionTo('view_roles');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->assertTrue(RoleResource::canViewAny());
    }

    public function test_staff_without_permission_still_cannot_access_user_and_role_resources(): void
    {
        $cashier = User::factory()->cashier()->create();
        $this->actingAs($cashier);

        $this->assertFalse(UserResource::canViewAny());
        $this->assertFalse(RoleResource::canViewAny());
    }
}
