<?php

namespace Tests\Feature\Padel;

use App\Models\Membership\MembershipPlan;
use App\Models\Membership\UserMembership;
use App\Models\Membership\UserMembershipBalance;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\ClubFinanceSetting;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\Pos\PosCashierShift;
use App\Models\Pos\Refund;
use App\Models\Pos\Voucher;
use App\Models\User;
use App\Services\Padel\PadelBookingService;
use App\Services\Payment\MidtransReconciliationService;
use App\Services\Payment\PaymentOrchestratorService;
use App\Services\Permission\Club61PermissionMatrix;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Regresi audit "bom waktu" 1 Okt 2026: webhook expire membatalkan booking lunas, tagihan selisih tertukar antar
 * booking satu order, refund melebihi uang masuk, uang telat untuk tagihan yang sudah ditutup, input jam reschedule
 * direkayasa, benefit member/sponsor yang sudah habis ikut pindah, dan booking berurutan yang bisa dipecah.
 */
class PaymentTimeBombRegressionTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'SB-Mid-server-TIMEBOMB';

    protected PadelBookingService $service;

    protected User $admin;

    protected User $customer;

    protected PadelCourt $court;

    protected string $date;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');

        ClubFinanceSetting::getSettings()->update([
            'is_admin_fee_enabled' => false, 'is_tax_enabled' => false, 'admin_fee_channels' => 'ALL', 'tax_channels' => 'ALL',
        ]);
        Cache::forget(ClubFinanceSetting::CACHE_KEY);

        $this->service = app(PadelBookingService::class);
        $this->admin = User::factory()->superAdmin()->create(['name' => 'Owner']);
        $this->customer = User::factory()->customer()->create(['name' => 'Budi']);
        $this->court = PadelCourt::create([
            'name' => 'Court TB', 'type' => 'INDOOR', 'hourly_rate_regular' => 200000, 'hourly_rate_prime' => 300000, 'is_active' => true,
        ]);
        $this->date = now()->next(Carbon::WEDNESDAY)->format('Y-m-d');
        config(['services.midtrans.server_key' => self::KEY]);
    }

    /** Order lunas berisi satu atau beberapa booking (tiap booking 200rb, jam reguler). */
    private function paidOrder(array $starts, string $code = 'TB1', ?string $voucher = null): array
    {
        $total = 200000 * count($starts);
        $order = Order::create([
            'order_number' => 'ORD-'.$code, 'user_id' => $this->customer->id, 'order_type' => 'ONLINE_BOOKING',
            'subtotal' => $total, 'grand_total' => $total, 'payment_status' => 'PAID', 'voucher_code' => $voucher,
        ]);
        Payment::create([
            'order_id' => $order->id, 'payment_gateway' => 'MIDTRANS', 'transaction_id' => 'ORD-'.$code,
            'amount' => $total, 'payment_method' => 'BCA_VA', 'status' => 'SUCCESS',
        ]);

        $bookings = [];
        foreach ($starts as $i => $start) {
            $startAt = Carbon::parse("{$this->date} {$start}");
            $bookings[] = PadelBooking::create([
                'booking_code' => "BK-{$code}-{$i}", 'order_id' => $order->id, 'user_id' => $this->customer->id,
                'court_id' => $this->court->id, 'booking_date' => $this->date, 'start_time' => $startAt,
                'end_time' => $startAt->copy()->addHour(), 'court_fee' => 200000, 'total_amount' => 200000,
                'status' => 'PAID', 'qr_code_hash' => "QR-{$code}-{$i}",
            ]);
        }

        return [$order, $bookings];
    }

    private function reschedule(PadelBooking $booking, string $start, string $channel = 'CASHIER', ?string $date = null): array
    {
        return $this->service->adminRescheduleBooking(
            bookingId: $booking->id, newCourtId: $this->court->id, newDate: $date ?? $this->date,
            newStartTimeStr: $start, reason: 'Permintaan customer', adminUser: $this->admin, deltaPaymentChannel: $channel,
        );
    }

    private function openShift(): void
    {
        PosCashierShift::create([
            'shift_number' => 'SFT-TB-'.Str::random(4), 'counter' => 'PADEL_FRONTDESK', 'status' => 'OPEN',
            'opened_by_id' => $this->admin->id, 'opened_at' => now(), 'starting_cash' => 0, 'expected_cash' => 0,
        ]);
    }

    private function webhook(string $orderId, string $transactionStatus, string $gross, string $statusCode): void
    {
        $this->postJson('/api/v1/padel/webhook/midtrans', [
            'order_id' => $orderId, 'status_code' => $statusCode, 'gross_amount' => $gross,
            'signature_key' => hash('sha512', $orderId.$statusCode.$gross.self::KEY),
            'transaction_status' => $transactionStatus, 'payment_type' => 'qris',
        ])->assertOk();
    }

    /** Customer membuka link bayar selisih di invoice (sesi Snap Midtrans). */
    private function openOnlineDeltaSession(PadelBooking $booking): string
    {
        return $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/padel/bookings/{$booking->id}/retry-payment", ['payment_method' => 'QRIS'])
            ->assertOk()
            ->json('suffixed_order_id');
    }

    public function test_expired_delta_session_does_not_cancel_the_paid_booking(): void
    {
        [$order, [$booking]] = $this->paidOrder(['10:00']);
        $this->reschedule($booking, '18:00', 'ONLINE');
        $sessionId = $this->openOnlineDeltaSession($booking);

        $this->webhook($sessionId, 'expire', '100000.00', '407');

        $this->assertSame('LOCKED', $booking->fresh()->status, 'booking tetap ditahan, bukan dibatalkan');
        $this->assertNotSame('CANCELLED', $order->fresh()->payment_status);
        $bill = Payment::where('order_id', $order->id)->where('status', 'PENDING')->sole();
        $this->assertSame('CASHIER_POS', $bill->payment_gateway, 'tagihan kembali terbuka untuk dibayar ulang / di kasir');
        $this->assertSame(0, Refund::count());
    }

    public function test_old_session_expiring_after_payment_never_cancels_a_paid_order(): void
    {
        [$order, [$booking]] = $this->paidOrder(['10:00']);
        $initial = Payment::where('order_id', $order->id)->sole();
        $initial->update(['payload_log' => ['midtrans_order_ids' => ['ORD-TB1_111'], 'midtrans_order_id' => 'ORD-TB1_111']]);

        $this->webhook('ORD-TB1', 'expire', '200000.00', '407');
        $this->webhook('ORD-TB1_111', 'cancel', '200000.00', '200');

        $this->assertSame('PAID', $order->fresh()->payment_status);
        $this->assertSame('PAID', $booking->fresh()->status);
        $this->assertSame('SUCCESS', $initial->fresh()->status);
    }

    public function test_deny_notification_is_not_final(): void
    {
        $order = Order::create(['order_number' => 'ORD-DENY', 'user_id' => $this->customer->id, 'order_type' => 'ONLINE_BOOKING', 'subtotal' => 200000, 'grand_total' => 200000, 'payment_status' => 'UNPAID']);
        $startAt = Carbon::parse("{$this->date} 10:00");
        $booking = PadelBooking::create(['booking_code' => 'BK-DENY', 'order_id' => $order->id, 'user_id' => $this->customer->id, 'court_id' => $this->court->id, 'booking_date' => $this->date, 'start_time' => $startAt, 'end_time' => $startAt->copy()->addHour(), 'court_fee' => 200000, 'total_amount' => 200000, 'status' => 'LOCKED']);
        Payment::create(['order_id' => $order->id, 'payment_gateway' => 'MIDTRANS', 'transaction_id' => 'ORD-DENY', 'amount' => 200000, 'payment_method' => 'CREDIT_CARD', 'status' => 'PENDING']);

        $this->webhook('ORD-DENY', 'deny', '200000.00', '202');

        $this->assertSame('LOCKED', $booking->fresh()->status);
        $this->assertSame('UNPAID', $order->fresh()->payment_status);
        $this->assertSame('PENDING', Payment::where('order_id', $order->id)->value('status'));
    }

    public function test_bills_are_bound_to_their_own_booking_in_a_multi_booking_order(): void
    {
        [$order, [$a, $b]] = $this->paidOrder(['10:00', '13:00']);
        $this->reschedule($a, '18:00');   // selisih 100rb
        $this->reschedule($b, '20:00');   // selisih 100rb
        $this->openShift();
        $this->actingAs($this->admin);

        $this->service->adminSettleCashierPayment($a->id, 'QRIS', 100000, $this->admin, ['qris_rrn' => 'RRNBOOKA01']);

        $this->assertSame('PAID', $a->fresh()->status);
        $this->assertSame('LOCKED', $b->fresh()->status, 'melunasi tagihan A tidak boleh membuka booking B');
        $this->assertSame('PARTIALLY_PAID', $order->fresh()->payment_status);
        $openBill = Payment::where('order_id', $order->id)->where('status', 'PENDING')->sole();
        $this->assertSame($b->id, $openBill->payload_log['booking_id']);

        $this->service->adminSettleCashierPayment($b->id, 'QRIS', 100000, $this->admin, ['qris_rrn' => 'RRNBOOKB01']);
        $this->assertSame('PAID', $b->fresh()->status);
        $this->assertSame('PAID', $order->fresh()->payment_status);
    }

    public function test_cashier_cannot_settle_a_bill_that_was_just_paid(): void
    {
        [$order, [$booking]] = $this->paidOrder(['10:00']);
        $this->reschedule($booking, '18:00');
        $this->openShift();
        $bill = Payment::where('order_id', $order->id)->where('status', 'PENDING')->sole();
        $bill->update(['status' => 'SUCCESS']); // mis. webhook Midtrans commit duluan

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('SUDAH LUNAS');
        app(PaymentOrchestratorService::class)->markOrderAsPaid($order, [
            'payment_gateway' => 'CASHIER_POS', 'counter' => 'PADEL_FRONTDESK', 'payment_method' => 'QRIS',
            'amount' => 100000, 'transaction_id' => $bill->transaction_id, 'require_pending_payment' => true,
        ]);
    }

    public function test_unpaid_cart_cannot_be_refunded_and_no_fake_payment_is_created(): void
    {
        $order = Order::create(['order_number' => 'ORD-CART', 'user_id' => $this->customer->id, 'order_type' => 'ONLINE_BOOKING', 'subtotal' => 200000, 'grand_total' => 206000, 'payment_status' => 'UNPAID']);
        $startAt = Carbon::parse("{$this->date} 10:00");
        $booking = PadelBooking::create(['booking_code' => 'BK-CART', 'order_id' => $order->id, 'user_id' => $this->customer->id, 'court_id' => $this->court->id, 'booking_date' => $this->date, 'start_time' => $startAt, 'end_time' => $startAt->copy()->addHour(), 'court_fee' => 200000, 'total_amount' => 200000, 'status' => 'LOCKED']);
        Payment::create(['order_id' => $order->id, 'payment_gateway' => 'MIDTRANS', 'transaction_id' => 'ORD-CART', 'amount' => 206000, 'payment_method' => 'QRIS', 'status' => 'PENDING']);

        // Belum ada uang masuk → dibatalkan tanpa pengajuan refund (dan tanpa pembayaran palsu).
        $result = $this->service->requestCancelAndRefund($booking->id, 'SALAH_BAYAR', 'tes', $this->admin);

        $this->assertSame(0.0, $result['refund_amount']);
        $this->assertSame(0, Payment::where('order_id', $order->id)->where('status', 'SUCCESS')->count());
        $this->assertSame(0, Refund::count());
        $this->assertSame('CANCELLED', $booking->fresh()->status);
        $this->assertSame('FAILED', Payment::where('order_id', $order->id)->sole()->status, 'tagihan keranjang ikut ditutup');
    }

    public function test_refund_cap_is_per_booking_not_per_order(): void
    {
        [, [$a, $b]] = $this->paidOrder(['10:00', '13:00']);

        // Membatalkan 1 dari 2 lapangan hanya mengajukan bagian lapangan itu, bukan seluruh order.
        $result = $this->service->requestCancelAndRefund($a->id, 'SALAH_BAYAR', 'tes', $this->admin);
        $this->assertEqualsWithDelta(200000, $result['refund_amount'], 0.01);
        $this->assertSame('REFUND_PENDING', $a->fresh()->status);
        $this->assertSame('PAID', $b->fresh()->status);
        $this->assertEqualsWithDelta(200000, $this->service->refundableAmountForBooking($b->fresh('order')), 0.01);

        // Lapangan kedua mendapat sisa uangnya, bukan dipotong lagi oleh pengajuan pertama.
        $second = $this->service->requestCancelAndRefund($b->id, 'SALAH_BAYAR', 'tes', $this->admin);
        $this->assertEqualsWithDelta(200000, $second['refund_amount'], 0.01);
    }

    public function test_cancelled_booking_closes_its_bill_and_a_late_online_payment_becomes_a_refund(): void
    {
        [$order, [$booking]] = $this->paidOrder(['10:00']);
        $this->reschedule($booking, '18:00', 'ONLINE');
        $sessionId = $this->openOnlineDeltaSession($booking);
        $this->assertEquals(300000, (float) $order->fresh()->grand_total);

        $this->service->requestCancelAndRefund($booking->id, 'PERMINTAAN_CUSTOMER', 'batal', $this->admin);
        $requested = Refund::where('padel_booking_id', $booking->id)->sole();
        $this->assertEquals(200000, (float) $requested->refund_amount, 'yang diajukan hanya uang yang sudah masuk');

        $bill = Payment::where('order_id', $order->id)->where('payload_log->type', 'RESCHEDULE_PRICE_DELTA')->sole();
        $this->assertSame('FAILED', $bill->status);
        $this->assertEquals(200000, (float) $order->fresh()->grand_total, 'tagihan yang ditutup keluar dari total order');

        // Customer tetap membayar sesi Snap yang masih terbuka.
        $this->webhook($sessionId, 'settlement', '100000.00', '200');

        $this->assertSame('REFUND_PENDING', $booking->fresh()->status);
        $refund = Refund::where('order_id', $order->id)->whereNull('padel_booking_id')->sole();
        $this->assertSame('PENDING', $refund->status);
        $this->assertEquals(100000, (float) $refund->refund_amount);
    }

    public function test_voucher_quota_is_only_consumed_by_the_first_payment(): void
    {
        Voucher::create(['code' => 'PROMO1', 'discount_type' => 'FIXED', 'discount_value' => 10000, 'quota' => 10, 'used_count' => 1, 'valid_until' => now()->addMonth(), 'is_active' => true]);
        [, [$booking]] = $this->paidOrder(['10:00'], 'TBV', 'PROMO1');
        $this->reschedule($booking, '18:00');
        $this->openShift();

        $this->service->adminSettleCashierPayment($booking->id, 'QRIS', 100000, $this->admin, ['qris_rrn' => 'RRNVOUCHER1']);

        $voucher = Voucher::where('code', 'PROMO1')->first();
        $this->assertSame(10, (int) $voucher->quota);
        $this->assertSame(1, (int) $voucher->used_count);
    }

    public function test_tampered_reschedule_start_time_is_rejected(): void
    {
        [, [$booking]] = $this->paidOrder(['10:00']);

        foreach (['10:00 +1 day', '14:30', '25:00'] as $bad) {
            try {
                $this->reschedule($booking, $bad);
                $this->fail("Jam '{$bad}' harus ditolak");
            } catch (HttpException $e) {
                $this->assertSame(422, $e->getStatusCode());
            }
        }
        $this->assertSame('10:00', $booking->fresh()->start_time->format('H:i'));
    }

    public function test_inactive_target_court_is_rejected(): void
    {
        [, [$booking]] = $this->paidOrder(['10:00']);
        $this->court->update(['is_active' => false]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('tidak aktif');
        $this->reschedule($booking, '12:00');
    }

    private function quotaBooking(string $membershipEnd, ?array $window = null): array
    {
        $plan = MembershipPlan::create(['code' => 'MBR-TB', 'name' => 'Gold TB', 'ownership_type' => 'INDIVIDUAL', 'duration_days' => 30, 'price' => 1000000, 'is_active' => true]);
        $membership = UserMembership::create([
            'membership_code' => 'MBR-TB-1', 'owner_type' => 'INDIVIDUAL', 'user_id' => $this->customer->id, 'plan_id' => $plan->id,
            'status' => 'ACTIVE', 'start_date' => now()->subDays(10), 'end_date' => $membershipEnd, 'qr_pass_hash' => 'QR-MBR-TB',
        ]);
        $balance = UserMembershipBalance::create([
            'user_membership_id' => $membership->id, 'facility' => 'PADEL', 'quota_type' => 'HOURS',
            'initial_quota' => 10, 'remaining_quota' => 9,
            'time_window_start' => $window[0] ?? null, 'time_window_end' => $window[1] ?? null,
        ]);
        [$order, [$booking]] = $this->paidOrder(['10:00'], 'TBM');
        $booking->update(['court_fee' => 0, 'member_discount_court' => 200000, 'membership_balance_id' => $balance->id, 'member_hours_consumed' => 1, 'total_amount' => 0]);
        Payment::where('order_id', $order->id)->update(['amount' => 0]);
        $order->update(['subtotal' => 0, 'grand_total' => 0]);

        return [$booking->fresh(), $balance];
    }

    public function test_benefit_is_kept_when_rescheduled_within_membership_validity(): void
    {
        [$booking, $balance] = $this->quotaBooking(Carbon::parse($this->date)->addDays(30)->toDateString());

        $result = $this->reschedule($booking, '12:00', 'CASHIER', Carbon::parse($this->date)->addDays(7)->toDateString());

        $this->assertEquals(0, $result['total_charge']);
        $this->assertNull($result['benefit_dropped_reason']);
        $this->assertEquals(9, (float) $balance->fresh()->remaining_quota);
    }

    public function test_benefit_drops_to_normal_price_after_membership_expiry_and_quota_is_returned(): void
    {
        [$booking, $balance] = $this->quotaBooking($this->date);
        $target = Carbon::parse($this->date)->addDays(7)->toDateString();

        $slots = $this->service->getAvailableRescheduleSlots($booking->id, $this->court->id, $target);
        $slot = collect($slots['slots'])->firstWhere('start_time', '12:00');
        $this->assertNotNull($slot['benefit_dropped_reason'], 'admin melihat peringatan benefit gugur');
        $this->assertEquals(200000, $slot['total_delta']);

        $result = $this->reschedule($booking, '12:00', 'CASHIER', $target);

        $this->assertEquals(200000, $result['total_charge']);
        $this->assertStringContainsString('berakhir', $result['benefit_dropped_reason']);
        $this->assertEquals(10, (float) $balance->fresh()->remaining_quota, 'jam kuota dikembalikan karena tidak dipakai lagi');
        $fresh = $booking->fresh();
        $this->assertEquals(0, (float) $fresh->member_hours_consumed);
        $this->assertEquals(0, (float) $fresh->member_discount_court);
        $this->assertSame('LOCKED', $fresh->status);
    }

    public function test_off_peak_window_is_enforced_for_a_slot_ending_at_midnight(): void
    {
        [$booking] = $this->quotaBooking(Carbon::parse($this->date)->addDays(30)->toDateString(), ['06:00:00', '17:00:00']);
        $start = Carbon::parse("{$this->date} 23:00");

        $quote = $this->service->quoteReschedule($booking, $this->court, $start, $start->copy()->addHour());

        $this->assertTrue($quote['member_benefit_dropped']);
        $this->assertEquals(300000, $quote['total_charge']);
    }

    public function test_consecutive_hours_are_held_as_one_booking_and_legacy_split_bookings_cannot_be_split(): void
    {
        $hold = $this->service->holdBatchSlots([
            ['court_id' => $this->court->id, 'start_time' => '10:00', 'end_time' => '11:00'],
            ['court_id' => $this->court->id, 'start_time' => '11:00', 'end_time' => '12:00'],
        ], $this->date, $this->customer);
        $this->assertCount(1, $hold['bookings']);
        $this->assertSame('12:00', $hold['bookings'][0]->end_time->format('H:i'));

        [, [$first]] = $this->paidOrder(['14:00', '15:00'], 'SPLIT'); // data lama: 2 baris jam berurutan
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('tidak bisa dipecah');
        $this->reschedule($first, '18:00');
    }

    public function test_complete_requires_checked_in(): void
    {
        [, [$booking]] = $this->paidOrder(['10:00']);
        $this->reschedule($booking, '18:00');

        $this->expectException(HttpException::class);
        $this->service->completeBooking($booking->id, $this->admin);
    }

    public function test_cek_midtrans_does_not_report_paid_for_an_unpaid_cashier_bill(): void
    {
        [$order, [$booking]] = $this->paidOrder(['10:00']);
        $this->reschedule($booking, '18:00');

        $result = app(MidtransReconciliationService::class)->reconcileOrder($order->fresh());

        $this->assertSame(MidtransReconciliationService::NOT_PAID, $result);
        $this->assertSame('LOCKED', $booking->fresh()->status);
    }

    public function test_walk_in_payment_proof_cannot_be_reused(): void
    {
        $this->openShift();
        $this->actingAs($this->admin);
        $walkIn = fn (string $start, string $rrn) => $this->service->processWalkInCheckout(
            customer: $this->customer,
            slots: [['court_id' => $this->court->id, 'start_time' => $start, 'end_time' => Carbon::parse($start)->addHour()->format('H:i')]],
            bookingDate: $this->date, equipments: [], paymentMethod: 'QRIS', cashier: $this->admin,
            paymentMeta: ['qris_provider' => 'BCA_QRIS', 'qris_rrn' => $rrn],
        );

        $walkIn('08:00', 'rrnwalk001');
        $this->assertSame('RRNWALK001', Payment::where('status', 'SUCCESS')->latest()->first()->payload_log['qris_details']['rrn']);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('sudah pernah dipakai');
        $walkIn('09:00', 'RRNWALK001');
    }
}
