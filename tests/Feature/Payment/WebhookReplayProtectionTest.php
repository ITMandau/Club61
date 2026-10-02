<?php

namespace Tests\Feature\Payment;

use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\Pos\Refund;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Signature Midtrans = SHA512(order_id + status_code + gross_amount + server key) — TIDAK mencakup
 * transaction_status. Dulu notifikasi expire / cancel lengkap dengan signature_key disimpan di payload_log, dan API
 * tiket customer mengirim payload_log itu apa adanya. Customer bisa memutar ulang signature tersebut dengan
 * transaction_status=settlement → order "lunas" + refund PENDING palsu untuk uang yang tidak pernah masuk.
 */
class WebhookReplayProtectionTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'SB-Mid-server-REPLAY';

    protected User $customer;

    protected PadelBooking $booking;

    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.midtrans.server_key' => self::KEY]);
        $this->customer = User::factory()->customer()->create();
        $court = PadelCourt::create(['name' => 'Court R', 'type' => 'INDOOR', 'hourly_rate_regular' => 200000, 'hourly_rate_prime' => 300000, 'is_active' => true]);
        $this->order = Order::create(['order_number' => 'ORD-RPL', 'user_id' => $this->customer->id, 'order_type' => 'ONLINE_BOOKING', 'subtotal' => 200000, 'grand_total' => 200000, 'payment_status' => 'UNPAID']);
        Payment::create(['order_id' => $this->order->id, 'payment_gateway' => 'MIDTRANS', 'transaction_id' => 'ORD-RPL', 'amount' => 200000, 'payment_method' => 'BCA_VA', 'status' => 'PENDING']);
        $start = Carbon::parse(now()->addDays(2)->format('Y-m-d').' 10:00');
        $this->booking = PadelBooking::create(['booking_code' => 'BK-RPL', 'order_id' => $this->order->id, 'user_id' => $this->customer->id, 'court_id' => $court->id, 'booking_date' => $start->toDateString(), 'start_time' => $start, 'end_time' => $start->copy()->addHour(), 'court_fee' => 200000, 'total_amount' => 200000, 'status' => 'PENDING_PAYMENT']);
    }

    private function notification(string $transactionStatus, string $statusCode): array
    {
        return [
            'order_id' => 'ORD-RPL', 'status_code' => $statusCode, 'gross_amount' => '200000.00',
            'signature_key' => hash('sha512', 'ORD-RPL'.$statusCode.'200000.00'.self::KEY),
            'transaction_status' => $transactionStatus, 'payment_type' => 'bank_transfer',
        ];
    }

    public function test_gateway_signatures_are_never_stored_or_sent_to_the_customer(): void
    {
        $this->postJson('/api/v1/padel/webhook/midtrans', $this->notification('expire', '407'))->assertOk();

        $this->assertArrayNotHasKey('signature_key', Payment::where('transaction_id', 'ORD-RPL')->first()->payload_log['gateway_notification'] ?? []);

        $json = $this->actingAs($this->customer, 'sanctum')->getJson("/api/v1/padel/bookings/{$this->booking->id}/ticket")->assertOk()->getContent();
        $this->assertStringNotContainsString('signature_key', $json);
        $this->assertStringNotContainsString('payload_log', $json);
    }

    public function test_replayed_expire_signature_cannot_mark_the_order_paid(): void
    {
        config(['services.midtrans.verify_webhook_with_status_api' => true]);
        Http::fake(['api.sandbox.midtrans.com/*' => Http::response([
            'order_id' => 'ORD-RPL', 'status_code' => '407', 'transaction_status' => 'expire', 'gross_amount' => '200000.00',
        ], 200)]);

        // Signature asli notifikasi expire (status_code 407) diputar ulang dengan transaction_status=settlement.
        $this->postJson('/api/v1/padel/webhook/midtrans', $this->notification('settlement', '407'))->assertOk();

        $this->assertNotSame('PAID', $this->order->fresh()->payment_status);
        $this->assertSame(0, Payment::where('order_id', $this->order->id)->where('status', 'SUCCESS')->count());
        $this->assertSame(0, Refund::count());
    }

    public function test_genuine_settlement_is_confirmed_with_the_status_api(): void
    {
        config(['services.midtrans.verify_webhook_with_status_api' => true]);
        Http::fake(['api.sandbox.midtrans.com/*' => Http::response([
            'order_id' => 'ORD-RPL', 'status_code' => '200', 'transaction_status' => 'settlement', 'gross_amount' => '200000.00', 'payment_type' => 'bank_transfer',
        ], 200)]);

        $this->postJson('/api/v1/padel/webhook/midtrans', $this->notification('settlement', '200'))->assertOk();

        $this->assertSame('PAID', $this->order->fresh()->payment_status);
        $this->assertSame('PAID', $this->booking->fresh()->status);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/v2/ORD-RPL/status'));
    }

    public function test_settlement_is_retried_when_the_status_api_is_unreachable(): void
    {
        config(['services.midtrans.verify_webhook_with_status_api' => true]);
        Http::fake(['api.sandbox.midtrans.com/*' => Http::response('Bad Gateway', 502)]);

        $this->postJson('/api/v1/padel/webhook/midtrans', $this->notification('settlement', '200'))->assertStatus(503);

        $this->assertNotSame('PAID', $this->order->fresh()->payment_status);
    }
}
