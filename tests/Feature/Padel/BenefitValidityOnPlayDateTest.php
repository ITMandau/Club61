<?php

namespace Tests\Feature\Padel;

use App\Models\Membership\MembershipPlan;
use App\Models\Membership\UserMembership;
use App\Models\Membership\UserMembershipBalance;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\ClubFinanceSetting;
use App\Models\Sponsor\SponsorMemberVoucher;
use App\Models\Sponsor\SponsorOrganization;
use App\Models\Sponsor\SponsorOrganizationMember;
use App\Models\User;
use App\Services\Padel\PadelBookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Audit 18 Okt 2026 (T1, T2, T3): benefit dinilai menurut TANGGAL & JAM MAIN, bukan hari checkout.
 * - Kuota member tidak boleh menanggung booking setelah membership habis; voucher sponsor tidak boleh dipakai untuk
 *   jam main setelah voucher habis (dulu cukup "masih aktif hari ini").
 * - Jam akses paket menghitung slot 23:00–24:00 dengan benar (dulu "00:00:00" lolos dari paket 06:00–16:00).
 * - Voucher organisasi yang Ditangguhkan tidak bisa dipakai.
 * - Checkout online (deteksi otomatis) tidak pernah gagal karena kartu yang tidak berlaku → harga normal.
 */
class BenefitValidityOnPlayDateTest extends TestCase
{
    use RefreshDatabase;

    protected PadelBookingService $service;

    protected User $customer;

    protected PadelCourt $court;

    protected function setUp(): void
    {
        parent::setUp();
        ClubFinanceSetting::getSettings()->update(['is_admin_fee_enabled' => false, 'is_tax_enabled' => false]);
        Cache::forget(ClubFinanceSetting::CACHE_KEY);

        $this->service = app(PadelBookingService::class);
        $this->customer = User::factory()->customer()->create(['name' => 'Budi']);
        $this->court = PadelCourt::create([
            'name' => 'Court BV', 'type' => 'INDOOR', 'hourly_rate_regular' => 200000, 'hourly_rate_prime' => 200000, 'is_active' => true,
        ]);
    }

    private function membership(string $endDate, ?array $window = null, float $quota = 10, string $code = 'A'): UserMembershipBalance
    {
        $plan = MembershipPlan::create(['code' => 'MBR-BV-'.$code, 'name' => 'Gold '.$code, 'ownership_type' => 'INDIVIDUAL', 'duration_days' => 30, 'price' => 1000000, 'is_active' => true]);
        $membership = UserMembership::create([
            'membership_code' => 'MBR-BV-'.$code, 'owner_type' => 'INDIVIDUAL', 'user_id' => $this->customer->id, 'plan_id' => $plan->id,
            'status' => 'ACTIVE', 'start_date' => now()->subDays(5)->toDateString(), 'end_date' => $endDate, 'qr_pass_hash' => 'QR-BV-'.$code,
        ]);

        return UserMembershipBalance::create([
            'user_membership_id' => $membership->id, 'facility' => 'PADEL', 'quota_type' => 'HOURS',
            'initial_quota' => $quota, 'remaining_quota' => $quota,
            'time_window_start' => $window[0] ?? null, 'time_window_end' => $window[1] ?? null,
        ]);
    }

    private function lockedBooking(Carbon $start, ?Carbon $end = null, ?User $user = null): PadelBooking
    {
        $end ??= $start->copy()->addHour();

        return PadelBooking::create([
            'booking_code' => 'BK-'.strtoupper(Str::random(6)), 'user_id' => ($user ?? $this->customer)->id, 'court_id' => $this->court->id,
            'booking_date' => $start->toDateString(), 'start_time' => $start, 'end_time' => $end,
            'court_fee' => 200000, 'coach_fee' => 0, 'equipment_fee' => 0, 'total_amount' => 200000,
            'status' => 'LOCKED', 'expires_at' => now()->addMinutes(10),
        ]);
    }

    private function checkout(PadelBooking $booking, ?string $balanceId = null, ?User $user = null): array
    {
        return $this->service->checkout(
            bookingIds: [$booking->id], equipments: [], voucherCode: null, paymentMethod: 'QRIS',
            idempotencyKey: Str::uuid()->toString(), user: $user ?? $this->customer, membershipBalanceId: $balanceId,
        );
    }

