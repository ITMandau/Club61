<?php

namespace Tests\Feature;

use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityHardeningAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $cashier;
    protected User $admin;
    protected User $kitchen;
    protected PadelCourt $court;
    protected string $customerToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::create([
            'name' => 'Member Customer',
            'email' => 'customer@club61.test',
            'phone' => '08123456789',
            'password' => bcrypt('password123'),
            'role' => 'CUSTOMER',
            'is_active' => true,
        ]);
        $this->customerToken = $this->customer->createToken('test-app')->plainTextToken;

        $this->cashier = User::create([
            'name' => 'Staff Kasir',
            'email' => 'cashier@club61.test',
            'phone' => '08123456780',
            'password' => bcrypt('password123'),
            'role' => 'CASHIER',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Venue Admin',
            'email' => 'admin@club61.test',
            'phone' => '08123456781',
            'password' => bcrypt('password123'),
            'role' => 'ADMIN',
            'is_active' => true,
        ]);

        $this->kitchen = User::create([
            'name' => 'Chef Kitchen',
            'email' => 'kitchen@club61.test',
            'phone' => '08123456782',
            'password' => bcrypt('password123'),
            'role' => 'KITCHEN',
            'is_active' => true,
        ]);

        $this->court = PadelCourt::create([
            'name' => 'Court 1 Grand Panoramic',
            'court_type' => 'INDOOR',
            'surface_type' => 'MONDO_SUPERCOURT',
            'hourly_rate_regular' => 200000.00,
            'hourly_rate_prime' => 300000.00,
            'is_active' => true,
        ]);
    }

    /**
     * Uji Celah 1: Simulator Endpoint Ditolak Pada Production
     */
    public function test_simulator_is_rejected_on_production_environment(): void
    {
        // Buat booking dummy
        $booking = PadelBooking::create([
            'booking_code' => 'BK-SIMULATE-1',
            'user_id' => $this->customer->id,
            'court_id' => $this->court->id,
            'booking_date' => now()->addDays(2)->format('Y-m-d'),
            'start_time' => now()->addDays(2)->setHour(10)->setMinute(0),
            'end_time' => now()->addDays(2)->setHour(11)->setMinute(0),
            'court_fee' => 200000.00,
            'total_amount' => 200000.00,
            'status' => 'PENDING_PAYMENT',
        ]);

        // Mock app environment ke production
        app()['env'] = 'production';

        $response = $this->postJson('/api/v1/payments/simulate', [
            'booking_id' => $booking->id,
        ]);

        // Di production harus ditolak (403 atau 404 karena rute tidak terdaftar)
        $this->assertTrue(in_array($response->status(), [403, 404]));
        $this->assertEquals('PENDING_PAYMENT', $booking->fresh()->status);

        // Kembalikan ke testing
        app()['env'] = 'testing';
    }

    /**
     * Uji Celah 2: Customer Checkout dengan CASH Wajib PENDING_PAYMENT (Bukan PAID)
     */
    public function test_customer_checkout_with_cash_is_rejected(): void
    {
        $date = now()->addDays(3)->format('Y-m-d');

        // Hold slot oleh customer
        $hold = $this->withHeader('Authorization', "Bearer {$this->customerToken}")
            ->postJson('/api/v1/padel/hold-slot', [
                'booking_date' => $date,
                'slots' => [
                    ['court_id' => $this->court->id, 'start_time' => '14:00', 'end_time' => '15:00'],
                ],
            ])
            ->assertStatus(201);

        $bookingId = $hold->json('data.bookings.0.id');

        // Venue 100% Cashless: checkout memilih CASH harus ditolak (validasi payment_method).
        $this->withHeader('Authorization', "Bearer {$this->customerToken}")
            ->withHeader('X-Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/padel/checkout', [
                'booking_ids' => [$bookingId],
                'payment_method' => 'CASH',
            ])
            ->assertStatus(422);

        $this->assertEquals('LOCKED', PadelBooking::find($bookingId)->status);
    }

    /**
     * Uji Celah 2: Staff Kasir / Admin di POS Checkout CASH Tetap Ditolak (100% Cashless)
     */
    public function test_staff_checkout_with_cash_at_pos_is_rejected(): void
    {
        \App\Models\Pos\PosCashierShift::create([
            'shift_number' => 'SHIFT-CASHIER-TEST',
            'counter' => 'PADEL_FRONTDESK',
            'status' => 'OPEN',
            'opened_by_id' => $this->cashier->id,
            'opened_at' => now(),
            'starting_cash' => 0.00,
            'expected_cash' => 0.00,
        ]);

        $date = now()->addDays(3)->format('Y-m-d');
        $cashierToken = $this->cashier->createToken('cashier-token')->plainTextToken;

        $hold = $this->withHeader('Authorization', "Bearer {$cashierToken}")
            ->postJson('/api/v1/padel/hold-slot', [
                'booking_date' => $date,
                'slots' => [
                    ['court_id' => $this->court->id, 'start_time' => '16:00', 'end_time' => '17:00'],
                ],
            ])
            ->assertStatus(201);

        $bookingId = $hold->json('data.bookings.0.id');

        // Kasir sekalipun TIDAK bisa checkout via CASH lagi — venue 100% Cashless tanpa pengecualian.
        $this->withHeader('Authorization', "Bearer {$cashierToken}")
            ->withHeader('X-Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/padel/checkout', [
                'booking_ids' => [$bookingId],
                'payment_method' => 'CASH',
            ])
            ->assertStatus(422);

        $this->assertEquals('LOCKED', PadelBooking::find($bookingId)->status);
    }

    /**
     * Uji Celah 3: /pos/check-in Menolak Tamu Tanpa Auth & Menolak Role Customer
     */
    public function test_pos_check_in_rejects_unauthenticated_and_customer_role(): void
    {
        $booking = PadelBooking::create([
            'booking_code' => 'BK-POS-AUTH-TEST',
            'user_id' => $this->customer->id,
            'court_id' => $this->court->id,
            'booking_date' => now()->format('Y-m-d'),
            'start_time' => now()->addMinutes(10),
            'end_time' => now()->addMinutes(70),
            'court_fee' => 200000.00,
            'total_amount' => 200000.00,
            'status' => 'PAID',
            'qr_code_hash' => 'hash_pos_auth',
        ]);

        // 1. Tanpa Login (Unauthenticated) -> Ditolak 401
        $resUnauth = $this->postJson('/pos/check-in', [
            'code' => 'BK-POS-AUTH-TEST',
        ]);
        $resUnauth->assertStatus(401);

        // 2. Login sebagai CUSTOMER -> Ditolak 403 Forbidden
        $resCustomer = $this->actingAs($this->customer)->postJson('/pos/check-in', [
            'code' => 'BK-POS-AUTH-TEST',
        ]);
        $resCustomer->assertStatus(403)
            ->assertJsonPath('success', false);

        // 3. Login sebagai CASHIER -> Diterima 200 OK
        $resCashier = $this->actingAs($this->cashier)->postJson('/pos/check-in', [
            'code' => 'BK-POS-AUTH-TEST',
        ]);
        $resCashier->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals('CHECKED_IN', $booking->fresh()->status);
    }

    /**
     * Uji Celah 4: Webhook POS Menolak Spoofing Tanpa Signature Valid (DRY)
     */
    public function test_pos_webhook_rejects_invalid_signature_and_accepts_valid(): void
    {
        Config::set('services.midtrans.server_key', 'test-secret-server-key-pos');

        $order = Order::create([
            'order_number' => 'ORD-POS-WEBHOOK-TEST',
            'user_id' => $this->customer->id,
            'subtotal' => 250000.00,
            'grand_total' => 250000.00,
            'payment_status' => 'UNPAID',
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'payment_gateway' => 'MIDTRANS',
            'transaction_id' => 'MID-ORDER-POS-1',
            'amount' => 250000.00,
            'status' => 'PENDING',
        ]);

        // 1. Spoofing dengan signature palsu -> Ditolak 400
        $fakeSignature = hash('sha512', 'fake-spoofed-data');
        $this->postJson('/api/v1/payments/webhook', [
            'order_id' => 'MID-ORDER-POS-1',
            'status_code' => '200',
            'gross_amount' => '250000',
            'signature_key' => $fakeSignature,
            'transaction_status' => 'settlement',
        ])->assertStatus(400)
            ->assertJsonPath('success', false);

        $this->assertEquals('UNPAID', $order->fresh()->payment_status);

        // 2. Signature Asli SHA-512 -> Diterima 200 dan Status Berubah Jadi PAID
        $validSignature = hash('sha512', 'MID-ORDER-POS-1' . '200' . '250000' . 'test-secret-server-key-pos');
        $this->postJson('/api/v1/payments/webhook', [
            'order_id' => 'MID-ORDER-POS-1',
            'status_code' => '200',
            'gross_amount' => '250000',
            'signature_key' => $validSignature,
            'transaction_status' => 'settlement',
        ])->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals('PAID', $order->fresh()->payment_status);
        $this->assertEquals('SUCCESS', $payment->fresh()->status);
    }

    /**
     * Uji Celah 5: Layar /pos dan /kitchen Dilindungi Middleware Auth & Role
     */
    public function test_pos_and_kitchen_screens_protected_from_public_and_wrong_role(): void
    {
        // 1. Guest akses /pos -> Redirect ke Login (302)
        $this->get('/pos')->assertRedirect('/login');

        // 2. Guest akses /kitchen -> Redirect ke Login (302)
        $this->get('/kitchen')->assertRedirect('/login');

        // 3. Customer akses /pos & /kitchen -> 403 Forbidden
        $this->actingAs($this->customer)->get('/pos')->assertStatus(403);
        $this->actingAs($this->customer)->get('/kitchen')->assertStatus(403);

        // 4. Kasir akses /pos -> 200 OK, tapi akses /kitchen -> 403 Forbidden
        $this->actingAs($this->cashier)->get('/pos')->assertStatus(200);
        $this->actingAs($this->cashier)->get('/kitchen')->assertStatus(403);

        // 5. Staf Kitchen akses /kitchen -> 200 OK, tapi akses /pos -> 403 Forbidden
        $this->actingAs($this->kitchen)->get('/kitchen')->assertStatus(200);
        $this->actingAs($this->kitchen)->get('/pos')->assertStatus(403);
    }
}
