<?php

namespace Tests\Feature\Padel;

use App\Models\Padel\BookingTimeSetting;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\Pos\Refund;
use App\Models\User;
use App\Services\Padel\BookingTimeService;
use App\Services\Padel\PadelBookingService;
use App\Services\Payment\PaymentOrchestratorService;
use App\Services\Permission\Club61PermissionMatrix;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Regresi audit pra-launching (2 Okt 2026): celah di alur ganti metode bayar, batas waktu bayar, dan walk-in.
 */
class LaunchAuditRegressionTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'SB-Mid-server-LAUNCH';

    protected User $customer;

    protected PadelCourt $court;

    protected string $date;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');
        $this->customer = User::factory()->customer()->create();
        $this->court = PadelCourt::create(['name' => 'Court L', 'type' => 'INDOOR', 'hourly_rate_regular' => 200000, 'hourly_rate_prime' => 300000, 'is_active' => true]);
        $this->date = now()->addDays(3)->format('Y-m-d');
        config(['services.midtrans.server_key' => self::KEY]);
    }

    private function booking(Order $order, string $status, array $extra = []): PadelBooking
    {
        $start = Carbon::parse("{$this->date} 10:00");

        return PadelBooking::create(array_merge([
            'booking_code' => 'BK-'.Str::random(5), 'order_id' => $order->id, 'user_id' => $this->customer->id, 'court_id' => $this->court->id,
            'booking_date' => $this->date, 'start_time' => $start, 'end_time' => $start->copy()->addHour(),
            'court_fee' => 200000, 'total_amount' => 200000, 'status' => $status,
        ], $extra));
    }

    private function order(string $number, float $total, string $status = 'UNPAID'): Order
    {
        return Order::create(['order_number' => $number, 'user_id' => $this->customer->id, 'order_type' => 'ONLINE_BOOKING', 'subtotal' => $total, 'grand_total' => $total, 'payment_status' => $status]);
    }

    public function test_paying_an_older_session_of_an_already_paid_bill_is_recorded_and_refunded(): void
    {
        $order = $this->order('ORD-2SES', 200000);
        $this->booking($order, 'PENDING_PAYMENT');
        Payment::create(['order_id' => $order->id, 'payment_gateway' => 'MIDTRANS', 'transaction_id' => 'ORD-2SES', 'amount' => 200000, 'payment_method' => 'BCA_VA', 'status' => 'PENDING',
            'payload_log' => ['midtrans_order_id' => 'ORD-2SES_2', 'midtrans_order_ids' => ['ORD-2SES_1', 'ORD-2SES_2']]]);
        $orchestrator = app(PaymentOrchestratorService::class);

        // Customer ganti metode lalu bayar sesi terbaru (QRIS)…
        $orchestrator->markOrderAsPaid($order, ['payment_gateway' => 'MIDTRANS', 'transaction_id' => 'ORD-2SES_2', 'payment_method' => 'QRIS', 'amount' => 200000]);
        // …lalu VA dari sesi sebelumnya ikut ditransfer.
        $orchestrator->markOrderAsPaid($order, ['payment_gateway' => 'MIDTRANS', 'transaction_id' => 'ORD-2SES_1', 'payment_method' => 'BANK_TRANSFER', 'amount' => 200000]);
        // Notifikasi duplikat sesi yang sama tetap diabaikan.
        $orchestrator->markOrderAsPaid($order, ['payment_gateway' => 'MIDTRANS', 'transaction_id' => 'ORD-2SES_1', 'payment_method' => 'BANK_TRANSFER', 'amount' => 200000]);

        $this->assertSame(2, Payment::where('order_id', $order->id)->where('status', 'SUCCESS')->count());
        $this->assertSame(1, Refund::where('order_id', $order->id)->where('status', 'PENDING')->count());
        $this->assertEquals(200000, (float) Refund::where('order_id', $order->id)->value('refund_amount'));
    }

    public function test_reconciliation_waits_for_a_long_payment_window_before_closing_a_bill(): void
    {
        BookingTimeSetting::query()->update(['payment_window_minutes' => 45]);
        app(BookingTimeService::class)->flush();
        $order = $this->order('ORD-LONG', 200000);
        $this->booking($order, 'PENDING_PAYMENT', ['expires_at' => now()->addMinutes(10)]);
        Payment::create(['order_id' => $order->id, 'payment_gateway' => 'MIDTRANS', 'transaction_id' => 'ORD-LONG', 'amount' => 200000, 'payment_method' => 'QRIS', 'status' => 'PENDING']);
        Payment::where('order_id', $order->id)->update(['created_at' => now()->subMinutes(35), 'updated_at' => now()->subMinutes(35)]);
        $this->app['env'] = 'staging';
        Http::fake(['api.sandbox.midtrans.com/*' => Http::response(['status_code' => '404'], 404)]);

        app(\App\Services\Payment\MidtransReconciliationService::class)->reconcileOrder($order);

        $this->assertSame('PENDING', Payment::where('order_id', $order->id)->value('status'), 'sesi Snap 45 menit masih bisa dibayar');
    }

    public function test_reschedule_surcharge_on_a_fully_member_covered_order_stays_a_surcharge(): void
    {
        $order = $this->order('ORD-FREE', 0, 'PAID');
        Payment::create(['order_id' => $order->id, 'payment_gateway' => 'MEMBERSHIP_QUOTA', 'transaction_id' => 'ORD-FREE', 'amount' => 0, 'payment_method' => 'MEMBERSHIP_QUOTA', 'status' => 'SUCCESS']);
        $booking = $this->booking($order, 'LOCKED', ['reschedule_count' => 1]);
        $bill = Payment::create(['order_id' => $order->id, 'payment_gateway' => 'CASHIER_POS', 'transaction_id' => 'SUPP-FREE', 'amount' => 150000, 'payment_method' => 'MENUNGGU_PEMBAYARAN', 'status' => 'PENDING',
            'payload_log' => ['type' => 'RESCHEDULE_PRICE_DELTA', 'booking_id' => $booking->id]]);

        $this->actingAs($this->customer, 'sanctum')->getJson("/api/v1/padel/bookings/{$booking->id}/ticket")
            ->assertOk()->assertJsonPath('data.has_pending_delta', true);

        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/padel/bookings/{$booking->id}/retry-payment", ['payment_method' => 'QRIS'])
            ->assertOk()->assertJsonPath('grand_total', 150000);

        $this->assertEquals(150000, (float) $bill->fresh()->amount, 'nominal selisih tidak ditimpa');
        $this->assertEquals(0, (float) $order->fresh()->grand_total, 'order tidak dihitung ulang');
        $this->assertSame('LOCKED', $booking->fresh()->status);
    }

    public function test_cart_and_checkout_timers_never_cancel_a_booking_that_is_being_paid(): void
    {
        $order = $this->order('ORD-TMR', 200000);
        $booking = $this->booking($order, 'PENDING_PAYMENT', ['expires_at' => now()->addMinutes(10)]);

        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/padel/release-slot', ['booking_ids' => [$booking->id], 'only_locked' => true])
            ->assertOk();

        $this->assertSame('PENDING_PAYMENT', $booking->fresh()->status);
        $this->assertSame('UNPAID', $order->fresh()->payment_status);
    }

    public function test_an_expired_hold_cannot_be_checked_out(): void
    {
        $id = $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/padel/hold-slot', [
            'booking_date' => $this->date, 'slots' => [['court_id' => $this->court->id, 'start_time' => '12:00', 'end_time' => '13:00']],
        ])->assertCreated()->json('data.bookings.0.id');
        PadelBooking::whereKey($id)->update(['expires_at' => now()->subSecond()]);

        $this->actingAs($this->customer, 'sanctum')->withHeader('X-Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/padel/checkout', ['booking_ids' => [$id], 'payment_method' => 'QRIS'])
            ->assertStatus(422);
        $this->assertSame('LOCKED', PadelBooking::find($id)->status);
    }

    public function test_expire_notification_before_the_deadline_does_not_cancel_the_booking(): void
    {
        $order = $this->order('ORD-EXP', 200000);
        $booking = $this->booking($order, 'PENDING_PAYMENT', ['expires_at' => now()->addSeconds(50)]);
        Payment::create(['order_id' => $order->id, 'payment_gateway' => 'MIDTRANS', 'transaction_id' => 'ORD-EXP', 'amount' => 200000, 'payment_method' => 'QRIS', 'status' => 'PENDING',
            'payload_log' => ['midtrans_order_id' => 'ORD-EXP_2', 'midtrans_order_ids' => ['ORD-EXP_2']]]);

        // Sesi bayar ulang (dibulatkan ke bawah) kedaluwarsa duluan; VA sesi checkout masih berlaku sampai batas bayar.
        $this->postJson('/api/v1/padel/webhook/midtrans', [
            'order_id' => 'ORD-EXP_2', 'status_code' => '407', 'gross_amount' => '200000.00',
            'signature_key' => hash('sha512', 'ORD-EXP_2'.'407'.'200000.00'.self::KEY),
            'transaction_status' => 'expire', 'payment_type' => 'qris',
        ])->assertOk();

        $this->assertSame('PENDING_PAYMENT', $booking->fresh()->status);
        $this->assertSame('PENDING', Payment::where('order_id', $order->id)->value('status'));
    }

    public function test_failed_walk_in_checkout_releases_the_slot_immediately(): void
    {
        $cashier = User::factory()->superAdmin()->create();
        $customer = User::factory()->customer()->create();
        $service = app(PadelBookingService::class);
        $slots = [['court_id' => $this->court->id, 'start_time' => '15:00:00', 'end_time' => '16:00:00']];

        try {
            // Belum ada shift kasir yang dibuka → pelunasan ditolak.
            $service->processWalkInCheckout(customer: $customer, slots: $slots, bookingDate: $this->date, equipments: [], paymentMethod: 'QRIS', cashier: $cashier);
            $this->fail('Walk-in tanpa shift aktif harus ditolak.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertSame(0, PadelBooking::where('user_id', $customer->id)->whereIn('status', ['LOCKED', 'PENDING_PAYMENT', 'PAID'])->count());

        // Kasir buka shift lalu coba lagi: slot tidak lagi "sedang di-hold".
        \App\Models\Pos\PosCashierShift::create([
            'shift_number' => 'SFT-L-'.Str::random(4), 'counter' => 'PADEL_FRONTDESK', 'status' => 'OPEN',
            'opened_by_id' => $cashier->id, 'opened_at' => now(), 'starting_cash' => 0, 'expected_cash' => 0,
        ]);
        $result = $service->processWalkInCheckout(customer: $customer, slots: $slots, bookingDate: $this->date, equipments: [], paymentMethod: 'QRIS', cashier: $cashier, paymentMeta: ['qris_details' => ['rrn' => 'RRN-'.Str::random(8)]]);
        $this->assertNotEmpty($result);
    }
}
