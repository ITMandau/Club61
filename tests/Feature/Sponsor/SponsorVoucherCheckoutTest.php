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

    /** @return array{0: SponsorMemberVoucher, 1: SponsorMemberVoucher, 2: User} voucher September (cepat expired) & Oktober */
    private function twoVouchers(float $septemberHours, float $octoberHours): array
    {
        [, $member, $employee] = $this->makeSponsorEmployee();
        $september = SponsorMemberVoucher::create([
            'sponsor_organization_member_id' => $member->id,
            'hours_granted' => $septemberHours, 'hours_used' => 0,
            'issued_at' => now(), 'expires_at' => now()->addDays(3),
        ]);
        $october = SponsorMemberVoucher::create([
            'sponsor_organization_member_id' => $member->id,
            'hours_granted' => $octoberHours, 'hours_used' => 0,
            'issued_at' => now(), 'expires_at' => now()->addMonth(),
        ]);

        return [$september, $october, $employee];
    }

    private function checkoutBookings(User $employee, array $bookings): void
    {
        app(PadelBookingService::class)->checkout(
            bookingIds: array_map(fn ($b) => $b->id, $bookings),
            equipments: [],
            voucherCode: null,
            paymentMethod: 'QRIS',
            idempotencyKey: Str::uuid()->toString(),
            user: $employee,
        );
    }

    public function test_one_transaction_uses_one_voucher_the_soonest_expiring_that_covers_it(): void
    {
        // September sisa 0,5 jam (tidak cukup), Oktober 5 jam → booking 2 jam diambil SEMUA dari Oktober, tidak digabung.
        [$september, $october, $employee] = $this->twoVouchers(0.5, 5);
        $booking = $this->makeLockedBooking($this->makeCourt(), $employee, 10, 12);

        $this->checkoutBookings($employee, [$booking]);

        $booking->refresh();
        $this->assertEquals(0.00, (float) $booking->court_fee);
        $this->assertEquals(0.0, (float) $september->fresh()->hours_used);
        $this->assertEquals(2.0, (float) $october->fresh()->hours_used);
        $this->assertEquals($october->id, $booking->sponsor_member_voucher_id);

        // Keduanya cukup → yang paling cepat expired (supaya jamnya tidak hangus sia-sia).
        [$september, $october, $employee] = $this->twoVouchers(3, 5);
        $booking = $this->makeLockedBooking($this->makeCourt(), $employee, 14, 16);
        $this->checkoutBookings($employee, [$booking]);
        $this->assertEquals(2.0, (float) $september->fresh()->hours_used);
        $this->assertEquals(0.0, (float) $october->fresh()->hours_used);
    }

    public function test_when_no_voucher_covers_the_transaction_the_rest_is_paid_not_taken_from_another_voucher(): void
    {
        // September 1 jam, Oktober 1,5 jam, transaksi 2 booking × 1 jam: dipakai Oktober (terbanyak), sisa 0,5 jam dibayar.
        [$september, $october, $employee] = $this->twoVouchers(1, 1.5);
        $court = $this->makeCourt();
        $first = $this->makeLockedBooking($court, $employee, 10, 11);
        $second = $this->makeLockedBooking($court, $employee, 11, 12);

        $this->checkoutBookings($employee, [$first, $second]);

        $this->assertEquals(0.0, (float) $september->fresh()->hours_used, 'voucher lain tidak ikut dipotong');
        $this->assertEquals(1.5, (float) $october->fresh()->hours_used);
        $this->assertEquals(0.00, (float) $first->fresh()->court_fee);
        $this->assertEquals(150000.00, (float) $second->fresh()->court_fee, 'setengah jam sisanya dibayar normal');
        $this->assertEquals($october->id, $second->fresh()->sponsor_member_voucher_id);
    }

    public function test_cancelled_booking_returns_hours_to_its_voucher_only_once(): void
    {
        [$september, $october, $employee] = $this->twoVouchers(0.5, 5);
        $booking = $this->makeLockedBooking($this->makeCourt(), $employee, 10, 12);
        $this->checkoutBookings($employee, [$booking]);
        $service = app(PadelBookingService::class);

        \Illuminate\Support\Facades\DB::transaction(fn () => $service->reverseBookingBenefits($booking->fresh()));
        $this->assertEquals(0.0, (float) $october->fresh()->hours_used);
        $this->assertEquals(0.0, (float) $booking->fresh()->sponsor_hours_consumed);

        // Dipanggil lagi (mis. webhook + pembersih slot) → tidak mengembalikan jam dua kali.
        $october->update(['hours_used' => 3]); // dipakai booking lain di sela-sela
        \Illuminate\Support\Facades\DB::transaction(fn () => $service->reverseBookingBenefits($booking->fresh()));
        $this->assertEquals(3.0, (float) $october->fresh()->hours_used);
    }

    public function test_gateway_expire_racing_the_slot_cleaner_returns_voucher_hours_only_once(): void
    {
        [$org, $member, $employee] = $this->makeSponsorEmployee();
        // 2 jam terpakai: 1 jam booking ini + 1 jam booking lain.
        $voucher = SponsorMemberVoucher::create([
            'sponsor_organization_member_id' => $member->id,
            'hours_granted' => 5, 'hours_used' => 2,
            'issued_at' => now(), 'expires_at' => now()->addMonth(),
        ]);
        $order = \App\Models\Pos\Order::create(['order_number' => 'ORD-RACE', 'user_id' => $employee->id, 'order_type' => 'ONLINE_BOOKING', 'subtotal' => 0, 'grand_total' => 50000, 'payment_status' => 'UNPAID']);
        \App\Models\Pos\Payment::create(['order_id' => $order->id, 'payment_gateway' => 'MIDTRANS', 'transaction_id' => 'ORD-RACE', 'amount' => 50000, 'payment_method' => 'QRIS', 'status' => 'PENDING']);
        $booking = PadelBooking::create([
            'booking_code' => 'BK-RACE', 'order_id' => $order->id, 'user_id' => $employee->id, 'court_id' => $this->makeCourt()->id,
            'booking_date' => Carbon::tomorrow()->toDateString(), 'start_time' => Carbon::tomorrow()->setTime(14, 0), 'end_time' => Carbon::tomorrow()->setTime(15, 0),
            'court_fee' => 0, 'total_amount' => 50000, 'status' => 'PENDING_PAYMENT', 'expires_at' => now()->subMinutes(10),
            'sponsor_organization_id' => $org->id, 'sponsor_member_voucher_id' => $voucher->id, 'sponsor_hours_consumed' => 1,
        ]);

        // Pembersih slot menghanguskan booking & mengembalikan 1 jam TEPAT setelah webhook membaca data bookingnya.
        $fired = false;
        PadelBooking::retrieved(function (PadelBooking $b) use (&$fired, $booking, $voucher) {
            $inWebhook = collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 40))->contains(fn ($f) => ($f['function'] ?? '') === 'handleGatewayCancellation');
            if ($fired || ! $inWebhook || $b->id !== $booking->id) {
                return;
            }
            $fired = true;
            \Illuminate\Support\Facades\DB::table('padel_bookings')->where('id', $booking->id)->update(['status' => 'EXPIRED', 'sponsor_hours_consumed' => 0]);
            \Illuminate\Support\Facades\DB::table('sponsor_member_vouchers')->where('id', $voucher->id)->update(['hours_used' => 1]);
        });

        $this->postJson('/api/v1/padel/webhook/midtrans', [
            'order_id' => 'ORD-RACE', 'status_code' => '407', 'gross_amount' => '50000.00', 'transaction_status' => 'expire', 'signature_key' => 'tanpa-server-key-di-test',
        ])->assertOk();

        $this->assertTrue($fired);
        $this->assertEquals(1.0, (float) $voucher->fresh()->hours_used, 'jam dikembalikan sekali saja (dulu dua kali → 0)');
        $this->assertSame('EXPIRED', $booking->fresh()->status, 'status hangus tidak ditimpa CANCELLED');
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

        $padelService->requestCancelAndRefund(
            bookingId: $booking->id,
            reasonCategory: 'CUSTOMER_REQUEST',
            notes: 'Batal tanding hujan',
            requester: $admin
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
