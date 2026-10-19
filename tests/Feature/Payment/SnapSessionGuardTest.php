<?php

namespace Tests\Feature\Payment;

use App\Models\Membership\MembershipPlan;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\Pos\Refund;
use App\Models\User;
use App\Services\Payment\MidtransService;
use App\Services\Payment\PaymentManager;
use App\Services\Payment\SnapSessionGuard;
use App\Services\Permission\Club61PermissionMatrix;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Satu tagihan = satu sesi Snap yang bisa dibayar. Kasus nyata di sandbox: Pay Now ditekan berulang membuka 5 sesi
 * QRIS untuk satu booking, lalu QRIS dan VA sama-sama dibayar (Rp600.000 ketarik dua kali).
 */
class SnapSessionGuardTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'SB-Mid-server-SESSION-GUARD';

    protected User $customer;

    protected PadelCourt $court;

    /** Status tiap order_id di "Midtrans": pending / settlement / expire / cancel; tidak ada = 404. */
    public array $gateway = [];

    /** Sesi Snap yang dibuat (PaymentManager::createPayment). */
    public array $created = [];

    public bool $midtransDown = false;

    /** Midtrans membalas HTTP 200 tapi status_code 412 dan transaksinya tetap pending. */
    public bool $closeRejected = false;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');
        config(['services.midtrans.server_key' => self::KEY, 'services.midtrans.is_production' => false]);

        $this->customer = User::factory()->customer()->create();
        $this->court = PadelCourt::create(['name' => 'Court Guard', 'type' => 'INDOOR', 'hourly_rate_regular' => 200000, 'hourly_rate_prime' => 200000, 'is_active' => true]);

        $test = $this;
        $this->app->instance(PaymentManager::class, new class($test) extends PaymentManager
        {
            public function __construct(private $test) {}

            public function createPayment(array $params): array
            {
                $this->test->created[] = $params;

                return ['snap_token' => 'SNAP-'.Str::random(8), 'redirect_url' => 'https://snap.test/'.$params['order_id'], 'is_mock' => false];
            }
        });

        Http::fake(function (HttpRequest $request) {
            if ($this->midtransDown) {
                throw new ConnectionException('Midtrans down');
            }
            if (! preg_match('#/v2/([^/]+)/(status|expire|cancel)$#', $request->url(), $m)) {
                return Http::response([], 404);
            }
            $id = rawurldecode($m[1]);

            if ($m[2] !== 'status') {
                if (($this->gateway[$id] ?? null) !== 'pending' || $this->closeRejected) {
                    return Http::response(['status_code' => '412', 'status_message' => 'Merchant cannot modify the status of the transaction'], 200);
                }
                $this->gateway[$id] = $m[2];

                return Http::response(['status_code' => $m[2] === 'expire' ? '407' : '200', 'transaction_status' => $m[2], 'order_id' => $id], 200);
            }

            if (! isset($this->gateway[$id])) {
                return Http::response(['status_code' => '404', 'status_message' => "Transaction doesn't exist."], 404);
            }

            return Http::response([
                'status_code' => $this->gateway[$id] === 'settlement' ? '200' : '201',
                'transaction_status' => $this->gateway[$id],
                'order_id' => $id,
                'gross_amount' => '200000.00',
                'payment_type' => 'qris',
            ], 200);
        });
    }

    /** Booking menunggu bayar + tagihan checkout (sesi pertama = order_number, metode QRIS). */
    private function pendingBooking(string $code = 'G1'): PadelBooking
    {
        $date = now()->next(Carbon::WEDNESDAY)->format('Y-m-d');
        $order = Order::create(['order_number' => "ORD-{$code}", 'user_id' => $this->customer->id, 'order_type' => 'ONLINE_BOOKING', 'subtotal' => 200000, 'grand_total' => 200000, 'payment_status' => 'UNPAID']);
        $start = Carbon::parse("{$date} 10:00");
        Payment::create([
            'order_id' => $order->id, 'payment_gateway' => 'MIDTRANS', 'transaction_id' => "ORD-{$code}", 'amount' => 200000, 'payment_method' => 'QRIS', 'status' => 'PENDING',
            'snap_token' => 'SNAP-FIRST',
            'payload_log' => SnapSessionGuard::record([], "ORD-{$code}", 'QRIS', 200000, 'SNAP-FIRST', 'https://snap.test/first', now()->addMinutes(15)),
        ]);

        return PadelBooking::create(['booking_code' => "BK-{$code}", 'order_id' => $order->id, 'user_id' => $this->customer->id, 'court_id' => $this->court->id, 'booking_date' => $date, 'start_time' => $start, 'end_time' => $start->copy()->addHour(), 'court_fee' => 200000, 'total_amount' => 200000, 'status' => 'PENDING_PAYMENT', 'expires_at' => now()->addMinutes(15)]);
    }

    private function payNow(PadelBooking $booking, string $method): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/padel/bookings/{$booking->id}/retry-payment", ['payment_method' => $method]);
    }

    private function bill(PadelBooking $booking): Payment
    {
        return Payment::where('order_id', $booking->order_id)->firstOrFail();
    }

    private function sessionStatus(PadelBooking $booking, string $sessionId): ?string
    {
        return $this->bill($booking)->payload_log['midtrans_sessions'][$sessionId]['status'] ?? null;
    }

    private function webhook(string $orderId, string $status): \Illuminate\Testing\TestResponse
    {
        $code = $status === 'settlement' ? '200' : '201';

        return $this->postJson('/api/v1/padel/webhook/midtrans', [
            'order_id' => $orderId, 'status_code' => $code, 'gross_amount' => '200000.00', 'transaction_status' => $status, 'payment_type' => 'qris',
            'signature_key' => hash('sha512', $orderId.$code.'200000.00'.self::KEY),
        ]);
    }

    public function test_pay_now_pressed_three_times_with_the_same_method_keeps_one_session(): void
    {
        $booking = $this->pendingBooking();
        $this->gateway['ORD-G1'] = 'pending'; // popup QRIS sudah dibuka

        $tokens = [];
        foreach (range(1, 3) as $_) {
            $tokens[] = $this->payNow($booking, 'QRIS')->assertOk()->assertJsonPath('reused_session', true)->json('snap_token');
        }

        $this->assertSame([], $this->created, 'tidak ada sesi Snap baru');
        $this->assertSame(['SNAP-FIRST', 'SNAP-FIRST', 'SNAP-FIRST'], $tokens);
        $this->assertSame(['ORD-G1'], SnapSessionGuard::sessionsOf($this->bill($booking)));
    }

    public function test_changing_method_expires_the_old_session_before_opening_a_new_one(): void
    {
        $booking = $this->pendingBooking();
        $this->gateway['ORD-G1'] = 'pending';

        $new = $this->payNow($booking, 'BCA_VA')->assertOk()->json('suffixed_order_id');

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v2/ORD-G1/expire'));
        $this->assertSame('expire', $this->gateway['ORD-G1'], 'QRIS lama mati di Midtrans');
        $this->assertCount(1, $this->created);
        $this->assertSame('BCA_VA', $this->created[0]['payment_method']);
        $this->assertSame(SnapSessionGuard::CLOSED, $this->sessionStatus($booking, 'ORD-G1'));
        $this->assertSame(SnapSessionGuard::ACTIVE, $this->sessionStatus($booking, $new));
        $this->assertSame($new, $this->bill($booking)->payload_log['midtrans_order_id']);

        // Ganti lagi ke QRIS: VA tadi ikut ditutup; batas bayar tidak mundur.
        $this->gateway[$new] = 'pending';
        $third = $this->payNow($booking, 'QRIS')->assertOk()->json('suffixed_order_id');
        $this->assertSame('expire', $this->gateway[$new]);
        $this->assertNotSame($new, $third);
        $this->assertLessThanOrEqual(15, $this->created[1]['expiry_minutes']);
    }

    public function test_old_session_already_paid_settles_the_bill_instead_of_opening_a_new_one(): void
    {
        $booking = $this->pendingBooking();
        $this->gateway['ORD-G1'] = 'settlement'; // customer sudah bayar QRIS, webhook belum sampai

        $this->payNow($booking, 'BCA_VA')->assertOk()->assertJsonPath('is_paid', true)->assertJsonPath('snap_token', null);

        $this->assertSame([], $this->created);
        $this->assertSame('PAID', Order::find($booking->order_id)->payment_status);
        $this->assertSame('PAID', $booking->fresh()->status);
        $this->assertSame('SUCCESS', $this->bill($booking)->status);
    }

    public function test_no_new_session_when_midtrans_cannot_be_reached_or_refuses_to_close(): void
    {
        $booking = $this->pendingBooking();
        $this->gateway['ORD-G1'] = 'pending';

        $this->midtransDown = true;
        $this->payNow($booking, 'BCA_VA')->assertStatus(503)->assertJsonPath('success', false);
        $this->assertSame([], $this->created);

        // HTTP 200 dengan status_code 412 (dulu dianggap berhasil) → tetap tidak membuka sesi baru.
        $this->midtransDown = false;
        $this->closeRejected = true;
        $this->payNow($booking, 'BCA_VA')->assertStatus(503);
        $this->assertSame([], $this->created);
        $this->assertSame('pending', $this->gateway['ORD-G1']);
        $this->assertSame('ORD-G1', $this->bill($booking)->payload_log['midtrans_order_id']);
        $this->assertSame(MidtransService::CLOSE_FAILED, app(MidtransService::class)->closeTransaction('ORD-G1')['result']);
    }

    public function test_method_changes_are_limited_but_the_last_session_stays_payable(): void
    {
        $booking = $this->pendingBooking();
        $methods = ['BCA_VA', 'QRIS', 'BNI_VA', 'QRIS', 'BCA_VA'];

        foreach ($methods as $method) {
            $this->payNow($booking, $method)->assertOk();
        }
        $this->assertCount(SnapSessionGuard::MAX_SESSIONS, SnapSessionGuard::sessionsOf($this->bill($booking)));

        $this->payNow($booking, 'QRIS')->assertStatus(429);
        $this->assertCount(5, $this->created);

        $this->payNow($booking, 'BCA_VA')->assertOk()->assertJsonPath('reused_session', true);
    }

    public function test_paying_one_session_closes_the_other_open_sessions(): void
    {
        $booking = $this->pendingBooking();
        // Data lama (sebelum perbaikan): dua sesi bayar ulang yang sama-sama masih hidup.
        Payment::where('order_id', $booking->order_id)->update(['payload_log' => ['midtrans_order_id' => 'ORD-G1_B', 'midtrans_order_ids' => ['ORD-G1_A', 'ORD-G1_B']]]);
        $this->gateway = ['ORD-G1_A' => 'pending', 'ORD-G1_B' => 'settlement'];

        $this->webhook('ORD-G1_B', 'settlement')->assertOk();

        $this->assertSame('PAID', Order::find($booking->order_id)->payment_status);
        $this->assertSame('expire', $this->gateway['ORD-G1_A'], 'VA / QR lama tidak bisa dibayar lagi');
        $this->assertSame(SnapSessionGuard::CLOSED, $this->sessionStatus($booking, 'ORD-G1_A'));
    }

    public function test_a_closed_session_reopened_in_another_tab_is_expired_on_its_pending_notification(): void
    {
        $booking = $this->pendingBooking();
        // Popup QRIS lama belum dibuka saat customer ganti ke VA (Midtrans belum mengenalnya).
        $this->payNow($booking, 'BCA_VA')->assertOk();
        // Belum bisa dimatikan (Midtrans belum mengenalnya) & masih bisa dibuka → tetap diawasi, bukan dianggap tertutup.
        $this->assertSame(SnapSessionGuard::ACTIVE, $this->sessionStatus($booking, 'ORD-G1'));

        // Customer membuka popup lama di tab lain → Midtrans membuat transaksi & mengirim notifikasi pending.
        $this->gateway['ORD-G1'] = 'pending';
        $this->webhook('ORD-G1', 'pending')->assertOk();

        $this->assertSame('expire', $this->gateway['ORD-G1']);
        $this->assertSame(SnapSessionGuard::CLOSED, $this->sessionStatus($booking, 'ORD-G1'));
        $this->assertSame('PENDING', $this->bill($booking)->status, 'tagihan & sesi aktif tidak tersentuh');
    }

    public function test_releasing_a_booking_closes_every_open_session_not_only_the_first(): void
    {
        $booking = $this->pendingBooking();
        $this->payNow($booking, 'BCA_VA')->assertOk();
        $retrySession = $this->created[0]['order_id'];
        $this->gateway[$retrySession] = 'pending'; // customer sudah membuka VA dari sesi bayar ulang

        app(\App\Services\Padel\PadelBookingService::class)->releaseSlots([$booking->id], $this->customer);

        $this->assertSame('CANCELLED', $booking->fresh()->status);
        $this->assertSame('expire', $this->gateway[$retrySession], 'VA sesi bayar ulang ikut dimatikan (dulu tetap bisa ditransfer)');
        $this->assertSame(SnapSessionGuard::CLOSED, $this->sessionStatus($booking, $retrySession));
    }

    public function test_payment_for_an_order_whose_booking_already_expired_in_part_is_never_silent(): void
    {
        $booking = $this->pendingBooking();
        $start = $booking->start_time->copy()->addHour();
        $expired = PadelBooking::create(['booking_code' => 'BK-G1-2', 'order_id' => $booking->order_id, 'user_id' => $this->customer->id, 'court_id' => $this->court->id, 'booking_date' => $booking->booking_date, 'start_time' => $start, 'end_time' => $start->copy()->addHour(), 'court_fee' => 200000, 'total_amount' => 200000, 'status' => 'EXPIRED', 'expires_at' => now()->subMinute()]);
        \Illuminate\Support\Facades\Log::spy();

        $this->gateway['ORD-G1'] = 'settlement';
        $this->webhook('ORD-G1', 'settlement')->assertOk();

        $this->assertSame('PAID', $booking->fresh()->status);
        $this->assertSame('EXPIRED', $expired->fresh()->status);
        \Illuminate\Support\Facades\Log::shouldHaveReceived('critical')->withArgs(fn ($message) => str_contains($message, 'sebagian booking-nya sudah tidak aktif'))->once();
    }

    public function test_paid_amount_that_differs_from_the_bill_is_never_settled(): void
    {
        $booking = $this->pendingBooking();
        // Ditagihkan Rp250.000, Midtrans melaporkan Rp200.000 dibayar.
        $bill = $this->bill($booking);
        $log = $bill->payload_log;
        $log['midtrans_sessions']['ORD-G1']['amount'] = 250000;
        $bill->update(['amount' => 250000, 'payload_log' => $log]);
        $this->gateway['ORD-G1'] = 'settlement';

        $this->webhook('ORD-G1', 'settlement')->assertOk()->assertJsonPath('data.status', 'AMOUNT_MISMATCH');
        $this->webhook('ORD-G1', 'settlement')->assertOk();
        $this->assertSame(
            \App\Services\Payment\MidtransReconciliationService::ERROR,
            app(\App\Services\Payment\MidtransReconciliationService::class)->reconcileOrder(Order::find($booking->order_id)),
            'bukan "lunas" (kasir tidak boleh menganggap tiket aktif) dan bukan "belum bayar" (uangnya ada di Midtrans)'
        );

        $this->assertSame('PENDING', $this->bill($booking)->status);
        $this->assertSame('PENDING_PAYMENT', $booking->fresh()->status);
        $this->assertNotSame('PAID', Order::find($booking->order_id)->payment_status);
        $this->assertSame(1, \App\Models\Audit\ActivityLog::where('event', 'payment.amount_mismatch')->where('severity', 'CRITICAL')->count(), 'alert sekali, tidak berulang tiap notifikasi / rekonsiliasi');
    }

    public function test_reconciliation_records_a_second_payment_whose_webhook_never_arrived(): void
    {
        $booking = $this->pendingBooking();
        Payment::where('order_id', $booking->order_id)->update(['payload_log' => ['midtrans_order_id' => 'ORD-G1_B', 'midtrans_order_ids' => ['ORD-G1_A', 'ORD-G1_B']]]);
        $this->gateway = ['ORD-G1_A' => 'pending', 'ORD-G1_B' => 'settlement'];
        $this->webhook('ORD-G1_B', 'settlement')->assertOk();

        // Penutupan sesi A gagal saat itu, lalu customer tetap mentransfer VA-nya dan webhook-nya hilang.
        $this->gateway['ORD-G1_A'] = 'settlement';
        $bill = $this->bill($booking);
        $log = $bill->payload_log;
        $log['midtrans_sessions']['ORD-G1_A']['status'] = SnapSessionGuard::ACTIVE;
        $bill->update(['payload_log' => $log]);

        $this->artisan('payment:reconcile-midtrans')->assertSuccessful();
        $this->artisan('payment:reconcile-midtrans')->assertSuccessful();

        $duplicate = Payment::where('transaction_id', 'ORD-G1_A')->first();
        $this->assertSame('DUPLICATE', $duplicate?->status);
        $this->assertSame(1, Refund::where('payment_id', $duplicate->id)->where('status', 'PENDING')->count());
        $this->assertEquals(200000, (float) Refund::where('payment_id', $duplicate->id)->value('refund_amount'));
        $this->assertSame(SnapSessionGuard::DUPLICATE, $this->bill($booking)->payload_log['midtrans_sessions']['ORD-G1_A']['status']);
    }

    public function test_retry_keeps_the_checkout_price_even_after_tax_settings_change(): void
    {
        $booking = $this->pendingBooking();

        // Admin menyalakan pajak 10% setelah customer checkout — hanya berlaku untuk transaksi baru.
        \App\Models\Pos\ClubFinanceSetting::first()->update(['is_tax_enabled' => true, 'tax_name' => 'PB1', 'tax_type' => 'PERCENTAGE', 'tax_rate' => 10.00, 'tax_channels' => 'ALL']);
        \Illuminate\Support\Facades\Cache::forget(\App\Models\Pos\ClubFinanceSetting::CACHE_KEY);

        $this->payNow($booking, 'BCA_VA')->assertOk()->assertJsonPath('grand_total', 200000);

        $this->assertSame(200000, $this->created[0]['gross_amount']);
        $this->assertEquals(200000, (float) Order::find($booking->order_id)->grand_total);
        $this->assertEquals(0, (float) Order::find($booking->order_id)->tax_amount);
    }

    public function test_reconciliation_never_overwrites_a_bill_that_was_just_paid(): void
    {
        $booking = $this->pendingBooking();
        $this->gateway['ORD-G1'] = 'expire';
        $bill = $this->bill($booking);

        // Webhook melunasi tagihan TEPAT setelah rekonsiliasi membaca tagihan yang masih PENDING.
        $fired = false;
        Payment::retrieved(function (Payment $p) use (&$fired, $bill) {
            $inClose = collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 40))->contains(fn ($f) => ($f['function'] ?? '') === 'closeAbandonedPayments');
            if ($fired || ! $inClose || $p->id !== $bill->id) {
                return;
            }
            $fired = true;
            \Illuminate\Support\Facades\DB::table('payments')->where('id', $bill->id)->update(['status' => 'SUCCESS']);
        });

        app(\App\Services\Payment\MidtransReconciliationService::class)->reconcileOrder(Order::find($booking->order_id));

        $this->assertTrue($fired);
        $this->assertSame('SUCCESS', $bill->fresh()->status, 'dulu tertimpa FAILED dari data lama');
    }

    public function test_slot_cleaner_never_cancels_an_order_that_was_just_paid(): void
    {
        $booking = $this->pendingBooking();
        $booking->update(['expires_at' => now()->subMinutes(10)]);
        $this->gateway['ORD-G1'] = 'expire';

        // Webhook melunasi order di sela proses: booking sudah dihanguskan, order belum dibatalkan.
        $fired = false;
        PadelBooking::retrieved(function (PadelBooking $b) use (&$fired, $booking) {
            if ($fired || $b->id !== $booking->id || $b->status !== 'EXPIRED'
                || ! collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS))->pluck('function')->contains('releaseExpiredLocks')) {
                return;
            }
            $fired = true;
            \Illuminate\Support\Facades\DB::table('orders')->where('id', $booking->order_id)->update(['payment_status' => 'PAID']);
            \Illuminate\Support\Facades\DB::table('payments')->where('order_id', $booking->order_id)->update(['status' => 'SUCCESS']);
        });

        app(\App\Services\Padel\PadelBookingService::class)->releaseExpiredLocks();

        $this->assertTrue($fired);
        $this->assertSame('EXPIRED', $booking->fresh()->status);
        $this->assertSame('PAID', Order::find($booking->order_id)->payment_status, 'dulu tertimpa CANCELLED dari data lama');
    }

    public function test_invoice_turns_expired_as_soon_as_midtrans_confirms_the_session_expired(): void
    {
        $booking = $this->pendingBooking();
        $booking->update(['expires_at' => now()->subSeconds(30)]);
        $this->gateway['ORD-G1'] = 'expire';

        // Dulu menunggu jeda 3 menit + scheduler tiap menit: "slot sedang dilepas" berputar 3–4 menit.
        $this->actingAs($this->customer, 'sanctum')
            ->getJson("/api/v1/padel/bookings/{$booking->id}/ticket?verify_payment=1")
            ->assertOk()
            ->assertJsonPath('data.status', 'EXPIRED');

        $this->assertSame('CANCELLED', Order::find($booking->order_id)->payment_status);
        $this->assertSame('FAILED', $this->bill($booking)->status);
    }

    public function test_slot_is_kept_during_the_grace_until_midtrans_confirms_the_session_ended(): void
    {
        $booking = $this->pendingBooking();
        $booking->update(['expires_at' => now()->subSeconds(30)]);
        $service = app(\App\Services\Padel\PadelBookingService::class);

        // Midtrans masih "pending" (expire-nya bisa telat s/d 90 detik) → notifikasi bayar mungkin masih datang.
        $this->gateway['ORD-G1'] = 'pending';
        $this->assertSame(0, $service->releaseExpiredLocks());

        // Sesi tidak dikenal Midtrans (404) belum pasti berakhir → tetap tunggu jeda.
        unset($this->gateway['ORD-G1']);
        \Illuminate\Support\Facades\Cache::flush();
        $this->assertSame(0, $service->releaseExpiredLocks());
        $this->assertSame('PENDING_PAYMENT', $booking->fresh()->status);

        $this->travel(3)->minutes();
        $this->assertSame(1, $service->releaseExpiredLocks());
        $this->assertSame('EXPIRED', $booking->fresh()->status);
    }

    public function test_membership_checkout_twice_with_the_same_method_reuses_the_session(): void
    {
        $plan = MembershipPlan::create(['code' => 'MBR-'.Str::random(5), 'name' => 'Silver', 'ownership_type' => 'INDIVIDUAL', 'duration_days' => 30, 'price' => 1_500_000, 'is_active' => true]);
        $checkout = fn () => $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/membership/checkout', ['plan_id' => $plan->id, 'payment_method' => 'QRIS']);

        $first = $checkout()->assertCreated()->json('data.payment.snap_token');
        $second = $checkout()->assertOk()->assertJsonPath('data.resumed', true)->json('data.payment.snap_token');

        $this->assertCount(1, $this->created);
        $this->assertSame($first, $second);
    }
}
