<?php

namespace Tests\Feature\Sponsor;

use App\Models\Membership\MembershipPlan;
use App\Models\Membership\UserMembership;
use App\Models\Sponsor\SponsorMemberVoucher;
use App\Models\Sponsor\SponsorOrganization;
use App\Models\Sponsor\SponsorOrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SponsorVoucherClaimTest extends TestCase
{
    use RefreshDatabase;

    protected function makeOrganization(): SponsorOrganization
    {
        $pic = User::factory()->create();
        $plan = MembershipPlan::create([
            'code' => 'MBR-CORP-'.uniqid(),
            'name' => 'Corporate B2B Padel',
            'ownership_type' => 'ORGANIZATIONAL',
            'duration_days' => 365,
            'price' => 50000000.00,
            'is_active' => true,
        ]);
        $membership = UserMembership::create([
            'membership_code' => 'MBR-CORP-'.uniqid(),
            'owner_type' => 'ORGANIZATIONAL',
            'user_id' => $pic->id,
            'plan_id' => $plan->id,
            'status' => 'ACTIVE',
            'start_date' => now(),
            'end_date' => now()->addYear(),
        ]);

        return SponsorOrganization::create([
            'name' => 'PT Voucher Claim Test',
            'user_membership_id' => $membership->id,
            'sponsor_admin_user_id' => $pic->id,
        ]);
    }

    public function test_dashboard_embeds_unacknowledged_voucher_data_for_the_claim_popup(): void
    {
        $org = $this->makeOrganization();
        $employee = User::factory()->create();
        $member = SponsorOrganizationMember::create([
            'sponsor_organization_id' => $org->id, 'user_id' => $employee->id, 'status' => 'ACTIVE',
        ]);
        SponsorMemberVoucher::create([
            'sponsor_organization_member_id' => $member->id,
            'hours_granted' => 10, 'hours_used' => 2,
            'issued_at' => now(), 'expires_at' => now()->addMonth(),
        ]);

        $response = $this->actingAs($employee)->get('/dashboard');

        $response->assertStatus(200)->assertSee('PT Voucher Claim Test', false);
        $this->assertStringContainsString('hours', $response->getContent());
        $this->assertStringContainsString(':8,', $response->getContent());
    }

    public function test_dashboard_does_not_embed_already_acknowledged_or_expired_vouchers(): void
    {
        $org = $this->makeOrganization();
        $employee = User::factory()->create();
        $member = SponsorOrganizationMember::create([
            'sponsor_organization_id' => $org->id, 'user_id' => $employee->id, 'status' => 'ACTIVE',
        ]);
        SponsorMemberVoucher::create([
            'sponsor_organization_member_id' => $member->id,
            'hours_granted' => 5, 'hours_used' => 0,
            'issued_at' => now(), 'expires_at' => now()->addMonth(),
            'acknowledged_at' => now(),
        ]);
        SponsorMemberVoucher::create([
            'sponsor_organization_member_id' => $member->id,
            'hours_granted' => 5, 'hours_used' => 0,
            'issued_at' => now()->subMonths(2), 'expires_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($employee)->get('/dashboard');

        $response->assertStatus(200)->assertSee('dashboardApp([])', false);
    }

    public function test_employee_can_acknowledge_their_own_voucher(): void
    {
        $org = $this->makeOrganization();
        $employee = User::factory()->create();
        $member = SponsorOrganizationMember::create([
            'sponsor_organization_id' => $org->id, 'user_id' => $employee->id, 'status' => 'ACTIVE',
        ]);
        $voucher = SponsorMemberVoucher::create([
            'sponsor_organization_member_id' => $member->id,
            'hours_granted' => 10, 'hours_used' => 0,
            'issued_at' => now(), 'expires_at' => now()->addMonth(),
        ]);

        $this->actingAs($employee)
            ->postJson("/api/v1/sponsor/my-vouchers/{$voucher->id}/acknowledge")
            ->assertStatus(200);

        $this->assertNotNull($voucher->fresh()->acknowledged_at);
    }

    public function test_employee_cannot_acknowledge_another_employees_voucher(): void
    {
        $org = $this->makeOrganization();
        $employeeA = User::factory()->create();
        $employeeB = User::factory()->create();
        $memberB = SponsorOrganizationMember::create([
            'sponsor_organization_id' => $org->id, 'user_id' => $employeeB->id, 'status' => 'ACTIVE',
        ]);
        $voucherOfB = SponsorMemberVoucher::create([
            'sponsor_organization_member_id' => $memberB->id,
            'hours_granted' => 10, 'hours_used' => 0,
            'issued_at' => now(), 'expires_at' => now()->addMonth(),
        ]);

        $this->actingAs($employeeA)
            ->postJson("/api/v1/sponsor/my-vouchers/{$voucherOfB->id}/acknowledge")
            ->assertStatus(404);

        $this->assertNull($voucherOfB->fresh()->acknowledged_at);
    }

    public function test_my_club_page_shows_corporate_voucher_section_with_status_labels(): void
    {
        $org = $this->makeOrganization();
        $employee = User::factory()->create();
        $member = SponsorOrganizationMember::create([
            'sponsor_organization_id' => $org->id, 'user_id' => $employee->id, 'status' => 'ACTIVE',
        ]);
        SponsorMemberVoucher::create([
            'sponsor_organization_member_id' => $member->id,
            'hours_granted' => 10, 'hours_used' => 3,
            'issued_at' => now(), 'expires_at' => now()->addMonth(),
        ]);
        SponsorMemberVoucher::create([
            'sponsor_organization_member_id' => $member->id,
            'hours_granted' => 4, 'hours_used' => 0,
            'issued_at' => now(), 'expires_at' => now()->addMonth(),
            'acknowledged_at' => now(),
        ]);

        $response = $this->actingAs($employee)->get('/my-club');

        $response->assertStatus(200)
            ->assertSee('PT Voucher Claim Test')
            ->assertSee('Corporate Team Benefit')
            ->assertSee('New')
            ->assertSee('Claimed')
            ->assertSee('id="corporate-vouchers"', false);
    }

    public function test_dashboard_shows_my_vouchers_quick_action_tile_only_for_corporate_members(): void
    {
        $org = $this->makeOrganization();
        $employee = User::factory()->create();
        $member = SponsorOrganizationMember::create([
            'sponsor_organization_id' => $org->id, 'user_id' => $employee->id, 'status' => 'ACTIVE',
        ]);
        SponsorMemberVoucher::create([
            'sponsor_organization_member_id' => $member->id,
            'hours_granted' => 10, 'hours_used' => 0,
            'issued_at' => now(), 'expires_at' => now()->addMonth(),
        ]);

        // "hrs active" cuma dirender di dalam tile Quick Action "My Vouchers" (di-gate @if
        // Blade server-side) — beda dari teks link "View All My Vouchers" di popup modal yang
        // memang selalu ada di HTML buat semua user (cuma disembunyikan via Alpine x-show).
        $this->actingAs($employee)->get('/dashboard')->assertSee('hrs active');

        $regularUser = User::factory()->create();
        $this->actingAs($regularUser)->get('/dashboard')->assertDontSee('hrs active');
    }
}
