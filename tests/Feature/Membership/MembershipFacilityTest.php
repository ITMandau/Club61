<?php

namespace Tests\Feature\Membership;

use App\Filament\Resources\Membership\Facilities\Pages\ManageMembershipFacilities;
use App\Filament\Resources\Membership\Pages\CreateMembershipPlan;
use App\Models\Membership\MembershipFacility;
use App\Models\Membership\MembershipPlan;
use App\Models\Membership\MembershipPlanBenefit;
use App\Models\User;
use App\Services\Membership\MembershipBalanceService;
use App\Services\Membership\MembershipFacilityService;
use App\Services\Permission\Club61PermissionMatrix;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Master Fasilitas membership: admin bisa menambah fasilitas & mengatur teks benefit yang dilihat customer.
 * Dulu fasilitas tetap PADEL/GYM/SAUNA dan halaman membership berisi teks dummy ("Technogym", "WPT standard").
 */
class MembershipFacilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');
        $this->customer = User::factory()->customer()->create();
    }

    private function facilities(): MembershipFacilityService
    {
        return app(MembershipFacilityService::class);
    }

    private function pool(string $mode = MembershipFacility::MODE_CHECK_IN): MembershipFacility
    {
        return MembershipFacility::create(['code' => 'POOL', 'name' => 'Kolam Renang', 'badge' => 'POOL', 'description' => 'Kolam renang semi-olympic.', 'usage_mode' => $mode, 'is_active' => true, 'sort_order' => 5]);
    }

    private function plan(array $benefits, array $attrs = []): MembershipPlan
    {
        $plan = MembershipPlan::create(array_merge(['code' => 'MBR-T'.random_int(100, 999), 'name' => 'Paket Uji', 'ownership_type' => 'INDIVIDUAL', 'duration_days' => 30, 'price' => 1000000, 'is_active' => true], $attrs));
        foreach ($benefits as $b) {
            MembershipPlanBenefit::create(array_merge(['plan_id' => $plan->id, 'quota_value' => null, 'discount_percent' => 0], $b));
        }

        return $plan->fresh('benefits');
    }

    private function activeMembership(MembershipPlan $plan)
    {
        $service = app(MembershipBalanceService::class);
        $membership = $service->purchasePlan($this->customer, $plan);

        return $service->activateMembership($membership)->fresh('balances');
    }

    public function test_system_facilities_are_seeded_and_locked(): void
    {
        $this->assertSame(['PADEL', 'GYM', 'SAUNA'], array_keys($this->facilities()->all()));

        $gym = MembershipFacility::where('code', 'GYM')->first();
        $gym->update(['name' => 'Gym Club 61', 'description' => 'Area gym lantai 2.']);
        $this->assertSame('Gym Club 61', $this->facilities()->name('GYM'), 'nama & deskripsi boleh diubah');

        try {
            $gym->update(['usage_mode' => MembershipFacility::MODE_INFO]);
            $this->fail('Mode fasilitas sistem tidak boleh diubah.');
        } catch (\LogicException) {
        }

        $this->expectException(\LogicException::class);
        $gym->fresh()->delete();
    }

    public function test_admin_can_add_a_facility_but_only_as_check_in_or_info(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        Livewire::test(ManageMembershipFacilities::class)
            ->callAction('create', ['code' => 'pool', 'name' => 'Kolam Renang', 'badge' => 'pool', 'description' => 'Kolam renang.', 'usage_mode' => MembershipFacility::MODE_CHECK_IN, 'sort_order' => 5, 'is_active' => true])
            ->assertHasNoActionErrors();

        $pool = MembershipFacility::where('code', 'POOL')->firstOrFail();
        $this->assertFalse($pool->is_system);
        $this->assertSame('POOL', $pool->badge);
        $this->assertSame(MembershipFacility::MODE_CHECK_IN, $this->facilities()->mode('POOL'));

        Livewire::test(ManageMembershipFacilities::class)
            ->callAction('create', ['code' => 'COURT2', 'name' => 'Palsu', 'badge' => 'X', 'usage_mode' => MembershipFacility::MODE_PADEL_BOOKING, 'sort_order' => 6, 'is_active' => true])
            ->assertHasActionErrors(['usage_mode']);
    }

    public function test_staff_without_permission_cannot_add_facilities(): void
    {
        $kitchen = User::factory()->kitchen()->create();
        \App\Models\Role::findByName('kitchen', 'web')->givePermissionTo('view_membership_plans');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($kitchen);

        Livewire::test(ManageMembershipFacilities::class)->assertActionHidden('create');
    }

    public function test_view_only_staff_cannot_delete_plans_or_sponsors_from_the_table(): void
    {
        // Dulu tombol hapus di tabel memakai policy model (tidak ada) → staf yang hanya boleh MELIHAT bisa menghapus.
        $kitchen = User::factory()->kitchen()->create();
        \App\Models\Role::findByName('kitchen', 'web')->givePermissionTo(['view_membership_plans', 'view_sponsor_organizations']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $plan = $this->plan([]);
        $orgPlan = $this->plan([], ['ownership_type' => 'ORGANIZATIONAL']);
        $sponsor = \App\Models\Sponsor\SponsorOrganization::create(['name' => 'PT Uji', 'user_membership_id' => app(MembershipBalanceService::class)->purchasePlan($this->customer, $orgPlan)->id, 'sponsor_admin_user_id' => $this->customer->id]);
        $this->actingAs($kitchen);

        Livewire::test(\App\Filament\Resources\Membership\Pages\ListMembershipPlans::class)
            ->assertTableActionHidden('delete', $plan)
            ->assertTableActionHidden('edit', $plan);
        Livewire::test(\App\Filament\Resources\Sponsor\Pages\ListSponsorOrganizations::class)
            ->assertTableActionHidden('delete', $sponsor);

        $this->assertNotNull(MembershipPlan::find($plan->id));
    }

    public function test_paid_quota_survives_deactivation_and_locks_the_usage_mode(): void
    {
        $pool = $this->pool();
        $plan = $this->plan([['facility' => 'POOL', 'quota_type' => 'VISITS', 'quota_value' => 3]]);
        $membership = $this->activeMembership($plan);

        // Berhenti dijual: kuota yang sudah dibayar tetap bisa dipakai.
        $pool->update(['is_active' => false]);
        app(MembershipBalanceService::class)->recordCheckin($membership->balanceFor('POOL')->id, $this->customer->id);
        $this->assertEquals(2, (float) $membership->balanceFor('POOL')->fresh()->remaining_quota);

        // Benefit dihapus dari paket, tapi kartu member masih memakainya → mode tetap terkunci & tidak bisa dihapus.
        MembershipPlanBenefit::where('plan_id', $plan->id)->delete();
        $this->assertTrue($pool->fresh()->isInUse());
        $this->actingAs(User::factory()->superAdmin()->create());
        Livewire::test(ManageMembershipFacilities::class)->assertTableActionHidden('delete', $pool);

        $this->expectException(\LogicException::class);
        $pool->fresh()->update(['usage_mode' => MembershipFacility::MODE_INFO]);
    }

    public function test_check_in_picks_the_card_that_can_actually_be_used(): void
    {
        // Kartu 1 (berakhir duluan): Gym diskon saja. Kartu 2: Gym 5 sesi. Dulu kartu pertama dipilih → ditolak.
        $this->activeMembership($this->plan([['facility' => 'GYM', 'quota_type' => 'NONE', 'discount_percent' => 10]], ['duration_days' => 10]));
        $second = $this->activeMembership($this->plan([['facility' => 'GYM', 'quota_type' => 'VISITS', 'quota_value' => 5]], ['duration_days' => 60]));

        $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/membership/checkin-gym')->assertOk();
        $this->assertEquals(4, (float) $second->balanceFor('GYM')->fresh()->remaining_quota);
    }

    public function test_used_facility_cannot_be_deleted(): void
    {
        $pool = $this->pool();
        $this->plan([['facility' => 'POOL', 'quota_type' => 'VISITS', 'quota_value' => 8]]);

        $this->expectException(\LogicException::class);
        $pool->delete();
    }

    public function test_check_in_works_for_new_facilities_and_respects_quota(): void
    {
        $this->pool();
        $membership = $this->activeMembership($this->plan([['facility' => 'POOL', 'quota_type' => 'VISITS', 'quota_value' => 1]]));

        $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/membership/checkin', ['facility' => 'POOL'])
            ->assertOk()->assertJsonPath('data.facility', 'POOL');
        $this->assertEquals(0, (float) $membership->balanceFor('POOL')->fresh()->remaining_quota);

        $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/membership/checkin', ['facility' => 'POOL'])
            ->assertStatus(422);
    }

    public function test_unlimited_gym_check_in_works_and_discount_only_is_not_free_entry(): void
    {
        $unlimited = $this->activeMembership($this->plan([['facility' => 'GYM', 'quota_type' => 'VISITS', 'quota_value' => null]]));
        $service = app(MembershipBalanceService::class);

        // Dulu selalu gagal: sistem mencoba memotong kuota dari saldo 0.
        $service->recordCheckin($unlimited->balanceFor('GYM')->id, $this->customer->id);
        $service->recordCheckin($unlimited->balanceFor('GYM')->id, $this->customer->id);
        $this->assertSame(2, \App\Models\Membership\FacilityCheckin::count());

        $other = User::factory()->customer()->create();
        $this->customer = $other;
        $discountOnly = $this->activeMembership($this->plan([['facility' => 'GYM', 'quota_type' => 'NONE', 'discount_percent' => 20]]));

        $this->expectException(DomainException::class);
        $service->recordCheckin($discountOnly->balanceFor('GYM')->id, $other->id);
    }

    public function test_info_facilities_cannot_be_checked_in(): void
    {
        $this->pool(MembershipFacility::MODE_INFO);
        $membership = $this->activeMembership($this->plan([['facility' => 'POOL', 'quota_type' => 'NONE']]));

        $this->expectException(DomainException::class);
        app(MembershipBalanceService::class)->recordCheckin($membership->balanceFor('POOL')->id, $this->customer->id);
    }

    public function test_plan_form_only_offers_quota_types_that_fit_the_facility(): void
    {
        $this->pool(MembershipFacility::MODE_INFO);
        $this->actingAs(User::factory()->superAdmin()->create());

        Livewire::test(CreateMembershipPlan::class)
            ->fillForm([
                'code' => 'MBR-INFO', 'name' => 'Paket Info', 'ownership_type' => 'INDIVIDUAL', 'duration_days' => 30, 'price' => 500000, 'is_active' => true,
                'benefits' => [['facility' => 'POOL', 'quota_type' => 'HOURS', 'quota_value' => 5]],
            ])
            ->call('create')
            ->assertHasFormErrors(['benefits.0.quota_type']);

        $this->assertSame(0, MembershipPlan::where('code', 'MBR-INFO')->count());
    }

    public function test_membership_page_shows_admin_texts_instead_of_dummy_copy(): void
    {
        $this->pool();
        $this->plan([
            ['facility' => 'PADEL', 'quota_type' => 'HOURS', 'quota_value' => 10, 'discount_percent' => 20, 'booking_priority_days' => 7],
            ['facility' => 'POOL', 'quota_type' => 'VISITS', 'quota_value' => 8, 'extra_benefits' => ['note' => 'Termasuk handuk & loker']],
        ], ['name' => 'Silver Uji', 'description' => 'Paket untuk pemain rutin.', 'perks' => ['Free parkir VIP']]);

        $this->actingAs($this->customer)->get(route('customer.membership'))
            ->assertOk()
            ->assertSee('10 Jam Padel Court')
            ->assertSee('Booking hingga H-7 lebih awal')
            ->assertSee('8 Sesi Kolam Renang')
            ->assertSee('Termasuk handuk &amp; loker', false)
            ->assertSee('Paket untuk pemain rutin.')
            ->assertSee('Free parkir VIP')
            ->assertDontSee('Technogym')
            ->assertDontSee('panoramic courts WPT')
            ->assertDontSee('Free VIP Valet Parking');
    }

    public function test_deactivated_facility_is_hidden_from_sales_pages(): void
    {
        $pool = $this->pool();
        $plan = $this->plan([['facility' => 'POOL', 'quota_type' => 'VISITS', 'quota_value' => 8], ['facility' => 'GYM', 'quota_type' => 'VISITS', 'quota_value' => 4]]);
        $pool->update(['is_active' => false]);

        $titles = array_column($this->facilities()->presentPlan($plan), 'title');
        $this->assertSame(['4 Sesi Fitness & Gym'], $titles);
        $this->assertSame($titles, array_column($this->getJson('/api/v1/membership/plans')->json('data.0.benefit_cards'), 'title'));
    }
}
