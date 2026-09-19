<?php

namespace Tests\Feature;

use App\Exceptions\SlotConflictException;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\Pos\PosCashierShift;
use App\Models\Pos\Refund;
use App\Models\Role;
use App\Models\User;
use App\Services\Padel\Handlers\PadelFulfillmentHandler;
use App\Services\Padel\PadelBookingService;
use App\Services\Payment\PaymentOrchestratorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PaymentOrchestrationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $cashier;
    protected User $unauthorizedStaff;
    protected PadelCourt $court1;
    protected PadelCourt $court2;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.midtrans.server_key' => 'SB-Mid-server-TEST-KEY']);

        // Inisialisasi Role & Permission
        $roleCustomer = Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
        $roleCashier = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);
        $roleKitchen = Role::firstOrCreate(['name' => 'kitchen', 'guard_name' => 'web']);

        $permWalkIn = Permission::firstOrCreate(['name' => 'process_walkin_booking', 'guard_name' => 'web']);
        $permView = Permission::firstOrCreate(['name' => 'View:BookOfflineCourt', 'guard_name' => 'web']);

        $roleCashier->givePermissionTo([$permWalkIn, $permView]);

        $password = Hash::make('password123');

        $this->customer = User::create([
            'name' => 'Budi Customer',
            'email' => 'budi@test.com',
            'phone' => '08123456789',
            'password' => $password,
            'is_active' => true,
        ]);
        $this->customer->assignRole('customer');

        $this->cashier = User::create([
            'name' => 'Siti Kasir',
            'email' => 'siti@test.com',
            'phone' => '08123456780',
            'password' => $password,
            'is_active' => true,
        ]);
        $this->cashier->assignRole('cashier');

        $this->unauthorizedStaff = User::create([
            'name' => 'Dapur User',
            'email' => 'dapur@test.com',
            'phone' => '08123456781',
            'password' => $password,
            'is_active' => true,
        ]);
        $this->unauthorizedStaff->assignRole('kitchen');

        $this->court1 = PadelCourt::create([
            'name' => 'Court Alpha 1',
            'type' => 'INDOOR',
            'hourly_rate_regular' => 150000,
            'hourly_rate_prime' => 180000,
            'is_active' => true,
        ]);

        $this->court2 = PadelCourt::create([
            'name' => 'Court Beta 2',
            'type' => 'OUTDOOR',
            'hourly_rate_regular' => 120000,
            'hourly_rate_prime' => 150000,
            'is_active' => true,
        ]);
    }

    /**
     * 1. Webhook Midtrans melalui /v1/padel/webhook/midtrans melewati orchestrator dan menyelesaikan order secara idempoten.
     */
    public function test_real_midtrans_webhook_route_processes_through_orchestrator(): void
    {
        $booking = PadelBooking::create([
            'booking_code' => 'PB-TEST-INT01',
            'user_id' => $this->customer->id,
            'court_id' => $this->court1->id,
            'booking_date' => now()->addDays(3)->format('Y-m-d'),
            'start_time' => Carbon::parse(now()->addDays(3)->format('Y-m-d') . ' 08:00'),
            'end_time' => Carbon::parse(now()->addDays(3)->format('Y-m-d') . ' 09:00'),
            'court_fee' => 150000,
            'total_amount' => 150000,
            'status' => 'PENDING_PAYMENT',
        ]);

        $orderId = 'ORD-PAD-INT888';
        Cache::put("order_bookings:{$orderId}", [$booking->id], 3600);

        $grossAmount = '150000.00';
        $statusCode = '200';
        $signature = hash('sha512', $orderId . $statusCode . $grossAmount . 'SB-Mid-server-TEST-KEY');

        $response = $this->postJson('/api/v1/padel/webhook/midtrans', [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'payment_type' => 'qris',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('padel_bookings', [
            'id' => $booking->id,
            'status' => 'PAID',
        ]);

        $this->assertDatabaseHas('payments', [
            'transaction_id' => $orderId,
            'payment_gateway' => 'MIDTRANS',
            'status' => 'SUCCESS',
        ]);

        $refreshedBooking = $booking->fresh();
        $this->assertNotNull($refreshedBooking->qr_code_hash);
    }

    /**
     * 2. Late settlement Midtrans pada pesanan yang sudah CANCELLED mencatat Refund PENDING dan tidak mengaktifkan slot booking.
     */
    public function test_midtrans_late_settlement_on_cancelled_order_creates_pending_refund_without_activating_slot(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-LATE-CANCELLED-01',
            'user_id' => $this->customer->id,
            'order_type' => 'ONLINE_BOOKING',
            'subtotal' => 150000,
            'grand_total' => 150000,
            'payment_status' => 'CANCELLED',
        ]);

        $booking = PadelBooking::create([
            'booking_code' => 'PB-LATE-01',
            'order_id' => $order->id,
            'user_id' => $this->customer->id,
            'court_id' => $this->court1->id,
            'booking_date' => now()->addDays(4)->format('Y-m-d'),
            'start_time' => Carbon::parse(now()->addDays(4)->format('Y-m-d') . ' 10:00'),
            'end_time' => Carbon::parse(now()->addDays(4)->format('Y-m-d') . ' 11:00'),
            'court_fee' => 150000,
            'total_amount' => 150000,
            'status' => 'CANCELLED',
        ]);

        $orderId = $order->order_number;
        $grossAmount = '150000.00';
        $statusCode = '200';
        $signature = hash('sha512', $orderId . $statusCode . $grossAmount . 'SB-Mid-server-TEST-KEY');

        $response = $this->postJson('/api/v1/padel/webhook/midtrans', [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        // Finansial tercatat lunas dan dibuatkan refund
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'PAID',
        ]);

        $this->assertDatabaseHas('refunds', [
            'order_id' => $order->id,
            'refund_amount' => 150000,
            'status' => 'PENDING',
        ]);

        // Slot booking TETAP CANCELLED (tidak hidup kembali)
        $this->assertEquals('CANCELLED', $booking->fresh()->status);
    }

    /**
     * 3. Settle kasir manual memproses pelunasan via orchestrator dengan gateway CASHIER_POS.
     */
    public function test_cashier_settle_manual_uses_orchestrator(): void
    {
        $booking = PadelBooking::create([
            'booking_code' => 'PB-CASHIER-SETTLE-01',
            'user_id' => $this->customer->id,
            'court_id' => $this->court1->id,
            'booking_date' => now()->addDays(2)->format('Y-m-d'),
            'start_time' => Carbon::parse(now()->addDays(2)->format('Y-m-d') . ' 14:00'),
            'end_time' => Carbon::parse(now()->addDays(2)->format('Y-m-d') . ' 15:00'),
            'court_fee' => 150000,
            'total_amount' => 150000,
            'status' => 'PENDING_PAYMENT',
        ]);

        $shift = PosCashierShift::create([
            'shift_number' => 'SFT-PADEL-' . now()->format('Ymd') . '-0001',
            'counter' => 'PADEL_FRONTDESK',
            'status' => 'OPEN',
            'opened_by_id' => $this->cashier->id,
            'opened_at' => now(),
            'starting_cash' => 200000,
            'expected_cash' => 200000,
        ]);

        $service = app(PadelBookingService::class);
        $result = $service->adminSettleCashierPayment(
            bookingId: $booking->id,
            paymentMethod: 'CASH',
            amountReceived: 150000,
            cashierUser: $this->cashier
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('PAID', $result['booking']->status);
        $this->assertNotNull($result['booking']->qr_code_hash);

        $this->assertDatabaseHas('payments', [
            'order_id' => $result['booking']->order_id,
            'pos_shift_id' => $shift->id,
            'payment_gateway' => 'CASHIER_POS',
            'payment_method' => 'CASH',
            'status' => 'SUCCESS',
        ]);
    }

    /**
     * 4. Modul reschedule menolak pemindahan ke jam yang berstatus PENDING_PAYMENT.
     */
    public function test_reschedule_checks_and_rejects_slot_occupied_by_pending_payment(): void
    {
        $targetDate = now()->addDays(3)->format('Y-m-d');

        // Slot target sedang ditahan customer lain (PENDING_PAYMENT)
        PadelBooking::create([
            'booking_code' => 'PB-OCCUPIED-01',
            'user_id' => $this->customer->id,
            'court_id' => $this->court2->id,
            'booking_date' => $targetDate,
            'start_time' => Carbon::parse("{$targetDate} 16:00"),
            'end_time' => Carbon::parse("{$targetDate} 17:00"),
            'court_fee' => 120000,
            'total_amount' => 120000,
            'status' => 'PENDING_PAYMENT',
        ]);

        // Booking yang akan di-reschedule
        $myBooking = PadelBooking::create([
            'booking_code' => 'PB-MY-BOOKING-01',
            'user_id' => $this->customer->id,
            'court_id' => $this->court1->id,
            'booking_date' => $targetDate,
            'start_time' => Carbon::parse("{$targetDate} 08:00"),
            'end_time' => Carbon::parse("{$targetDate} 09:00"),
            'court_fee' => 150000,
            'total_amount' => 150000,
            'status' => 'PAID',
        ]);

        $service = app(PadelBookingService::class);

        $this->expectException(SlotConflictException::class);

        $service->adminRescheduleBooking(
            bookingId: $myBooking->id,
            newCourtId: $this->court2->id,
            newDate: $targetDate,
            newStartTimeStr: '16:00',
            reason: 'Pindah jam',
            adminUser: $this->cashier
        );
    }

    /**
     * 5. releaseSlots() tidak membatalkan booking yang sudah berstatus PAID.
     */
    public function test_release_slots_does_not_cancel_already_paid_booking_or_order(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-RELEASE-PAID-01',
            'user_id' => $this->customer->id,
            'order_type' => 'ONLINE_BOOKING',
            'subtotal' => 150000,
            'grand_total' => 150000,
            'payment_status' => 'PAID',
        ]);

        $booking = PadelBooking::create([
            'booking_code' => 'PB-RELEASE-PAID-01',
            'order_id' => $order->id,
            'user_id' => $this->customer->id,
            'court_id' => $this->court1->id,
            'booking_date' => now()->addDays(2)->format('Y-m-d'),
            'start_time' => Carbon::parse(now()->addDays(2)->format('Y-m-d') . ' 18:00'),
            'end_time' => Carbon::parse(now()->addDays(2)->format('Y-m-d') . ' 19:00'),
            'court_fee' => 150000,
            'total_amount' => 150000,
            'status' => 'PAID',
        ]);

        $service = app(PadelBookingService::class);
        $count = $service->releaseSlots([$booking->id], $this->customer);

        $this->assertEquals(0, $count);
        $this->assertEquals('PAID', $booking->fresh()->status);
        $this->assertEquals('PAID', $order->fresh()->payment_status);
    }

    /**
     * 6. PadelFulfillmentHandler atomic conditional update menjaga booking CANCELLED agar tidak tertimpa menjadi PAID.
     */
    public function test_padel_fulfillment_handler_atomic_guard_preserves_cancelled_booking(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-ATOMIC-GUARD-01',
            'user_id' => $this->customer->id,
            'order_type' => 'ONLINE_BOOKING',
            'subtotal' => 150000,
            'grand_total' => 150000,
            'payment_status' => 'PAID',
        ]);

        $booking = PadelBooking::create([
            'booking_code' => 'PB-ATOMIC-GUARD-01',
            'order_id' => $order->id,
            'user_id' => $this->customer->id,
            'court_id' => $this->court1->id,
            'booking_date' => now()->addDays(2)->format('Y-m-d'),
            'start_time' => Carbon::parse(now()->addDays(2)->format('Y-m-d') . ' 20:00'),
            'end_time' => Carbon::parse(now()->addDays(2)->format('Y-m-d') . ' 21:00'),
            'court_fee' => 150000,
            'total_amount' => 150000,
            'status' => 'CANCELLED',
        ]);

        $handler = app(PadelFulfillmentHandler::class);
        $handler->fulfill($order, collect());

        $this->assertEquals('CANCELLED', $booking->fresh()->status);
    }

    /**
     * 7. Staf tanpa izin process_walkin_booking ditolak saat submitWalkInBooking.
     */
    public function test_submit_walkin_booking_rejects_unauthorized_staff_without_permission(): void
    {
        $this->actingAs($this->unauthorizedStaff);

        $page = new \App\Filament\Pages\BookOfflineCourt();
        $page->bookingDate = now()->addDays(1)->format('Y-m-d');
        $page->selectedSlots = [
            '0800' => [
                'court_id' => $this->court1->id,
                'court_name' => $this->court1->name,
                'start_time' => '08:00',
                'end_time' => '09:00',
                'time_label' => '08:00 - 09:00',
                'price' => 150000,
            ],
        ];
        $page->customerMode = 'quick_create';
        $page->walkInName = 'Pelanggan Walkin';
        $page->walkInPhone = '081299998888';

        $this->expectException(HttpException::class);

        $page->submitWalkInBooking(app(PadelBookingService::class));
    }
}
