<?php

namespace Tests\Feature\Sponsor;

use App\Filament\Pages\JualMembership;
use App\Models\Membership\MembershipPlan;
use App\Models\Membership\MembershipPlanBenefit;
use App\Models\Membership\UserMembership;
use App\Models\Pos\PosCashierShift;
use App\Models\Sponsor\SponsorOrganization;
use App\Models\User;
use App\Services\Membership\MembershipBalanceService;
use App\Services\Sponsor\SponsorOrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pembeli paket membership ORGANIZATIONAL otomatis jadi PIC (SponsorOrganization) begitu
 * membership-nya aktif — berlaku sama rata dari kanal manapun, karena hook-nya ditaruh di
 * MembershipFulfillmentHandler (satu-satunya titik aktivasi, bukan didup di tiap kanal).
 */
class SponsorOrganizationAutoCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function makeOrgPlan(): MembershipPlan
    {
        $plan = MembershipPlan::create([
            'code' => 'MBR-CORP-AUTO-'.uniqid(),
            'name' => 'Corporate Auto Test',
            'ownership_type' => 'ORGANIZATIONAL',
            'duration_days' => 365,
            'price' => 20000000.00,
            'is_active' => true,
        ]);
        MembershipPlanBenefit::create([
            'plan_id' => $plan->id,
            'facility' => 'PADEL',
            'quota_type' => 'HOURS',
            'quota_value' => 200,
        ]);

        return $plan;
    }

    public function test_service_auto_creates_organization_for_organizational_membership(): void
    {
        $buyer = User::factory()->create(['name' => 'Budi Corporate']);
        $plan = $this->makeOrgPlan();

        $membership = app(MembershipBalanceService::class)->purchasePlan($buyer, $plan, ['status' => 'ACTIVE']);

        $org = app(SponsorOrganizationService::class)->ensureOrganizationForMembership($membership);

        $this->assertNotNull($org);
        $this->assertEquals($buyer->id, $org->sponsor_admin_user_id);
        $this->assertEquals($membership->id, $org->user_membership_id);
        $this->assertStringContainsString('Budi Corporate', $org->name);
    }

    public function test_service_is_idempotent_and_skips_individual_memberships(): void
    {
        $buyer = User::factory()->create();
        $plan = $this->makeOrgPlan();
        $membership = app(MembershipBalanceService::class)->purchasePlan($buyer, $plan, ['status' => 'ACTIVE']);
        $service = app(SponsorOrganizationService::class);

        $first = $service->ensureOrganizationForMembership($membership);
        $second = $service->ensureOrganizationForMembership($membership);
        $this->assertTrue($first->is($second));
        $this->assertEquals(1, SponsorOrganization::count());

        $individualPlan = MembershipPlan::create([
            'code' => 'MBR-IND-AUTO-'.uniqid(),
            'name' => 'Individual Test',
            'ownership_type' => 'INDIVIDUAL',
            'duration_days' => 30,
            'price' => 100000,
            'is_active' => true,
        ]);
        $individualMembership = app(MembershipBalanceService::class)->purchasePlan(User::factory()->create(), $individualPlan, ['status' => 'ACTIVE']);
        $this->assertNull($service->ensureOrganizationForMembership($individualMembership));
        $this->assertEquals(1, SponsorOrganization::count());
    }

    public function test_pos_jual_membership_purchase_of_organizational_plan_auto_creates_sponsor_organization(): void
    {
        \App\Services\Permission\Club61PermissionMatrix::syncAllPermissions('web');
        $cashier = User::factory()->cashier()->create(['is_active' => true]);
        $cashier->givePermissionTo(['View:JualMembership', 'sell_membership']);
        PosCashierShift::create([
            'shift_number' => 'SFT-PADEL-'.now()->format('Ymd').'-0001',
            'counter' => 'PADEL_FRONTDESK',
            'status' => 'OPEN',
            'opened_by_id' => $cashier->id,
            'opened_at' => now(),
            'starting_cash' => 0.00,
            'expected_cash' => 0.00,
        ]);
        $this->actingAs($cashier);

        $plan = $this->makeOrgPlan();

        Livewire::test(JualMembership::class)
            ->set('selectedPlanId', $plan->id)
            ->set('customerMode', 'quick_create')
            ->set('walkInName', 'PT Pembeli Corporate')
            ->set('walkInPhone', '081277778888')
            ->set('paymentMethod', 'QRIS')
            ->set('qrisProvider', 'GOPAY_QRIS')
            ->set('qrisMode', 'MANUAL')->set('qrisRrn', '112233445566')
            ->set('qrisSenderName', 'PT Pembeli Corporate')
            ->call('submitSale');

        $buyer = User::where('phone', '081277778888')->first();
        $this->assertNotNull($buyer);

        $membership = UserMembership::where('user_id', $buyer->id)->where('owner_type', 'ORGANIZATIONAL')->first();
        $this->assertNotNull($membership);
        $this->assertEquals('ACTIVE', $membership->status);

        $org = SponsorOrganization::where('user_membership_id', $membership->id)->first();
        $this->assertNotNull($org, 'SponsorOrganization harus otomatis terbentuk dari pembelian POS Jual Membership.');
        $this->assertEquals($buyer->id, $org->sponsor_admin_user_id);
    }

    public function test_staff_can_open_kelola_sponsor_korporat_page(): void
    {
        \App\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->syncRoles(['super_admin']);

        $this->actingAs($admin)
            ->get('/admin/sponsor/sponsor-organizations')
            ->assertStatus(200);
    }
}
