<?php

namespace Tests\Feature\Payment;

use App\Exceptions\PaymentGatewayUnavailableException;
use App\Models\Membership\MembershipPlan;
use App\Models\Membership\MembershipPlanBenefit;
use App\Models\Membership\UserMembership;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\User;
use App\Services\Payment\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Bug kritis: dulu kalau Midtrans error (jaringan putus / key salah / request ditolak) atau server key
 * kosong, MidtransService diam-diam membalas token MOCK → booking padel langsung PAID dan membership
 * online langsung ACTIVE tanpa uang masuk. Di server (bukan local/testing) checkout wajib gagal.
 */
class MidtransFailClosedTest extends TestCase
{
    use RefreshDatabase;

    private function runAsServer(string $env = 'staging', string $serverKey = 'SB-Mid-server-TEST-KEY'): void
    {
        $this->app['env'] = $env;
        config(['services.midtrans.server_key' => $serverKey, 'services.midtrans.is_production' => false]);
    }

    private function snapParams(): array
    {
        return [
            'order_id' => 'ORD-FAILCLOSED-1',
            'gross_amount' => 100000,
            'item_details' => [['id' => 'X', 'price' => 100000, 'quantity' => 1, 'name' => 'Item']],
            'customer_details' => [],
        ];
    }

    public function test_midtrans_unreachable_throws_instead_of_returning_mock_token(): void
    {
        $this->runAsServer();
        Http::fake(fn () => throw new ConnectionException('Midtrans down'));

        $this->expectException(PaymentGatewayUnavailableException::class);
        (new MidtransService())->createSnapTransaction($this->snapParams());
    }

    public function test_midtrans_rejecting_request_throws_instead_of_returning_mock_token(): void
    {
        $this->runAsServer();
        Http::fake(['*' => Http::response(['error_messages' => ['Access denied due to unauthorized transaction']], 401)]);

        $this->expectException(PaymentGatewayUnavailableException::class);
        (new MidtransService())->createSnapTransaction($this->snapParams());
    }

    public function test_empty_server_key_on_production_is_rejected(): void
    {
        $this->runAsServer('production', '');
        Http::fake();

        try {
            (new MidtransService())->createSnapTransaction($this->snapParams());
            $this->fail('Checkout dengan server key kosong di production harus ditolak.');
        } catch (PaymentGatewayUnavailableException $e) {
            $this->assertSame(503, $e->getStatusCode());
        }
        Http::assertNothingSent();
    }

    public function test_local_developer_without_key_still_gets_mock_token(): void
    {
        $this->runAsServer('local', '');

        $result = (new MidtransService())->createSnapTransaction($this->snapParams());

        $this->assertTrue($result['is_mock']);
    }

    public function test_successful_snap_response_returns_real_token(): void
    {
        $this->runAsServer();
        Http::fake(['*' => Http::response(['token' => 'real-snap-token', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/x'], 201)]);

        $result = (new MidtransService())->createSnapTransaction($this->snapParams());

        $this->assertFalse($result['is_mock']);
        $this->assertSame('real-snap-token', $result['snap_token']);
    }

    public function test_membership_checkout_does_not_activate_membership_when_midtrans_fails(): void
    {
        $plan = MembershipPlan::create([
            'code' => 'MBR-FAILCLOSED',
            'name' => 'Gold Plan',
            'ownership_type' => 'INDIVIDUAL',
            'duration_days' => 30,
            'price' => 5000000.00,
            'is_active' => true,
        ]);
        MembershipPlanBenefit::create([
            'plan_id' => $plan->id,
            'facility' => 'PADEL',
            'quota_type' => 'HOURS',
            'quota_value' => 20.00,
            'discount_percent' => 20.00,
        ]);
        $customer = User::factory()->create();

        $this->runAsServer();
        Http::fake(fn () => throw new ConnectionException('Midtrans down'));

        $this->actingAs($customer)
            ->postJson('/api/v1/membership/checkout', ['plan_id' => $plan->id, 'payment_method' => 'QRIS'])
            ->assertStatus(503)
            ->assertJsonPath('success', false);

        $this->assertSame(0, UserMembership::where('user_id', $customer->id)->count(), 'Kartu membership tidak boleh terbentuk, apalagi aktif');
        $this->assertSame(0, Order::where('user_id', $customer->id)->count(), 'Order harus ikut di-rollback');
        $this->assertSame(0, Payment::count());
    }

    public function test_padel_checkout_does_not_mark_booking_paid_when_midtrans_fails(): void
    {
        $customer = User::factory()->customer()->create();
        $court = PadelCourt::create([
            'name' => 'Court Fail Closed',
            'type' => 'INDOOR',
            'hourly_rate_regular' => 200000,
            'hourly_rate_prime' => 300000,
            'is_active' => true,
        ]);
        $start = now()->addDays(2)->setTime(10, 0);
        $booking = PadelBooking::create([
            'booking_code' => 'BK-PAD-FAILCL',
            'user_id' => $customer->id,
            'court_id' => $court->id,
            'booking_date' => $start->format('Y-m-d'),
            'start_time' => $start,
            'end_time' => $start->copy()->addHour(),
            'court_fee' => 200000,
            'total_amount' => 200000,
            'status' => 'LOCKED',
        ]);

        $this->runAsServer();
        Http::fake(['*' => Http::response(['error_messages' => ['Midtrans internal error']], 500)]);

        $this->actingAs($customer, 'sanctum')
            ->withHeader('X-Idempotency-Key', (string) \Illuminate\Support\Str::uuid())
            ->postJson('/api/v1/padel/checkout', ['booking_ids' => [$booking->id], 'payment_method' => 'QRIS'])
            ->assertStatus(503)
            ->assertJsonPath('success', false);

        $booking->refresh();
        $this->assertSame('LOCKED', $booking->status, 'Slot tetap dikunci supaya customer bisa coba bayar lagi');
        $this->assertNull($booking->qr_code_hash, 'QR tiket tidak boleh terbit');
        $this->assertNull($booking->order_id);
        $this->assertSame(0, Order::count());
        $this->assertSame(0, Payment::count());
    }
}
