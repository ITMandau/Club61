<?php

namespace Tests\Feature\Security;

use App\Models\Padel\PadelCourt;
use App\Models\Pos\Voucher;
use App\Models\User;
use App\Services\Payment\MidtransService;
use App\Services\Payment\PaymentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SecurityHardeningAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected string $customerToken;
    protected PadelCourt $court;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::factory()->customer()->create();
        $this->customerToken = $this->customer->createToken('test-customer')->plainTextToken;

        $this->court = PadelCourt::create([
            'name' => 'Court Test Pro',
            'type' => 'INDOOR',
            'hourly_rate_regular' => 200000.00,
            'hourly_rate_prime' => 300000.00,
            'is_active' => true,
        ]);
    }

    public function test_payment_manager_rejects_mock_driver_in_production(): void
    {
        $manager = new PaymentManager();

        // Pada testing environment, mock driver dapat diakses
        $driver = $manager->driver('mock');
        $this->assertInstanceOf(\App\Services\Payment\Drivers\MockSimulatorDriver::class, $driver);

        // Simulasikan environment production dengan instance baru
        $this->app['env'] = 'production';
        $prodManager = new PaymentManager();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Driver simulasi mock dinonaktifkan pada environment production.');

        $prodManager->driver('mock');
    }

    public function test_payment_webhook_rejects_mock_driver_in_production_with_http_403(): void
    {
        $this->app['env'] = 'production';

        $response = $this->postJson('/api/v1/padel/webhook/mock', [
            'order_id' => 'ORD-TEST-MOCK-999',
            'status' => 'PAID',
            'gross_amount' => 200000,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Driver simulasi mock dinonaktifkan pada environment production.',
            ]);
    }

    public function test_midtrans_signature_fails_closed_in_production_when_server_key_is_empty(): void
    {
        // Dalam local/testing, server key kosong mengizinkan fallback
        $this->app['env'] = 'testing';
        $validInTesting = MidtransService::verifySignature('ORD-1', '200', '200000', 'dummy-hash', '');
        $this->assertTrue($validInTesting);

        // Dalam production, server key kosong wajib ditolak (fail-closed)
        $this->app['env'] = 'production';
        $validInProduction = MidtransService::verifySignature('ORD-1', '200', '200000', 'dummy-hash', '');
        $this->assertFalse($validInProduction);
    }

    public function test_voucher_database_validation_and_atomic_decrement(): void
    {
        // 1. Buat voucher aktif dengan kuota 2
        $voucher = Voucher::create([
            'code' => 'DISC50K',
            'discount_type' => 'FIXED',
            'discount_value' => 50000.00,
            'min_order_amount' => 100000.00,
            'max_discount_amount' => null,
            'quota' => 2,
            'used_count' => 0,
            'valid_until' => now()->addDays(7),
            'is_active' => true,
        ]);

        // 2. Hold slot lapangan
        $bookingDate = now()->addDays(2)->format('Y-m-d');
        $holdRes = $this->withHeader('Authorization', "Bearer {$this->customerToken}")
            ->postJson('/api/v1/padel/hold-slot', [
                'booking_date' => $bookingDate,
                'slots' => [
                    [
                        'court_id' => $this->court->id,
                        'start_time' => '08:00',
                        'end_time' => '09:00',
                    ],
                ],
            ]);

        $holdRes->assertStatus(201);
        $bookingId = $holdRes->json('data.bookings.0.id');

        // 3. Checkout dengan voucher DISC50K (Mock driver di testing menghasilkan status PAID)
        $checkoutRes = $this->withHeader('Authorization', "Bearer {$this->customerToken}")
            ->withHeader('X-Idempotency-Key', \Illuminate\Support\Str::uuid()->toString())
            ->postJson('/api/v1/padel/checkout', [
                'booking_ids' => [$bookingId],
                'voucher_code' => 'DISC50K',
                'payment_method' => 'QRIS',
            ]);

        $checkoutRes->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.discount', 50000);

        // 4. Verifikasi kuota voucher berkurang 1 dan used_count bertambah 1
        $voucher->refresh();
        $this->assertEquals(1, $voucher->quota);
        $this->assertEquals(1, $voucher->used_count);
    }

    public function test_voucher_rejected_when_expired_or_quota_exhausted(): void
    {
        Voucher::create([
            'code' => 'EXPIRED10',
            'discount_type' => 'FIXED',
            'discount_value' => 20000.00,
            'min_order_amount' => 50000.00,
            'quota' => 10,
            'used_count' => 0,
            'valid_until' => now()->subDay(),
            'is_active' => true,
        ]);

        $bookingDate = now()->addDays(2)->format('Y-m-d');
        $holdRes = $this->withHeader('Authorization', "Bearer {$this->customerToken}")
            ->postJson('/api/v1/padel/hold-slot', [
                'booking_date' => $bookingDate,
                'slots' => [
                    [
                        'court_id' => $this->court->id,
                        'start_time' => '10:00',
                        'end_time' => '11:00',
                    ],
                ],
            ]);

        $holdRes->assertStatus(201);
        $bookingId = $holdRes->json('data.bookings.0.id');

        // Checkout dengan voucher expired tidak mendapatkan diskon
        $checkoutRes = $this->withHeader('Authorization', "Bearer {$this->customerToken}")
            ->withHeader('X-Idempotency-Key', \Illuminate\Support\Str::uuid()->toString())
            ->postJson('/api/v1/padel/checkout', [
                'booking_ids' => [$bookingId],
                'voucher_code' => 'EXPIRED10',
                'payment_method' => 'QRIS',
            ]);

        $checkoutRes->assertStatus(200)
            ->assertJsonPath('data.discount', 0);
    }
}
