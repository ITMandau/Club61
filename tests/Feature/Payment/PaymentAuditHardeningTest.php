<?php

namespace Tests\Feature\Payment;

use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/** Audit 16 Okt 2026 — temuan rendah jalur pembayaran. */
class PaymentAuditHardeningTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'SB-Mid-server-AUDIT-HARDENING';

    public function test_retry_payment_cannot_revive_an_expired_hold(): void
    {
        $customer = User::factory()->customer()->create();
        $court = PadelCourt::create(['name' => 'Court Audit', 'type' => 'INDOOR', 'hourly_rate_regular' => 200000, 'hourly_rate_prime' => 200000, 'is_active' => true]);
        $start = now()->addDays(3)->setTime(10, 0);
        $booking = PadelBooking::create([
            'booking_code' => 'BK-AUDIT-1', 'user_id' => $customer->id, 'court_id' => $court->id, 'booking_date' => $start->toDateString(),
            'start_time' => $start, 'end_time' => $start->copy()->addHour(), 'court_fee' => 200000, 'total_amount' => 200000,
            'status' => 'LOCKED', 'expires_at' => now()->subMinute(),
        ]);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/v1/padel/bookings/{$booking->id}/retry-payment", ['payment_method' => 'QRIS'])
            ->assertStatus(422);

        $booking->refresh();
        $this->assertSame('LOCKED', $booking->status, 'hold yang habis tidak diubah jadi PENDING_PAYMENT dengan batas bayar baru');
        $this->assertTrue($booking->expires_at->isPast());
    }

    public function test_rejected_webhook_does_not_log_the_signature_or_payload(): void
    {
        config(['services.midtrans.server_key' => self::KEY]);
        Log::spy();

        $this->postJson('/api/v1/padel/webhook/midtrans', [
            'order_id' => 'ORD-SPOOF-1', 'status_code' => '200', 'gross_amount' => '1.00', 'transaction_status' => 'settlement',
            'signature_key' => 'not-a-valid-signature', 'va_numbers' => [['bank' => 'bca', 'va_number' => '1234567890']],
        ])->assertStatus(400);

        Log::shouldHaveReceived('warning')->withArgs(function ($message, $context = []) {
            return str_contains($message, 'SPOOFING')
                && ! array_key_exists('payload', $context)
                && ! str_contains(json_encode($context), 'not-a-valid-signature')
                && ! str_contains(json_encode($context), '1234567890')
                && ($context['order_id'] ?? null) === 'ORD-SPOOF-1';
        })->once();
    }

    public function test_valid_paid_notification_for_an_unknown_order_raises_an_alert(): void
    {
        config(['services.midtrans.server_key' => self::KEY]);
        Log::spy();

        $id = 'ORD-UNKNOWN-1';
        $this->postJson('/api/v1/padel/webhook/midtrans', [
            'order_id' => $id, 'status_code' => '200', 'gross_amount' => '150000.00', 'transaction_status' => 'settlement', 'payment_type' => 'qris',
            'signature_key' => hash('sha512', $id.'200'.'150000.00'.self::KEY),
        ])->assertOk();

        Log::shouldHaveReceived('critical')->withArgs(fn ($message) => str_contains($message, 'order yang tidak dikenal') && str_contains($message, $id))->once();
    }

    public function test_webhook_routes_are_throttled(): void
    {
        foreach (['api.payment.webhook.midtrans', 'api.payment.webhook'] as $name) {
            $this->assertContains('throttle:300,1', app('router')->getRoutes()->getByName($name)->gatherMiddleware(), $name);
        }
        $pos = collect(app('router')->getRoutes()->getRoutes())->first(fn ($r) => $r->uri() === 'api/v1/payments/webhook');
        $this->assertContains('throttle:300,1', $pos->gatherMiddleware());
    }
}
