<?php

namespace Tests\Feature\Sponsor;

use App\Models\Membership\MembershipPlan;
use App\Models\Membership\UserMembership;
use App\Models\Sponsor\SponsorOrganization;
use App\Models\User;
use App\Services\Sponsor\SponsorScheduleService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SponsorScheduleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SponsorScheduleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SponsorScheduleService::class);
    }

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
            'name' => 'PT Jadwal Test',
            'user_membership_id' => $membership->id,
            'sponsor_admin_user_id' => $pic->id,
        ]);
    }

    public function test_staff_can_create_and_delete_access_schedule_rule(): void
    {
        $org = $this->makeOrganization();
        $staff = User::factory()->create();

        $rule = $this->service->createRule($org, '2026-10-01', '2026-10-07', [1, 3, 5], '08:00', '16:00', null, $staff);

        $this->assertDatabaseHas('sponsor_access_schedules', [
            'id' => $rule->id,
            'sponsor_organization_id' => $org->id,
            'created_by_user_id' => $staff->id,
        ]);

        $this->service->deleteRule($rule);
        $this->assertDatabaseMissing('sponsor_access_schedules', ['id' => $rule->id]);
    }

    public function test_create_rule_rejects_invalid_date_range(): void
    {
        $org = $this->makeOrganization();

        $this->expectException(DomainException::class);
        $this->service->createRule($org, '2026-10-10', '2026-10-01', null, '08:00', '16:00');
    }

    public function test_create_rule_rejects_invalid_time_range(): void
    {
        $org = $this->makeOrganization();

        $this->expectException(DomainException::class);
        $this->service->createRule($org, '2026-10-01', '2026-10-07', null, '16:00', '08:00');
    }

    public function test_create_rule_rejects_invalid_day_of_week_value(): void
    {
        $org = $this->makeOrganization();

        $this->expectException(DomainException::class);
        $this->service->createRule($org, '2026-10-01', '2026-10-07', [9], '08:00', '16:00');
    }

    public function test_create_rule_rejects_zero_or_negative_max_concurrent_courts(): void
    {
        $org = $this->makeOrganization();

        $this->expectException(DomainException::class);
        $this->service->createRule($org, '2026-10-01', '2026-10-07', null, '08:00', '16:00', 0);
    }

    public function test_rule_with_no_cap_always_has_capacity(): void
    {
        $org = $this->makeOrganization();
        $rule = $this->service->createRule($org, '2026-10-01', '2026-10-07', null, '08:00', '16:00', null);

        $this->assertTrue($rule->hasCapacityFor(\Carbon\Carbon::parse('2026-10-01'), '09:00:00', '10:00:00'));
    }

    public function test_max_concurrent_courts_blocks_once_cap_of_overlapping_bookings_reached(): void
    {
        $org = $this->makeOrganization();
        $rule = $this->service->createRule($org, '2026-10-01', '2026-10-07', null, '08:00', '16:00', 2);

        $court1 = \App\Models\Padel\PadelCourt::create(['name' => 'Court 1', 'hourly_rate_regular' => 200000, 'hourly_rate_prime' => 300000, 'is_active' => true]);
        $court2 = \App\Models\Padel\PadelCourt::create(['name' => 'Court 2', 'hourly_rate_regular' => 200000, 'hourly_rate_prime' => 300000, 'is_active' => true]);
        $court3 = \App\Models\Padel\PadelCourt::create(['name' => 'Court 3', 'hourly_rate_regular' => 200000, 'hourly_rate_prime' => 300000, 'is_active' => true]);
        $employee = User::factory()->create();

        $makeBooking = function ($court) use ($org, $employee) {
            return \App\Models\Padel\PadelBooking::create([
                'booking_code' => 'BK-'.uniqid(),
                'user_id' => $employee->id,
                'court_id' => $court->id,
                'booking_date' => '2026-10-01',
                'start_time' => '2026-10-01 09:00:00',
                'end_time' => '2026-10-01 10:00:00',
                'court_fee' => 0,
                'total_amount' => 0,
                'status' => 'CONFIRMED',
                'sponsor_organization_id' => $org->id,
            ]);
        };

        $bookingDate = \Carbon\Carbon::parse('2026-10-01');

        // Belum ada booking sama sekali — masih ada kapasitas.
        $this->assertTrue($rule->hasCapacityFor($bookingDate, '09:00:00', '10:00:00'));

        $makeBooking($court1);
        $this->assertTrue($rule->hasCapacityFor($bookingDate, '09:00:00', '10:00:00'));

        $makeBooking($court2);
        // Sudah 2 lapangan terpakai bersamaan (cap = 2) — lapangan ke-3 harus ditolak.
        $this->assertFalse($rule->hasCapacityFor($bookingDate, '09:00:00', '10:00:00'));

        // Jam yang tidak overlap (mis. sore) tetap harus punya kapasitas penuh.
        $this->assertTrue($rule->hasCapacityFor($bookingDate, '14:00:00', '15:00:00'));
    }

    public function test_staff_admin_can_open_sponsor_access_schedule_page(): void
    {
        \App\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->syncRoles(['super_admin']);

        $this->actingAs($admin)
            ->get('/admin/sponsor/sponsor-access-schedules')
            ->assertStatus(200);
    }
}
