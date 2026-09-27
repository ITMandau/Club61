<?php

namespace Tests\Feature\Membership;

use App\Models\Membership\FacilityCheckin;
use App\Models\Membership\MembershipPlan;
use App\Models\Membership\MembershipPlanBenefit;
use App\Models\Membership\MembershipUsageLog;
use App\Models\Membership\UserMembership;
use App\Models\Membership\UserMembershipBalance;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\Order;
use App\Models\User;
use App\Models\Wellness\WellnessFacility;
use App\Models\Wellness\WellnessSlot;
use App\Services\Membership\MembershipBalanceService;
use App\Services\Membership\MembershipFulfillmentHandler;
use App\Services\Padel\PadelBookingService;
use App\Services\Wellness\WellnessBookingService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MembershipSystemTest extends TestCase
{
    use RefreshDatabase;

    protected MembershipBalanceService $balanceService;
    protected User $user;
    protected MembershipPlan $planSilver;
    protected MembershipPlan $planBronze;

    protected function setUp(): void
    {
        parent::setUp();

        $this->balanceService = app(MembershipBalanceService::class);

        \App\Services\Permission\Club61PermissionMatrix::syncAllPermissions('web');

        $this->user = User::factory()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'phone' => '081234567890',
        ]);

        // 1. Setup Paket Silver (PADEL 10 Jam, GYM Unlimited, SAUNA 4 Kunjungan)
        $this->planSilver = MembershipPlan::create([
            'code' => 'MBR-SILVER',
            'name' => 'Silver Padel Addict',
            'ownership_type' => 'INDIVIDUAL',
            'duration_days' => 30,
            'price' => 3500000.00,
            'is_active' => true,
        ]);
        MembershipPlanBenefit::create([
            'plan_id' => $this->planSilver->id,
            'facility' => 'PADEL',
            'quota_type' => 'HOURS',
            'quota_value' => 10,
            'discount_percent' => 20,
            'booking_priority_days' => 7,
        ]);
        MembershipPlanBenefit::create([
            'plan_id' => $this->planSilver->id,
            'facility' => 'GYM',
            'quota_type' => 'VISITS',
            'quota_value' => null, // unlimited
            'discount_percent' => 0,
        ]);
        MembershipPlanBenefit::create([
            'plan_id' => $this->planSilver->id,
            'facility' => 'SAUNA',
            'quota_type' => 'VISITS',
            'quota_value' => 4,
            'discount_percent' => 20,
        ]);

        // 2. Setup Paket Bronze (Diskon flat Padel 10%, GYM 12 Visit, SAUNA 4 Visit)
        $this->planBronze = MembershipPlan::create([
            'code' => 'MBR-BRONZE',
            'name' => 'Bronze Active',
            'ownership_type' => 'INDIVIDUAL',
            'duration_days' => 30,
            'price' => 1500000.00,
            'is_active' => true,
        ]);
        MembershipPlanBenefit::create([
            'plan_id' => $this->planBronze->id,
            'facility' => 'PADEL',
            'quota_type' => 'NONE',
            'quota_value' => null,
            'discount_percent' => 10,
        ]);
        MembershipPlanBenefit::create([
            'plan_id' => $this->planBronze->id,
            'facility' => 'GYM',
            'quota_type' => 'VISITS',
            'quota_value' => 12,
            'discount_percent' => 0,
        ]);
        MembershipPlanBenefit::create([
            'plan_id' => $this->planBronze->id,
            'facility' => 'SAUNA',
            'quota_type' => 'VISITS',
            'quota_value' => 4,
            'discount_percent' => 10,
        ]);
    }

    /**
     * Skenario 1: Sequence kode membership atomik dan berformat MBR-YYYY-NNNNN.
     */
    public function test_membership_code_sequence_is_atomic_and_formatted_correctly(): void
    {
        $code1 = $this->balanceService->generateMembershipCode();
        $code2 = $this->balanceService->generateMembershipCode();

        $year = date('Y');
        $this->assertEquals("MBR-{$year}-00001", $code1);
        $this->assertEquals("MBR-{$year}-00002", $code2);
    }

    /**
     * Skenario 2: Snapshot immutability saat purchasePlan() dan remaining_quota = 0.00.
     */
    public function test_purchase_plan_snapshots_benefits_with_zero_initial_remaining_quota(): void
    {
        $membership = $this->balanceService->purchasePlan($this->user, $this->planSilver);

        $this->assertEquals('PENDING_PAYMENT', $membership->status);
        $this->assertNull($membership->start_date);
        $this->assertNull($membership->end_date);
        $this->assertNotEmpty($membership->qr_pass_hash);

        $padelBal = $membership->balanceFor('PADEL');
        $this->assertNotNull($padelBal);
        $this->assertEquals(10.00, (float) $padelBal->initial_quota);
        $this->assertEquals(0.00, (float) $padelBal->remaining_quota); // Mulai dari nol!
    }

    /**
     * Skenario 3: Aktivasi mengisi kuota awal via TOPUP tanpa risiko kuota ganda.
     */
    public function test_activation_sets_dates_and_tops_up_quota_without_double_counting(): void
    {
        $membership = $this->balanceService->purchasePlan($this->user, $this->planSilver);
        $activeMembership = $this->balanceService->activateMembership($membership);

        $this->assertEquals('ACTIVE', $activeMembership->status);
        $this->assertEquals(Carbon::today()->toDateString(), $activeMembership->start_date->toDateString());
        $this->assertEquals(Carbon::today()->addDays(30)->toDateString(), $activeMembership->end_date->toDateString());

        $padelBal = $activeMembership->balanceFor('PADEL');
        $this->assertEquals(10.00, (float) $padelBal->remaining_quota);

        // Audit log TOPUP tercatat
        $this->assertDatabaseHas('membership_usage_logs', [
            'balance_id' => $padelBal->id,
            'change_type' => 'TOPUP',
            'quantity' => 10.00,
        ]);
    }

    /**
     * Skenario 4: adjustQuota mencegah overdraft (saldo minus) saat DECREMENT.
     */
    public function test_adjust_quota_prevents_overdraft_on_decrement(): void
    {
        $membership = $this->balanceService->purchasePlan($this->user, $this->planSilver);
        $this->balanceService->activateMembership($membership);

        $padelBal = $membership->balanceFor('PADEL');

        $this->expectException(DomainException::class);
        // Coba kurangi 15 jam padahal saldo cuma 10 jam
        $this->balanceService->adjustQuota($padelBal->id, 'DECREMENT', 15.00);
    }

    /**
     * Skenario 5: adjustQuota mencatat audit log immutable tanpa updated_at.
     */
    public function test_adjust_quota_creates_immutable_audit_log_without_updated_at(): void
    {
        $membership = $this->balanceService->purchasePlan($this->user, $this->planSilver);
        $this->balanceService->activateMembership($membership);

        $padelBal = $membership->balanceFor('PADEL');
        $this->balanceService->adjustQuota(
            balanceId: $padelBal->id,
            changeType: 'DECREMENT',
            quantity: 2.00,
            notes: 'Main padel 2 jam'
        );

        $padelBal->refresh();
        $this->assertEquals(8.00, (float) $padelBal->remaining_quota);

        $log = MembershipUsageLog::where('balance_id', $padelBal->id)
            ->where('change_type', 'DECREMENT')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals(2.00, (float) $log->quantity);
        $this->assertNotNull($log->created_at);
        $this->assertFalse(array_key_exists('updated_at', $log->getAttributes()));
    }

    /**
     * Skenario 6: Idempotency guard di MembershipFulfillmentHandler.
     */
    public function test_membership_fulfillment_handler_is_idempotent(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-MBR-TEST1',
            'user_id' => $this->user->id,
            'order_type' => 'MEMBERSHIP',
            'subtotal' => 3500000,
            'grand_total' => 3500000,
            'payment_status' => 'PAID',
        ]);

        $membership = $this->balanceService->purchasePlan($this->user, $this->planSilver, [
            'order_id' => $order->id,
            'status' => 'PENDING_PAYMENT',
        ]);

        $handler = app(MembershipFulfillmentHandler::class);

        // Eksekusi webhook pertama
        $handler->fulfill($order, collect());
        $membership->refresh();
        $this->assertEquals('ACTIVE', $membership->status);
        $this->assertEquals(10.00, (float) $membership->balanceFor('PADEL')->remaining_quota);

        // Eksekusi webhook kedua (duplikat Midtrans retry)
        $handler->fulfill($order, collect());
        $membership->refresh();
        // Saldo kuota tetap 10 jam, TIDAK bertambah jadi 20 jam!
        $this->assertEquals(10.00, (float) $membership->balanceFor('PADEL')->remaining_quota);
    }

    /**
     * Skenario 7: Renewal memperpanjang masa aktif parent dari max(today, end_date) dan akumulasi kuota.
     */
    public function test_renewal_extends_parent_end_date_and_accumulates_quota(): void
    {
        // Kartu lama aktif sisa 5 hari ke depan, sisa kuota 3 jam
        $parent = $this->balanceService->purchasePlan($this->user, $this->planSilver);
        $this->balanceService->activateMembership($parent);
        $parent->update(['end_date' => Carbon::today()->addDays(5)->toDateString()]);

        $parentPadel = $parent->balanceFor('PADEL');
        $parentPadel->update(['remaining_quota' => 3.00]);

        // Buat renewal paket Silver (+30 hari, +10 jam)
        $renewal = $this->balanceService->renewMembership($parent, $this->planSilver);
        $this->assertEquals($parent->id, $renewal->renewal_of_id);

        // Fulfill renewal
        $this->balanceService->fulfillRenewal($renewal);

        $parent->refresh();
        $parentPadel->refresh();

        // End date bertambah 30 hari dari H+5 = H+35
        $expectedEndDate = Carbon::today()->addDays(35)->toDateString();
        $this->assertEquals($expectedEndDate, $parent->end_date->toDateString());

        // Kuota terakumulasi: 3 + 10 = 13 jam!
        $this->assertEquals(13.00, (float) $parentPadel->remaining_quota);

        // Kartu renewal berstatus MERGED
        $renewal->refresh();
        $this->assertEquals('MERGED', $renewal->status);
    }

    /**
     * Skenario 8: Upgrade kartu lama menguras kuota via ROLLOVER_OUT dan mengisi kartu baru via ROLLOVER_IN.
     */
    public function test_upgrade_drains_old_card_with_rollover_out_and_deposits_rollover_in(): void
    {
        $oldMembership = $this->balanceService->purchasePlan($this->user, $this->planSilver);
        $this->balanceService->activateMembership($oldMembership);
        $oldPadel = $oldMembership->balanceFor('PADEL');
        $oldPadel->update(['remaining_quota' => 4.00]); // sisa 4 jam di kartu lama

        // Buat paket Gold (25 jam padel)
        $planGold = MembershipPlan::create([
            'code' => 'MBR-GOLD',
            'name' => 'Gold Ultimate',
            'ownership_type' => 'INDIVIDUAL',
            'duration_days' => 30,
            'price' => 6000000.00,
            'is_active' => true,
        ]);
        MembershipPlanBenefit::create([
            'plan_id' => $planGold->id,
            'facility' => 'PADEL',
            'quota_type' => 'HOURS',
            'quota_value' => 25,
            'discount_percent' => 30,
        ]);

        $newMembership = $this->balanceService->upgradeMembership($oldMembership, $planGold, [
            'activate_now' => true,
        ]);

        $oldMembership->refresh();
        $oldPadel->refresh();
        $newPadel = $newMembership->balanceFor('PADEL');

        // Status kartu lama UPGRADED, saldo 0.00
        $this->assertEquals('UPGRADED', $oldMembership->status);
        $this->assertEquals(0.00, (float) $oldPadel->remaining_quota);

        // Saldo kartu baru: 25 (topup awal paket gold) + 4 (rollover in) = 29 jam!
        $this->assertEquals(29.00, (float) $newPadel->remaining_quota);

        // Pastikan audit log mencatat ROLLOVER_OUT pada kartu lama dan ROLLOVER_IN pada kartu baru
        $this->assertDatabaseHas('membership_usage_logs', [
            'balance_id' => $oldPadel->id,
            'change_type' => 'ROLLOVER_OUT',
            'quantity' => 4.00,
        ]);
        $this->assertDatabaseHas('membership_usage_logs', [
            'balance_id' => $newPadel->id,
            'change_type' => 'ROLLOVER_IN',
            'quantity' => 4.00,
        ]);
    }

    /**
     * Skenario 9: Command auto-expiry TIDAK meng-expire kartu diskon-only/unlimited di hari pertama.
     */
    public function test_sync_expired_memberships_command_does_not_expire_unlimited_or_discount_only_plans(): void
    {
        // Beli paket diskon-only / unlimited
        $planDiscountOnly = MembershipPlan::create([
            'code' => 'MBR-DISC-ONLY',
            'name' => 'Flat Discount Club',
            'ownership_type' => 'INDIVIDUAL',
            'duration_days' => 30,
            'price' => 1000000,
            'is_active' => true,
        ]);
        MembershipPlanBenefit::create([
            'plan_id' => $planDiscountOnly->id,
            'facility' => 'PADEL',
            'quota_type' => 'NONE',
            'quota_value' => null,
            'discount_percent' => 15,
        ]);

        $membership = $this->balanceService->purchasePlan($this->user, $planDiscountOnly);
        $this->balanceService->activateMembership($membership);

        // Jalankan command sync-expired
        $this->artisan('membership:sync-expired')->assertSuccessful();

        $membership->refresh();
        // Kartu TETAP ACTIVE karena end_date masih 30 hari ke depan dan tidak punya balance bertipe HOURS/VISITS
        $this->assertEquals('ACTIVE', $membership->status);
    }

    /**
     * Skenario 10: Command auto-expiry meng-expire kartu jika seluruh balance HOURS/VISITS habis.
     */
    public function test_sync_expired_memberships_command_expires_when_all_hours_or_visits_depleted(): void
    {
        $membership = $this->balanceService->purchasePlan($this->user, $this->planSilver);
        $membership = $this->balanceService->activateMembership($membership);

        // Habiskan kuota Padel & Sauna langsung di DB
        UserMembershipBalance::where('user_membership_id', $membership->id)->update(['remaining_quota' => 0.00]);

        $this->artisan('membership:sync-expired')->assertSuccessful();

        $membership->refresh();
        $this->assertEquals('EXPIRED', $membership->status);
    }

    /**
     * Skenario 11: Padel checkout menggunakan kuota membership jam bermain dan memotong court_fee.
     */
    public function test_padel_checkout_applies_membership_quota_and_deducts_hours(): void
    {
        $court = PadelCourt::create([
            'name' => 'Court 1 Centre Panoramic',
            'type' => 'INDOOR',
            'hourly_rate_regular' => 300000.00,
            'hourly_rate_prime' => 350000.00,
            'is_active' => true,
        ]);

        $booking = PadelBooking::create([
            'booking_code' => 'BK-' . strtoupper(Str::random(6)),
            'user_id' => $this->user->id,
            'court_id' => $court->id,
            'booking_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => Carbon::tomorrow()->setTime(10, 0),
            'end_time' => Carbon::tomorrow()->setTime(11, 0), // 1 jam
            'court_fee' => 300000.00,
            'coach_fee' => 0,
            'equipment_fee' => 0,
            'total_amount' => 300000.00,
            'status' => 'LOCKED',
        ]);

        // Berikan member paket Silver (10 jam padel)
        $membership = $this->balanceService->purchasePlan($this->user, $this->planSilver);
        $this->balanceService->activateMembership($membership);
        $padelBal = $membership->balanceFor('PADEL');

        $padelService = app(PadelBookingService::class);
        $checkoutResult = $padelService->checkout(
            bookingIds: [$booking->id],
            equipments: [],
            voucherCode: null,
            paymentMethod: 'QRIS',
            idempotencyKey: Str::uuid()->toString(),
            user: $this->user,
            membershipBalanceId: $padelBal->id
        );

        $this->assertTrue($checkoutResult['success']);

        $booking->refresh();
        $padelBal->refresh();

        // Biaya court_fee menjadi 0 karena ditanggung kuota jam
        $this->assertEquals(0.00, (float) $booking->court_fee);
        $this->assertEquals(1.00, (float) $booking->member_hours_consumed);
        $this->assertEquals(300000.00, (float) $booking->member_discount_court);
        // Kuota berkurang 1 jam (10 - 1 = 9 jam)
        $this->assertEquals(9.00, (float) $padelBal->remaining_quota);
    }

    /**
     * Skenario 12: Pembatalan booking padel mereversal jam kuota member.
     */
    public function test_padel_booking_cancellation_reverses_membership_hours(): void
    {
        $court = PadelCourt::create([
            'name' => 'Court 2 Panoramic',
            'type' => 'INDOOR',
            'hourly_rate_regular' => 300000.00,
            'hourly_rate_prime' => 350000.00,
            'is_active' => true,
        ]);

        $membership = $this->balanceService->purchasePlan($this->user, $this->planSilver);
        $this->balanceService->activateMembership($membership);
        $padelBal = $membership->balanceFor('PADEL');

        $booking = PadelBooking::create([
            'booking_code' => 'BK-' . strtoupper(Str::random(6)),
            'user_id' => $this->user->id,
            'court_id' => $court->id,
            'booking_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => Carbon::tomorrow()->setTime(14, 0),
            'end_time' => Carbon::tomorrow()->setTime(15, 0),
            'court_fee' => 0.00,
            'total_amount' => 0.00,
            'status' => 'PAID',
            'membership_balance_id' => $padelBal->id,
            'member_hours_consumed' => 1.00,
        ]);

        // Simulasikan saldo terpotong 1 jam
        $padelBal->update(['remaining_quota' => 9.00]);

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

        $padelBal->refresh();
        // Jam kuota berhasil direversal kembali menjadi 10 jam!
        $this->assertEquals(10.00, (float) $padelBal->remaining_quota);
    }

    /**
     * Skenario 13: Booking wellness menggunakan 1 visit untuk 1 orang, kelebihan orang bayar normal.
     */
    public function test_wellness_booking_uses_visit_quota_for_one_person_and_charges_extra_persons(): void
    {
        $facility = WellnessFacility::create([
            'name' => 'Finnish Cedar Sauna',
            'max_capacity_per_slot' => 8,
            'duration_minutes' => 45,
            'price_per_person' => 150000.00,
        ]);

        $slot = WellnessSlot::create([
            'facility_id' => $facility->id,
            'session_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => Carbon::tomorrow()->setTime(16, 0),
            'end_time' => Carbon::tomorrow()->setTime(16, 45),
            'max_capacity' => 8,
            'booked_count' => 0,
            'status' => 'AVAILABLE',
        ]);

        $membership = $this->balanceService->purchasePlan($this->user, $this->planSilver);
        $this->balanceService->activateMembership($membership);
        $saunaBal = $membership->balanceFor('SAUNA'); // Sisa 4 sesi

        $wellnessService = app(WellnessBookingService::class);

        // Booking untuk 3 orang (1 orang member + 2 orang teman)
        $booking = $wellnessService->bookSlot(
            user: $this->user,
            slotId: $slot->id,
            numPersons: 3,
            membershipBalanceId: $saunaBal->id
        );

        $saunaBal->refresh();
        // 1 sesi visit terpotong (4 - 1 = 3 sesi)
        $this->assertEquals(3.00, (float) $saunaBal->remaining_quota);

        // Total bayar: 1 orang gratis (Rp 0) + 2 orang teman bayar penuh (2 x Rp 150.000 = Rp 300.000)
        $this->assertEquals(300000.00, (float) $booking->total_amount);
        $this->assertEquals(150000.00, (float) $booking->member_discount_amount);
        $this->assertEquals(1.00, (float) $booking->member_sessions_consumed);
    }

    /**
     * Skenario 14: Check-in Gym mengurangi kuota visit dan memvalidasi jam akses.
     */
    public function test_facility_checkin_gym_decrements_visit_quota_and_validates_time_window(): void
    {
        $membership = $this->balanceService->purchasePlan($this->user, $this->planBronze);
        $this->balanceService->activateMembership($membership);
        $gymBal = $membership->balanceFor('GYM'); // 12 visits

        $checkin = $this->balanceService->recordCheckin($gymBal->id, $this->user->id);

        $this->assertInstanceOf(FacilityCheckin::class, $checkin);
        $gymBal->refresh();
        // Kuota gym berkurang 1 visit (12 - 1 = 11 visits)
        $this->assertEquals(11.00, (float) $gymBal->remaining_quota);
    }

    /**
     * Skenario 15 (Security/IDOR): User lain tidak boleh check-in / menguras kuota gym
     * milik member lain hanya dengan menebak/mengetahui balance_id member tersebut.
     */
    public function test_facility_checkin_gym_rejects_balance_owned_by_another_user(): void
    {
        $membership = $this->balanceService->purchasePlan($this->user, $this->planBronze);
        $this->balanceService->activateMembership($membership);
        $gymBal = $membership->balanceFor('GYM'); // 12 visits, milik $this->user

        $attacker = User::factory()->create([
            'name' => 'Attacker',
            'email' => 'attacker@example.com',
            'phone' => '089999999999',
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Balance ini bukan milik member yang login.');

        try {
            $this->balanceService->recordCheckin($gymBal->id, $attacker->id);
        } finally {
            $gymBal->refresh();
            // Kuota korban tidak boleh berkurang sama sekali
            $this->assertEquals(12.00, (float) $gymBal->remaining_quota);
        }
    }
}
