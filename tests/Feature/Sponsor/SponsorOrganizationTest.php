<?php

namespace Tests\Feature\Sponsor;

use App\Models\Membership\MembershipPlan;
use App\Models\Membership\UserMembership;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Sponsor\SponsorAccessSchedule;
use App\Models\Sponsor\SponsorMemberVoucher;
use App\Models\Sponsor\SponsorOrganization;
use App\Models\Sponsor\SponsorOrganizationMember;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class SponsorOrganizationTest extends TestCase
{
    use RefreshDatabase;

    protected function makeOrganizationalMembership(User $pic): UserMembership
    {
        $plan = MembershipPlan::create([
            'code' => 'MBR-CORP-'.uniqid(),
            'name' => 'Corporate B2B Padel',
            'ownership_type' => 'ORGANIZATIONAL',
            'duration_days' => 365,
            'price' => 50000000.00,
            'is_active' => true,
        ]);

        return UserMembership::create([
            'membership_code' => 'MBR-CORP-'.uniqid(),
            'owner_type' => 'ORGANIZATIONAL',
            'user_id' => $pic->id,
            'plan_id' => $plan->id,
            'status' => 'ACTIVE',
            'start_date' => now(),
            'end_date' => now()->addYear(),
        ]);
    }

    public function test_sponsor_organization_links_to_organizational_membership_and_pic(): void
    {
        $pic = User::factory()->create(['name' => 'PIC Perusahaan A']);
        $membership = $this->makeOrganizationalMembership($pic);

        $org = SponsorOrganization::create([
            'name' => 'PT Maju Jaya',
            'user_membership_id' => $membership->id,
            'sponsor_admin_user_id' => $pic->id,
            'status' => 'ACTIVE',
        ]);

        $this->assertTrue($org->userMembership->is($membership));
        $this->assertTrue($org->sponsorAdmin->is($pic));
        $this->assertTrue($membership->sponsorOrganization->is($org));
    }

    public function test_sponsor_organization_requires_unique_membership_and_rejects_duplicate_member_roster_entry(): void
    {
        $pic = User::factory()->create();
        $membership = $this->makeOrganizationalMembership($pic);

        $org = SponsorOrganization::create([
            'name' => 'PT Duplikat Test',
            'user_membership_id' => $membership->id,
            'sponsor_admin_user_id' => $pic->id,
        ]);

        $employee = User::factory()->create();

        SponsorOrganizationMember::create([
            'sponsor_organization_id' => $org->id,
            'user_id' => $employee->id,
            'status' => 'ACTIVE',
        ]);

        $this->expectException(QueryException::class);

        SponsorOrganizationMember::create([
            'sponsor_organization_id' => $org->id,
            'user_id' => $employee->id,
            'status' => 'ACTIVE',
        ]);
    }

    public function test_sponsor_member_voucher_tracks_remaining_hours_and_expiry_independently_per_batch(): void
    {
        $pic = User::factory()->create();
        $membership = $this->makeOrganizationalMembership($pic);
        $org = SponsorOrganization::create([
            'name' => 'PT Voucher Mode',
            'user_membership_id' => $membership->id,
            'sponsor_admin_user_id' => $pic->id,
        ]);

        $employee = User::factory()->create();
        $member = SponsorOrganizationMember::create([
            'sponsor_organization_id' => $org->id,
            'user_id' => $employee->id,
            'status' => 'ACTIVE',
        ]);

        $oldVoucher = SponsorMemberVoucher::create([
            'sponsor_organization_member_id' => $member->id,
            'hours_granted' => 5,
            'hours_used' => 2,
            'issued_at' => now()->subMonth(),
            'expires_at' => now()->subDay(), // sudah expired
            'source' => 'CSV_IMPORT',
        ]);

        $newVoucher = SponsorMemberVoucher::create([
            'sponsor_organization_member_id' => $member->id,
            'hours_granted' => 10,
            'hours_used' => 3,
            'issued_at' => now(),
            'expires_at' => now()->addMonth(),
            'source' => 'MANUAL_RELEASE',
        ]);

        $this->assertTrue($oldVoucher->isExpired());
        $this->assertFalse($oldVoucher->isUsable());
        $this->assertEquals(3.0, $oldVoucher->remainingHours());

        $this->assertFalse($newVoucher->isExpired());
        $this->assertTrue($newVoucher->isUsable());
        $this->assertEquals(7.0, $newVoucher->remainingHours());

        // Anggota punya 2 voucher sekaligus — total sisa jam HANYA menghitung yang belum expired.
        $this->assertEquals(7.0, $member->totalRemainingHours());
    }

    public function test_sponsor_organization_policy_only_allows_the_assigned_pic(): void
    {
        $pic = User::factory()->create();
        $membership = $this->makeOrganizationalMembership($pic);
        $org = SponsorOrganization::create([
            'name' => 'PT Scoped Policy',
            'user_membership_id' => $membership->id,
            'sponsor_admin_user_id' => $pic->id,
        ]);

        $stranger = User::factory()->create();

        $this->assertTrue(Gate::forUser($pic)->allows('update', $org));
        $this->assertTrue(Gate::forUser($pic)->allows('view', $org));
        $this->assertFalse(Gate::forUser($stranger)->allows('update', $org));
        $this->assertFalse(Gate::forUser($stranger)->allows('view', $org));
    }

    public function test_padel_booking_can_be_tagged_to_a_sponsor_organization_and_voucher(): void
    {
        $pic = User::factory()->create();
        $membership = $this->makeOrganizationalMembership($pic);
        $org = SponsorOrganization::create([
            'name' => 'PT Booking Tag',
            'user_membership_id' => $membership->id,
            'sponsor_admin_user_id' => $pic->id,
        ]);

        $employee = User::factory()->create();
        $member = SponsorOrganizationMember::create([
            'sponsor_organization_id' => $org->id,
            'user_id' => $employee->id,
            'status' => 'ACTIVE',
        ]);
        $voucher = SponsorMemberVoucher::create([
            'sponsor_organization_member_id' => $member->id,
            'hours_granted' => 5,
            'issued_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);

        $court = PadelCourt::create([
            'name' => 'Court Corporate Test',
            'hourly_rate_regular' => 200000,
            'hourly_rate_prime' => 300000,
            'is_active' => true,
        ]);

        $booking = PadelBooking::create([
            'booking_code' => 'BK-CORP-TEST',
            'user_id' => $employee->id,
            'court_id' => $court->id,
            'booking_date' => now()->toDateString(),
            'start_time' => now()->setTime(10, 0),
            'end_time' => now()->setTime(11, 0),
            'court_fee' => 0,
            'total_amount' => 0,
            'status' => 'CONFIRMED',
            'sponsor_organization_id' => $org->id,
            'sponsor_member_voucher_id' => $voucher->id,
        ]);

        $this->assertTrue($booking->sponsorOrganization->is($org));
        $this->assertTrue($booking->sponsorMemberVoucher->is($voucher));
    }

    public function test_sponsor_access_schedule_covers_date_time_and_optional_days_of_week(): void
    {
        $pic = User::factory()->create();
        $membership = $this->makeOrganizationalMembership($pic);
        $org = SponsorOrganization::create([
            'name' => 'PT Jadwal Rotasi',
            'user_membership_id' => $membership->id,
            'sponsor_admin_user_id' => $pic->id,
        ]);

        $monday = \Carbon\Carbon::now()->next(\Carbon\Carbon::MONDAY);
        $tuesday = $monday->copy()->addDay();

        $rule = SponsorAccessSchedule::create([
            'sponsor_organization_id' => $org->id,
            'valid_from' => $monday->toDateString(),
            'valid_until' => $monday->copy()->addWeek()->toDateString(),
            'days_of_week' => [1], // Senin saja
            'time_start' => '08:00',
            'time_end' => '16:00',
        ]);

        $this->assertTrue($rule->covers($monday, '09:00:00', '10:00:00'));
        $this->assertFalse($rule->covers($tuesday, '09:00:00', '10:00:00'), 'Bukan hari Senin, harus ditolak.');
        $this->assertFalse($rule->covers($monday, '07:00:00', '08:00:00'), 'Di luar jam mulai, harus ditolak.');
        $this->assertFalse($rule->covers($monday, '15:00:00', '17:00:00'), 'Melebihi jam selesai, harus ditolak.');
        $this->assertFalse($rule->covers($monday->copy()->addMonths(2), '09:00:00', '10:00:00'), 'Di luar rentang tanggal, harus ditolak.');
    }
}
