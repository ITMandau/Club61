<?php

namespace Tests\Feature\Sponsor;

use App\Filament\Pages\SponsorDashboard;
use App\Models\Membership\MembershipPlan;
use App\Models\Membership\MembershipPlanBenefit;
use App\Models\Role;
use App\Models\Sponsor\SponsorOrganization;
use App\Models\User;
use App\Services\Permission\Club61PermissionMatrix;
use App\Services\Sponsor\SponsorOrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SponsorDashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    protected SponsorOrganization $organization;

    protected User $pic;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');

        $plan = MembershipPlan::create([
            'code' => 'MBR-CORP-T',
            'name' => 'Corporate Tier Test',
            'ownership_type' => 'ORGANIZATIONAL',
            'duration_days' => 365,
            'price' => 25000000,
            'is_active' => true,
        ]);
        MembershipPlanBenefit::create([
            'plan_id' => $plan->id,
            'facility' => 'PADEL',
            'quota_type' => 'HOURS',
            'quota_value' => 200,
            'discount_percent' => 0,
        ]);

        $this->pic = User::factory()->customer()->create(['name' => 'Rina PIC']);
        $this->organization = app(SponsorOrganizationService::class)->grantCorporateMembership(
            $this->pic, $plan, 'PT Demo Sponsor', User::factory()->superAdmin()->create(), 'uji'
        );
    }

    private function sponsorViewer(): User
    {
        $role = Role::create(['name' => 'sponsor_viewer', 'guard_name' => 'web']);
        $role->syncPermissions(['View:SponsorDashboard']);
        $user = User::factory()->create();
        $user->syncRoles([$role]);

        return $user;
    }

    public function test_staff_are_sent_back_to_panel_instead_of_customer_portal(): void
    {
        // Admin yang kebetulan tercatat sebagai PIC tetap tidak boleh masuk portal customer.
        $admin = User::factory()->admin()->create();
        $this->organization->update(['sponsor_admin_user_id' => $admin->id]);

        foreach (['/dashboard', '/booking', '/my-club', '/corporate'] as $path) {
            $this->actingAs($admin)->get($path)->assertRedirect('/admin');
        }

        $cashierRole = Role::findOrCreate('cashier', 'web');
        $cashierRole->update(['home_route' => '/pos']);
        $this->actingAs(User::factory()->cashier()->create())->get('/dashboard')->assertRedirect('/pos');

        $this->actingAs($this->pic)->get('/dashboard')->assertOk();
    }

    public function test_role_with_only_sponsor_dashboard_permission_lands_on_it_and_sees_nothing_else(): void
    {
        $viewer = $this->sponsorViewer();

        $this->actingAs($viewer)->get('/admin')->assertRedirect(SponsorDashboard::getUrl());

        $response = $this->actingAs($viewer)->get(SponsorDashboard::getUrl());
        $response->assertOk()
            ->assertSee('Dashboard Sponsor')
            ->assertSee('PT Demo Sponsor — PIC: Rina PIC')
            ->assertDontSee('Kelola Pemesanan')
            ->assertDontSee('Monitoring Lapangan');

        $this->actingAs($viewer)->get('/admin/kelola-pemesanan')->assertForbidden();
    }

    public function test_embedded_pic_dashboard_has_no_customer_navigation_and_allows_same_origin_frame(): void
    {
        $response = $this->actingAs($this->sponsorViewer())
            ->get(route('corporate.preview', $this->organization->id));

        $response->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertSee('MODE PRATINJAU STAF')
            ->assertSee('Rina PIC')
            ->assertDontSee('href="'.route('customer.booking').'"', false)
            ->assertDontSee('const API_BASE', false);

        // Halaman lain tetap tidak boleh di-frame sama sekali.
        $this->actingAs($this->pic)->get('/dashboard')->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_admin_sees_edit_and_delete_buttons_even_though_not_the_pic(): void
    {
        // Regresi: tombol tabel dulu mengikuti SponsorOrganizationPolicy (khusus PIC), jadi
        // admin non-PIC kehilangan tombol Edit walau punya manage_sponsor_organizations.
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(\App\Filament\Resources\Sponsor\Pages\ListSponsorOrganizations::class)
            ->assertTableActionVisible('edit', $this->organization)
            ->assertTableActionVisible('delete', $this->organization);

        $withoutManage = User::factory()->admin()->create();
        Role::findByName('admin', 'web')->revokePermissionTo('manage_sponsor_organizations');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->actingAs($withoutManage);

        Livewire::test(\App\Filament\Resources\Sponsor\Pages\ListSponsorOrganizations::class)
            ->assertTableActionHidden('edit', $this->organization)
            ->assertTableActionHidden('delete', $this->organization);
    }

    public function test_sponsor_dashboard_page_is_hidden_without_permission(): void
    {
        $this->actingAs(User::factory()->cashier()->create());
        $this->assertFalse(SponsorDashboard::canAccess());

        $this->actingAs(User::factory()->cashier()->create())
            ->get(route('corporate.preview', $this->organization->id))
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create());
        $this->assertTrue(SponsorDashboard::canAccess());
        Livewire::test(SponsorDashboard::class)
            ->assertSet('organizationId', $this->organization->id)
            ->assertSee(route('corporate.preview', ['organization' => $this->organization->id]), false);
    }
}
