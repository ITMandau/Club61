<?php

namespace Tests\Feature\Sponsor;

use App\Models\Membership\MembershipPlan;
use App\Models\Membership\UserMembership;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Sponsor\SponsorMemberVoucher;
use App\Models\Sponsor\SponsorOrganization;
use App\Models\Sponsor\SponsorOrganizationMember;
use App\Models\User;
use App\Services\Padel\PadelBookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Menyambungkan voucher jam sponsor corporate ke mesin checkout padel yang SESUNGGUHNYA — sebelum
 * ini, kolom sponsor_organization_id/sponsor_member_voucher_id di padel_bookings ada tapi tidak
 * pernah diisi oleh kode apa pun (voucher cuma data dummy, tidak pernah benar-benar dipakai untuk
 * membayar booking).
 */
class SponsorVoucherCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function makeSponsorEmployee(): array
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
        $org = SponsorOrganization::create([
            'name' => 'PT Uji Checkout',
            'user_membership_id' => $membership->id,
            'sponsor_admin_user_id' => $pic->id,
        ]);

        $employee = User::factory()->create(['phone' => '081200000'.random_int(100, 999)]);
        $member = SponsorOrganizationMember::create([
            'sponsor_organization_id' => $org->id,
            'user_id' => $employee->id,
            'status' => 'ACTIVE',
        ]);

        return [$org, $member, $employee];
    }

    protected function makeCourt(): PadelCourt
    {
        return PadelCourt::create([
            'name' => 'Court 1 Centre Panoramic',
            'type' => 'INDOOR',
            'hourly_rate_regular' => 300000.00,
            'hourly_rate_prime' => 350000.00,
            'is_active' => true,
        ]);
    }

    protected function makeLockedBooking(PadelCourt $court, User $user, int $startHour, int $endHour): PadelBooking
    {
        $courtFee = ($endHour - $startHour) * 300000.00;

        return PadelBooking::create([
            'booking_code' => 'BK-'.strtoupper(Str::random(6)),
            'user_id' => $user->id,
            'court_id' => $court->id,
            'booking_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => Carbon::tomorrow()->setTime($startHour, 0),
            'end_time' => Carbon::tomorrow()->setTime($endHour, 0),
            'court_fee' => $courtFee,
            'coach_fee' => 0,
            'equipment_fee' => 0,
            'total_amount' => $courtFee,
            'status' => 'LOCKED',
        ]);
    }

    public function test_padel_checkout_applies_sponsor_voucher_and_deducts_hours(): void
    {
        [$org, $member, $employee] = $this->makeSponsorEmployee();
        $voucher = SponsorMemberVoucher::create([
            'sponsor_organization_member_id' => $member->id,
            'hours_granted' => 5, 'hours_used' => 0,
            'issued_at' => now(), 'expires_at' => now()->addMonth(),
        ]);

        $court = $this->makeCourt();
        $booking = $this->makeLockedBooking($court, $employee, 10, 11); // 1 jam, Rp 300.000

        $padelService = app(PadelBookingService::class);
        $checkoutResult = $padelService->checkout(
            bookingIds: [$booking->id],
            equipments: [],
            voucherCode: null,
            paymentMethod: 'QRIS',
            idempotencyKey: Str::uuid()->toString(),
            user: $employee,
        );

        $this->assertTrue($checkoutResult['success']);

        $booking->refresh();
        $voucher->refresh();

        $this->assertEquals(0.00, (float) $booking->court_fee);
        $this->assertEquals(1.00, (float) $booking->sponsor_hours_consumed);
        $this->assertEquals(300000.00, (float) $booking->sponsor_discount_court);
        $this->assertEquals($org->id, $booking->sponsor_organization_id);
        $this->assertEquals($voucher->id, $booking->sponsor_member_voucher_id);
        $this->assertEquals(1.00, (float) $voucher->hours_used);
    }

    public function test_fully_covered_checkout_labels_payment_as_sponsor_voucher_not_membership_quota(): void
    {
        // Regresi: sebelumnya label pembayaran untuk booking yang Rp 0 SELALU di-hardcode jadi
        // "MEMBERSHIP_QUOTA" walau yang benar-benar menutupnya voucher jam sponsor, bikin invoice
        // salah nampilin "Method: MEMBERSHIP_QUOTA" padahal customer bukan pemegang membership pribadi.
        [, $member, $employee] = $this->makeSponsorEmployee();
        SponsorMemberVoucher::create([
            'sponsor_organization_member_id' => $member->id,
            'hours_granted' => 5, 'hours_used' => 0,
            'issued_at' => now(), 'expires_at' => now()->addMonth(),
        ]);

        $court = $this->makeCourt();
        $booking = $this->makeLockedBooking($court, $employee, 10, 11);

        $padelService = app(PadelBookingService::class);
        $checkoutResult = $padelService->checkout(
            bookingIds: [$booking->id],
            equipments: [],
            voucherCode: null,
            paymentMethod: 'QRIS',
            idempotencyKey: Str::uuid()->toString(),
            user: $employee,
        );

        $order = \App\Models\Pos\Order::where('order_number', $checkoutResult['data']['order_id'])->first();
        $payment = \App\Models\Pos\Payment::where('order_id', $order->id)->latest()->first();

        $this->assertEquals('SPONSOR_VOUCHER', $payment->payment_gateway);
        $this->assertEquals('SPONSOR_VOUCHER', $payment->payment_method);
    }

    public function test_padel_checkout_applies_partial_sponsor_voucher_coverage_when_hours_insufficient(): void
    {
        // Regresi: dulu kalau sisa voucher LEBIH SEDIKIT dari durasi booking (mis. sisa 0.5 jam
        // buat booking 1 jam), sistem melewatkan benefit-nya SAMA SEKALI (all-or-nothing) — bikin
        // voucher kelihatan "tidak aktif" padahal customer masih punya sisa jam. Sekarang jam yang
        // tersedia tetap dipakai (gratis), sisanya (yang belum ke-cover) baru ditagih normal.
        [, $member, $employee] = $this->makeSponsorEmployee();
        $voucher = SponsorMemberVoucher::create([
            'sponsor_organization_member_id' => $member->id,
            'hours_granted' => 0.5, 'hours_used' => 0,
            'issued_at' => now(), 'expires_at' => now()->addMonth(),
        ]);

        $court = $this->makeCourt();
        $booking = $this->makeLockedBooking($court, $employee, 10, 11); // butuh 1 jam @ Rp 300.000, voucher cuma 0.5 jam

        $padelService = app(PadelBookingService::class);
        $padelService->checkout(
            bookingIds: [$booking->id],
            equipments: [],
            voucherCode: null,
            paymentMethod: 'QRIS',
            idempotencyKey: Str::uuid()->toString(),
            user: $employee,
        );

        $booking->refresh();
        $voucher->refresh();

        // 0.5 jam gratis (Rp 150.000 dipotong), sisa 0.5 jam tetap ditagih Rp 150.000.
        $this->assertEquals(150000.00, (float) $booking->court_fee);
        $this->assertEquals(150000.00, (float) $booking->sponsor_discount_court);
        $this->assertEquals(0.5, (float) $booking->sponsor_hours_consumed);
        $this->assertEquals($voucher->id, $booking->sponsor_member_voucher_id);
        $this->assertEquals(0.5, (float) $voucher->hours_used);
    }

    public function test_padel_checkout_applies_partial_sponsor_voucher_coverage_for_reported_six_of_seven_hours_case(): void
    {
        // Skenario persis yang dilaporkan: sisa voucher 6 jam, booking 7 jam — 6 jam harus gratis,
        // 1 jam sisanya baru ditagih, bukan malah nagih semua 7 jam kaya kejadian sebelumnya.
        [, $member, $employee] = $this->makeSponsorEmployee();
        $voucher = SponsorMemberVoucher::create([
            'sponsor_organization_member_id' => $member->id,
            'hours_granted' => 6, 'hours_used' => 0,
            'issued_at' => now(), 'expires_at' => now()->addMonth(),
        ]);

        $court = $this->makeCourt();
        $booking = $this->makeLockedBooking($court, $employee, 14, 21); // 7 jam @ Rp 300.000/jam = Rp 2.100.000

        $padelService = app(PadelBookingService::class);
        $padelService->checkout(
            bookingIds: [$booking->id],
            equipments: [],
            voucherCode: null,
            paymentMethod: 'QRIS',
            idempotencyKey: Str::uuid()->toString(),
            user: $employee,
        );

        $booking->refresh();
        $voucher->refresh();

        // 6 jam gratis (Rp 1.800.000 dipotong), sisa 1 jam tetap ditagih Rp 300.000.
        $this->assertEquals(300000.00, (float) $booking->court_fee);
        $this->assertEquals(1800000.00, (float) $booking->sponsor_discount_court);
        $this->assertEquals(6.0, (float) $booking->sponsor_hours_consumed);
        $this->assertEquals(0.0, (float) $voucher->remainingHours());
    }

    public function test_padel_checkout_can_opt_out_of_sponsor_voucher_with_none(): void
    {
        [, $member, $employee] = $this->makeSponsorEmployee();
        SponsorMemberVoucher::create([
            'sponsor_organization_member_id' => $member->id,
            'hours_granted' => 5, 'hours_used' => 0,
            'issued_at' => now(), 'expires_at' => now()->addMonth(),
        ]);

        $court = $this->makeCourt();
        $booking = $this->makeLockedBooking($court, $employee, 10, 11);

        $padelService = app(PadelBookingService::class);
        $padelService->checkout(
            bookingIds: [$booking->id],
            equipments: [],
            voucherCode: null,
            paymentMethod: 'QRIS',
            idempotencyKey: Str::uuid()->toString(),
            user: $employee,
            membershipBalanceId: null,
            sponsorVoucherId: 'NONE'
        );

        $booking->refresh();

        $this->assertEquals(300000.00, (float) $booking->court_fee);
        $this->assertNull($booking->sponsor_member_voucher_id);
    }

    public function test_padel_checkout_consumes_across_multiple_vouchers_soonest_expiry_first(): void
    {
        [, $member, $employee] = $this->makeSponsorEmployee();
        $expiringSoon = SponsorMemberVoucher::create([
            'sponsor_organization_member_id' => $member->id,
            'hours_granted' => 0.5, 'hours_used' => 0,
            'issued_at' => now(), 'expires_at' => now()->addDays(3),
        ]);
        $expiringLater = SponsorMemberVoucher::create([
            'sponsor_organization_member_id' => $member->id,
            'hours_granted' => 5, 'hours_used' => 0,
            'issued_at' => now(), 'expires_at' => now()->addMonth(),
        ]);

        $court = $this->makeCourt();
        $booking = $this->makeLockedBooking($court, $employee, 10, 12); // 2 jam, butuh 2 jam total

        $padelService = app(PadelBookingService::class);
        $padelService->checkout(
            bookingIds: [$booking->id],
            equipments: [],
            voucherCode: null,
            paymentMethod: 'QRIS',
            idempotencyKey: Str::uuid()->toString(),
            user: $employee,
        );

        $booking->refresh();
        $expiringSoon->refresh();
        $expiringLater->refresh();

        $this->assertEquals(0.00, (float) $booking->court_fee);
        // Voucher yang paling cepat expired habis duluan (0.5 jam), sisanya (1.5 jam) dari voucher lain.
        $this->assertEquals(0.5, (float) $expiringSoon->hours_used);
        $this->assertEquals(1.5, (float) $expiringLater->hours_used);
        $this->assertEquals($expiringSoon->id, $booking->sponsor_member_voucher_id);
    }

    public function test_padel_booking_cancellation_reverses_sponsor_voucher_hours(): void
    {
        [$org, $member, $employee] = $this->makeSponsorEmployee();
        $voucher = SponsorMemberVoucher::create([
            'sponsor_organization_member_id' => $member->id,
            'hours_granted' => 5, 'hours_used' => 1,
            'issued_at' => now(), 'expires_at' => now()->addMonth(),
        ]);

        $court = $this->makeCourt();
        $booking = PadelBooking::create([
            'booking_code' => 'BK-'.strtoupper(Str::random(6)),
            'user_id' => $employee->id,
            'court_id' => $court->id,
            'booking_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => Carbon::tomorrow()->setTime(14, 0),
            'end_time' => Carbon::tomorrow()->setTime(15, 0),
            'court_fee' => 0.00,
            'total_amount' => 0.00,
            'status' => 'PAID',
            'sponsor_organization_id' => $org->id,
            'sponsor_member_voucher_id' => $voucher->id,
            'sponsor_hours_consumed' => 1.00,
        ]);

        $admin = User::factory()->admin()->create();
        $padelService = app(PadelBookingService::class);

        $padelService->adminCancelAndRefund(
            bookingId: $booking->id,
            refundAmount: 0,
            refundMethod: 'ORIGINAL_PAYMENT',
            reasonCategory: 'CUSTOMER_REQUEST',
            notes: 'Batal tanding hujan',
            adminUser: $admin
        );

        $voucher->refresh();
        $this->assertEquals(0.00, (float) $voucher->hours_used);
    }

    public function test_checkout_preview_endpoint_includes_sponsor_voucher_benefit(): void
    {
        [, $member, $employee] = $this->makeSponsorEmployee();
        SponsorMemberVoucher::create([
            'sponsor_organization_member_id' => $member->id,
            'hours_granted' => 5, 'hours_used' => 0,
            'issued_at' => now(), 'expires_at' => now()->addMonth(),
        ]);

        $court = $this->makeCourt();
        $booking = $this->makeLockedBooking($court, $employee, 10, 11);

        $response = $this->actingAs($employee)->postJson('/api/v1/padel/preview-membership-benefit', [
            'booking_ids' => [$booking->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.sponsor_voucher_benefit.has_benefit', true)
            ->assertJsonPath('data.sponsor_voucher_benefit.hours_to_consume', 1)
            ->assertJsonPath('data.sponsor_voucher_benefit.projected_court_total', 0);
    }
}