    public function test_quota_is_not_used_for_a_play_date_after_the_membership_ends(): void
    {
        $balance = $this->membership(now()->addDays(3)->toDateString());
        $booking = $this->lockedBooking(now()->addDays(20)->setTime(10, 0));

        $preview = $this->service->previewMembershipBenefit([$booking->id], $this->customer);
        $this->assertFalse($preview['has_benefit']);
        $this->assertSame('MEMBERSHIP_ENDS_BEFORE_PLAY_DATE', $preview['rejected_reason']);

        // Deteksi otomatis (checkout online) → harga normal, bukan error.
        $this->checkout($booking);
        $this->assertEquals(200000, (float) $booking->fresh()->court_fee);
        $this->assertNull($booking->fresh()->membership_balance_id);
        $this->assertEquals(10, (float) $balance->fresh()->remaining_quota);
    }

    public function test_quota_still_covers_play_dates_up_to_the_last_valid_day(): void
    {
        $endDate = now()->addDays(3)->toDateString();
        $balance = $this->membership($endDate);
        $booking = $this->lockedBooking(Carbon::parse($endDate)->setTime(10, 0));

        $this->checkout($booking);

        $this->assertEquals(0, (float) $booking->fresh()->court_fee);
        $this->assertEquals(9, (float) $balance->fresh()->remaining_quota);
    }

    public function test_auto_detect_picks_the_card_that_is_still_valid_on_the_play_date(): void
    {
        $short = $this->membership(now()->addDays(2)->toDateString(), code: 'S');
        $long = $this->membership(now()->addDays(60)->toDateString(), code: 'L');
        $booking = $this->lockedBooking(now()->addDays(20)->setTime(10, 0));

        $this->checkout($booking);

        $this->assertSame($long->id, $booking->fresh()->membership_balance_id);
        $this->assertEquals(10, (float) $short->fresh()->remaining_quota);
        $this->assertEquals(9, (float) $long->fresh()->remaining_quota);
    }

    public function test_explicitly_chosen_card_that_ends_before_the_play_date_is_rejected(): void
    {
        $balance = $this->membership(now()->addDays(3)->toDateString());
        $booking = $this->lockedBooking(now()->addDays(20)->setTime(10, 0));

        try {
            $this->checkout($booking, $balance->id);
            $this->fail('kartu yang habis sebelum tanggal main harus ditolak');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertStringContainsString('berakhir', $e->getMessage());
        }
        $this->assertEquals(10, (float) $balance->fresh()->remaining_quota);
    }

    public function test_slot_ending_at_midnight_is_outside_a_morning_package_window(): void
    {
        $balance = $this->membership(now()->addDays(30)->toDateString(), ['06:00:00', '16:00:00']);
        $start = now()->addDays(2)->setTime(23, 0);
        $booking = $this->lockedBooking($start, $start->copy()->addHour()); // selesai 00:00 hari berikutnya

        $preview = $this->service->previewMembershipBenefit([$booking->id], $this->customer);
        $this->assertSame('OUTSIDE_TIME_WINDOW', $preview['rejected_reason']);

        $this->checkout($booking); // dulu: kuota terpakai (lolos), atau 422 untuk kartu paket jam lain
        $this->assertEquals(200000, (float) $booking->fresh()->court_fee);
        $this->assertEquals(10, (float) $balance->fresh()->remaining_quota);
    }

    public function test_night_package_until_midnight_covers_its_evening_slots(): void
    {
        $balance = $this->membership(now()->addDays(30)->toDateString(), ['18:00:00', '00:00:00']);
        $booking = $this->lockedBooking(now()->addDays(2)->setTime(20, 0));

        $this->checkout($booking); // dulu "21:00:00" > "00:00:00" → paket malam menolak slot 18:00–23:00

        $this->assertEquals(0, (float) $booking->fresh()->court_fee);
        $this->assertEquals(9, (float) $balance->fresh()->remaining_quota);
    }

