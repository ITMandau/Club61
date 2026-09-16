<?php

namespace Tests\Feature\Payment;

use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\Pos\Refund;
use App\Models\User;
use App\Services\Padel\PadelBookingService;
use App\Services\Payment\MidtransService;
use App\Services\Payment\PaymentOrchestratorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PendingPaymentSlotProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $userA;
    protected User $userB;
    protected PadelCourt $court;
    protected string $bookingDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userA = User::create([
            'name' => 'Player A',
            'email' => 'player_a@club61.test',
            'phone' => '08111111111',
            'password' => bcrypt('password123'),
            'role' => 'CUSTOMER',
            'is_active' => true,
        ]);

        $this->userB = User::create([
            'name' => 'Player B',
            'email' => 'player_b@club61.test',
            'phone' => '08222222222',
            'password' => bcrypt('password123'),
            'role' => 'CUSTOMER',
            'is_active' => true,
        ]);

        $this->court = PadelCourt::create([
            'name' => 'Court Panoramic Central',
            'court_type' => 'INDOOR',
            'surface_type' => 'MONDO_SUPERCOURT',
            'hourly_rate_regular' => 200000.00,
            'hourly_rate_prime' => 300000.00,
            'is_active' => true,
        ]);

        $this->bookingDate = now()->addDays(3)->format('Y-m-d');
    }

    /**
     * 1. Slot dengan status PENDING_PAYMENT tidak boleh bisa di-hold oleh pemain lain (Conflict 409).
     */
    public function test_hold_batch_slots_rejects_booking_if_slot_is_pending_payment(): void
    {
        $startTime = '10:00';
        $endTime = '11:00';

        // User A holds and checks out a slot via CASH (customer cash checkout transitions status to PENDING_PAYMENT)
        $hold = $this->actingAs($this->userA, 'sanctum')
            ->postJson('/api/v1/padel/hold-slot', [
                'booking_date' => $this->bookingDate,
                'slots' => [
                    ['court_id' => $this->court->id, 'start_time' => $startTime, 'end_time' => $endTime],
                ],
            ])
            ->assertStatus(201);

        $bookingId = $hold->json('data.bookings.0.id');

        $this->actingAs($this->userA, 'sanctum')
            ->withHeader('X-Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/padel/checkout', [
                'booking_ids' => [$bookingId],
                'payment_method' => 'CASH',
            ])
            ->assertStatus(200);

        $booking = PadelBooking::find($bookingId);
        $this->assertEquals('PENDING_PAYMENT', $booking->status);

        // User B attempts to claim the exact same slot while User A is in pending payment
        $response = $this->actingAs($this->userB, 'sanctum')
            ->postJson('/api/v1/padel/hold-slot', [
                'booking_date' => $this->bookingDate,
                'slots' => [
                    ['court_id' => $this->court->id, 'start_time' => $startTime, 'end_time' => $endTime],
                ],
            ]);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * 2. Schedule matrix merender slot PENDING_PAYMENT sebagai LOCKED (bukan BOOKED atau AVAILABLE).
     */
    public function test_schedule_matrix_renders_pending_payment_slots_as_locked(): void
    {
        $startPending = Carbon::parse("{$this->bookingDate} 14:00:00");
        $endPending = Carbon::parse("{$this->bookingDate} 15:00:00");

        $startPaid = Carbon::parse("{$this->bookingDate} 16:00:00");
        $endPaid = Carbon::parse("{$this->bookingDate} 17:00:00");

        PadelBooking::create([
            'booking_code' => 'BK-PENDING-1',
            'user_id' => $this->userA->id,
            'court_id' => $this->court->id,
            'booking_date' => $this->bookingDate,
            'start_time' => $startPending,
            'end_time' => $endPending,
            'court_fee' => 200000.00,
            'total_amount' => 200000.00,
            'status' => 'PENDING_PAYMENT',
            'qr_code_hash' => hash('sha256', 'BK-PENDING-1'),
        ]);

        PadelBooking::create([
            'booking_code' => 'BK-PAID-1',
            'user_id' => $this->userB->id,
            'court_id' => $this->court->id,
            'booking_date' => $this->bookingDate,
            'start_time' => $startPaid,
            'end_time' => $endPaid,
            'court_fee' => 200000.00,
            'total_amount' => 200000.00,
            'status' => 'PAID',
            'qr_code_hash' => hash('sha256', 'BK-PAID-1'),
        ]);

        $response = $this->getJson("/api/v1/padel/schedule?date={$this->bookingDate}")
            ->assertStatus(200);

        $courtsData = $response->json('data.courts');
        $this->assertNotEmpty($courtsData);

        $courtSlots = collect($courtsData)->firstWhere('court_id', $this->court->id)['slots'] ?? [];
        $slot14 = collect($courtSlots)->firstWhere('local_start', '14:00');
        $slot16 = collect($courtSlots)->firstWhere('local_start', '16:00');

        $this->assertNotNull($slot14);
        $this->assertEquals('LOCKED', $slot14['status']);

        $this->assertNotNull($slot16);
        $this->assertEquals('BOOKED', $slot16['status']);
    }

    /**
     * 3. Pembatalan sukarela (release-slot) membatalkan booking PENDING_PAYMENT,
     * mengubah order status menjadi CANCELLED, dan membuka kembali slot untuk pemain lain.
     */
    public function test_voluntary_release_cancels_pending_payment_booking_and_frees_slot(): void
    {
        $startTime = '09:00';
        $endTime = '10:00';

        $hold = $this->actingAs($this->userA, 'sanctum')
            ->postJson('/api/v1/padel/hold-slot', [
                'booking_date' => $this->bookingDate,
                'slots' => [
                    ['court_id' => $this->court->id, 'start_time' => $startTime, 'end_time' => $endTime],
                ],
            ])
            ->assertStatus(201);

        $bookingId = $hold->json('data.bookings.0.id');

        $checkout = $this->actingAs($this->userA, 'sanctum')
            ->withHeader('X-Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/padel/checkout', [
                'booking_ids' => [$bookingId],
                'payment_method' => 'CASH',
            ])
            ->assertStatus(200);

        $orderNumber = $checkout->json('data.order_id');
        $this->assertNotNull($orderNumber);

        // User A releases / cancels voluntarily
        $releaseRes = $this->actingAs($this->userA, 'sanctum')
            ->postJson('/api/v1/padel/release-slot', [
                'booking_ids' => [$bookingId],
            ]);

        $releaseRes->assertStatus(200)
            ->assertJson(['success' => true]);

        $booking = PadelBooking::find($bookingId);
        $this->assertEquals('CANCELLED', $booking->status);

        $order = Order::where('order_number', $orderNumber)->first();
        $this->assertNotNull($order);
        $this->assertEquals('CANCELLED', $order->payment_status);

        // User B should now be able to hold this exact slot immediately
        $holdUserB = $this->actingAs($this->userB, 'sanctum')
            ->postJson('/api/v1/padel/hold-slot', [
                'booking_date' => $this->bookingDate,
                'slots' => [
                    ['court_id' => $this->court->id, 'start_time' => $startTime, 'end_time' => $endTime],
                ],
            ]);

        $holdUserB->assertStatus(201)
            ->assertJson(['success' => true]);
    }

    /**
     * 4. releaseExpiredLocks membersihkan PENDING_PAYMENT yang melewati 15 menit,
     * serta mengubah order payment_status menjadi CANCELLED.
     */
    public function test_release_expired_locks_cancels_stale_pending_payment_bookings_over_15_minutes(): void
    {
        $orderStale = Order::create([
            'order_number' => 'ORD-STALE-15M',
            'user_id' => $this->userA->id,
            'cashier_id' => $this->userA->id,
            'subtotal' => 200000.00,
            'grand_total' => 200000.00,
            'payment_status' => 'UNPAID',
            'order_type' => 'ONLINE',
        ]);

        $bookingStale = PadelBooking::create([
            'booking_code' => 'BK-STALE-1',
            'order_id' => $orderStale->id,
            'user_id' => $this->userA->id,
            'court_id' => $this->court->id,
            'booking_date' => $this->bookingDate,
            'start_time' => Carbon::parse("{$this->bookingDate} 11:00:00"),
            'end_time' => Carbon::parse("{$this->bookingDate} 12:00:00"),
            'court_fee' => 200000.00,
            'total_amount' => 200000.00,
            'status' => 'PENDING_PAYMENT',
            'qr_code_hash' => hash('sha256', 'BK-STALE-1'),
        ]);

        // Explicitly backdate created_at directly in database to simulate elapsed time > 15 minutes
        DB::table('padel_bookings')->where('id', $bookingStale->id)->update([
            'created_at' => now()->subMinutes(16),
        ]);

        $orderFresh = Order::create([
            'order_number' => 'ORD-FRESH-5M',
            'user_id' => $this->userB->id,
            'cashier_id' => $this->userB->id,
            'subtotal' => 200000.00,
            'grand_total' => 200000.00,
            'payment_status' => 'UNPAID',
            'order_type' => 'ONLINE',
        ]);

        $bookingFresh = PadelBooking::create([
            'booking_code' => 'BK-FRESH-1',
            'order_id' => $orderFresh->id,
            'user_id' => $this->userB->id,
            'court_id' => $this->court->id,
            'booking_date' => $this->bookingDate,
            'start_time' => Carbon::parse("{$this->bookingDate} 13:00:00"),
            'end_time' => Carbon::parse("{$this->bookingDate} 14:00:00"),
            'court_fee' => 200000.00,
            'total_amount' => 200000.00,
            'status' => 'PENDING_PAYMENT',
            'qr_code_hash' => hash('sha256', 'BK-FRESH-1'),
        ]);

        DB::table('padel_bookings')->where('id', $bookingFresh->id)->update([
            'created_at' => now()->subMinutes(5),
        ]);

        $service = app(PadelBookingService::class);
        $expiredCount = $service->releaseExpiredLocks();

        $this->assertGreaterThanOrEqual(1, $expiredCount);

        // Stale booking is expired and its order cancelled
        $bookingStale->refresh();
        $orderStale->refresh();
        $this->assertEquals('EXPIRED', $bookingStale->status);
        $this->assertEquals('CANCELLED', $orderStale->payment_status);

        // Fresh booking remains untouched
        $bookingFresh->refresh();
        $orderFresh->refresh();
        $this->assertEquals('PENDING_PAYMENT', $bookingFresh->status);
        $this->assertEquals('UNPAID', $orderFresh->payment_status);
    }

    /**
     * 5. Proteksi Late Settlement: Jika uang customer baru masuk saat order sudah CANCELLED,
     * status pembayaran tetap SUCCESS, order di-set PAID, dibuat record Refund PENDING,
     * dan slot booking TIDAK diaktifkan kembali.
     */
    public function test_late_settlement_on_cancelled_order_creates_refund_and_skips_fulfillment(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-CANCELLED-LATE',
            'user_id' => $this->userA->id,
            'cashier_id' => $this->userA->id,
            'subtotal' => 200000.00,
            'grand_total' => 200000.00,
            'payment_status' => 'CANCELLED',
            'order_type' => 'ONLINE',
        ]);

        $booking = PadelBooking::create([
            'booking_code' => 'BK-CANCELLED-1',
            'order_id' => $order->id,
            'user_id' => $this->userA->id,
            'court_id' => $this->court->id,
            'booking_date' => $this->bookingDate,
            'start_time' => Carbon::parse("{$this->bookingDate} 15:00:00"),
            'end_time' => Carbon::parse("{$this->bookingDate} 16:00:00"),
            'court_fee' => 200000.00,
            'total_amount' => 200000.00,
            'status' => 'CANCELLED',
            'qr_code_hash' => hash('sha256', 'BK-CANCELLED-1'),
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'payment_gateway' => 'MIDTRANS',
            'transaction_id' => 'TRX-LATE-001',
            'payment_method' => 'QRIS',
            'amount' => 200000.00,
            'status' => 'PENDING',
        ]);

        $orchestrator = app(PaymentOrchestratorService::class);
        $orchestrator->markOrderAsPaid($order, [
            'transaction_id' => 'TRX-LATE-001',
            'payment_gateway' => 'MIDTRANS',
            'payment_method' => 'QRIS',
            'amount' => 200000.00,
        ]);

        $order->refresh();
        $payment->refresh();
        $booking->refresh();

        // 1. Order financial status is recorded as PAID because money was received
        $this->assertEquals('PAID', $order->payment_status);

        // 2. Payment is recorded as SUCCESS
        $this->assertEquals('SUCCESS', $payment->status);

        // 3. A Refund record is created with status PENDING for staff to process
        $refund = Refund::where('order_id', $order->id)->first();
        $this->assertNotNull($refund);
        $this->assertEquals('PENDING', $refund->status);
        $this->assertEquals(200000.00, (float) $refund->refund_amount);

        // 4. Booking slot is NOT reactivated, protecting against double booking
        $this->assertEquals('CANCELLED', $booking->status);
    }
}