    public function test_walk_in_quote_follows_the_same_play_date_rules(): void
    {
        $this->membership(now()->addDays(3)->toDateString());
        $date = now()->addDays(20)->toDateString();

        $quote = $this->service->quoteWalkInBenefits([['court_id' => $this->court->id, 'start_time' => '10:00', 'end_time' => '11:00']], $date, $this->customer);

        $this->assertFalse($quote['membership']['applies']);
        $this->assertSame('MEMBERSHIP_ENDS_BEFORE_PLAY_DATE', $quote['membership']['rejected_reason']);
        $this->assertEquals(0, $quote['membership_discount']);
    }

    private function sponsorEmployee(string $orgStatus = SponsorOrganization::STATUS_ACTIVE, ?Carbon $voucherExpires = null): array
    {
        $pic = User::factory()->create();
        $plan = MembershipPlan::create(['code' => 'MBR-CORP-BV', 'name' => 'Corporate BV', 'ownership_type' => 'ORGANIZATIONAL', 'duration_days' => 365, 'price' => 1, 'is_active' => true]);
        $membership = UserMembership::create([
            'membership_code' => 'MBR-CORP-BV', 'owner_type' => 'ORGANIZATIONAL', 'user_id' => $pic->id, 'plan_id' => $plan->id,
            'status' => 'ACTIVE', 'start_date' => now()->toDateString(), 'end_date' => now()->addYear()->toDateString(),
        ]);
        $org = SponsorOrganization::create(['name' => 'PT BV', 'user_membership_id' => $membership->id, 'sponsor_admin_user_id' => $pic->id, 'status' => $orgStatus]);
        $employee = User::factory()->create(['phone' => '081200009'.random_int(100, 999)]);
        $member = SponsorOrganizationMember::create(['sponsor_organization_id' => $org->id, 'user_id' => $employee->id, 'status' => 'ACTIVE']);
        $voucher = SponsorMemberVoucher::create([
            'sponsor_organization_member_id' => $member->id, 'hours_granted' => 5, 'hours_used' => 0,
            'issued_at' => now(), 'expires_at' => $voucherExpires ?? now()->addMonth(),
        ]);

        return [$employee, $voucher, $org];
    }

    public function test_sponsor_voucher_is_not_used_for_play_after_it_expires(): void
    {
        [$employee, $voucher] = $this->sponsorEmployee(voucherExpires: now()->addDays(5));
        $booking = $this->lockedBooking(now()->addDays(20)->setTime(10, 0), user: $employee);

        $this->checkout($booking, user: $employee);

        $this->assertEquals(200000, (float) $booking->fresh()->court_fee);
        $this->assertNull($booking->fresh()->sponsor_member_voucher_id);
        $this->assertEquals(0, (float) $voucher->fresh()->hours_used);

        $quote = $this->service->quoteWalkInBenefits([['court_id' => $this->court->id, 'start_time' => '10:00', 'end_time' => '11:00']], now()->addDays(20)->toDateString(), $employee);
        $this->assertFalse($quote['sponsor']['applies']);
    }

    public function test_sponsor_voucher_covers_play_before_it_expires(): void
    {
        [$employee, $voucher] = $this->sponsorEmployee(voucherExpires: now()->addDays(5));
        $booking = $this->lockedBooking(now()->addDays(2)->setTime(10, 0), user: $employee);

        $this->checkout($booking, user: $employee);

        $this->assertEquals(0, (float) $booking->fresh()->court_fee);
        $this->assertEquals(1, (float) $voucher->fresh()->hours_used);
    }

    public function test_vouchers_of_a_suspended_organization_cannot_be_used(): void
    {
        [$employee, $voucher] = $this->sponsorEmployee(SponsorOrganization::STATUS_SUSPENDED);
        $booking = $this->lockedBooking(now()->addDays(2)->setTime(10, 0), user: $employee);

        $this->checkout($booking, user: $employee);
        $this->assertEquals(200000, (float) $booking->fresh()->court_fee);
        $this->assertEquals(0, (float) $voucher->fresh()->hours_used);

        $quote = $this->service->quoteWalkInBenefits([['court_id' => $this->court->id, 'start_time' => '12:00', 'end_time' => '13:00']], now()->addDays(2)->toDateString(), $employee);
        $this->assertFalse($quote['sponsor']['applies']);

        // Customer melihat vouchernya tidak aktif, bukan "Applied automatically at checkout".
        $this->actingAs($employee)->get('/profile/vouchers?tab=history')->assertOk()->assertSee('Deactivated');
    }
}
