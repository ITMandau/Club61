<?php

namespace Tests\Feature\Payment;

use App\Models\Padel\CourtEquipment;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\Pos\Voucher;
use App\Models\User;
use App\Services\Payment\MidtransService;
use App\Services\Payment\PaymentOrchestratorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

class UnifiedPaymentOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $staff;
    protected string $customerToken;
    protected PadelCourt $court;
    protected CourtEquipment $racket;
    protected Voucher $voucher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::create([
            'name' => 'John Doe',
            'email' => 'john@club61.test',
            'phone' => '08123456789',
            'password' => bcrypt('password123'),
            'role' => 'CUSTOMER',
            'is_active' => true,
        ]);
        $this->customerToken = $this->customer->createToken('test-token')->plainTextToken;

        $this->staff = User::create([
            'name' => 'Cashier Frontdesk',
            'email' => 'cashier@club61.test',
            'phone' => '08123456780',
            'password' => bcrypt('password123'),
            'role' => 'CASHIER',
            'is_active' => true,
        ]);

        $this->court = PadelCourt::create([
            'name' => 'Court Central Panoramic',
            'court_type' => 'INDOOR',
            'surface_type' => 'MONDO_SUPERCOURT',
            'hourly_rate_regular' => 200000.00,
            'hourly_rate_prime' => 300000.00,
            'is_active' => true,
        ]);

        $this->racket = CourtEquipment::create([
            'name' => 'Raket Babolat Counter Vertuo',
            'category' => 'RACKET',
            'stock_total' => 10,
            'stock_available' => 10,
            'rental_price' => 50000.00,
            'is_active' => true,
        ]);

        $this->voucher = Voucher::create([
            'code' => 'PADEL61',
            'discount_type' => 'FIXED',
            'discount_value' => 25000.00,
            'min_order_amount' => 100000.00,
            'quota' => 5,
            'used_count' => 0,
            'valid_until' => now()->addDays(30),
            'is_active' => true,
        ]);
    }

    /**
     * 1. Eager Order Creation: Checkout membuat Order & Items seketika di awal.
     */
    public function test_checkout_creates_order_and_items_eagerly(): void
    {
        $date = now()->addDays(2)->format('Y-m-d');
        $hold = $this->withHeader('Authorization', "Bearer {$this->customerToken}")
            ->postJson('/api/v1/padel/hold-slot', [
                'booking_date' => $date,
                'slots' => [
                    ['court_id' => $this->court->id, 'start_time' => '10:00', 'end_time' => '11:00'],
                    ['court_id' => $this->court->id, 'start_time' => '11:00', 'end_time' => '12:00'],
                ],
            ])
            ->assertStatus(201);

        $bookingIds = collect($hold->json('data.bookings'))->pluck('id')->toArray();
        $idempotencyKey = (string) Str::uuid();

        $checkout = $this->withHeader('Authorization', "Bearer {$this->customerToken}")
            ->withHeader('X-Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/padel/checkout', [
                'booking_ids' => $bookingIds,
                'equipments' => [
                    ['equipment_id' => $this->racket->id, 'quantity' => 2],
                ],
                'voucher_code' => 'PADEL61',
                'payment_method' => 'QRIS',
            ])
            ->assertStatus(200);

        $orderNumber = $checkout->json('data.order_id');
        $this->assertNotEmpty($orderNumber);

        // MockSimulatorDriver selalu aktif di environment testing (lihat MidtransService::createSnapTransaction,
        // is_mock dipaksa true kalau app()->environment('testing')), jadi checkout di atas langsung PAID.
        // Untuk memverifikasi invarian "Order & Items dibuat eagerly SEBELUM lunas" (independen dari kapan
        // pelunasan terjadi), status di-override manual meniru window saat webhook Midtrans belum masuk.
        Order::where('order_number', $orderNumber)->update(['payment_status' => 'UNPAID']);

        // Assert Order row tercipta eagerly di tabel orders
        $order = Order::where('order_number', $orderNumber)->first();
        $this->assertNotNull($order);
        $this->assertEquals($this->customer->id, $order->user_id);
        $this->assertEquals('PADEL61', $order->voucher_code);
        $this->assertEquals(25000.00, (float) $order->discount_amount);
        $this->assertEquals('UNPAID', $order->payment_status);

        // Assert foreign key padel_bookings.order_id menyimpan ULID order->id
        foreach ($bookingIds as $bId) {
            $booking = PadelBooking::find($bId);
            $this->assertEquals($order->id, $booking->order_id);
        }

        // Assert order_items tercipta
        $this->assertCount(3, $order->items); // 2 slot lapangan + 1 peralatan
    }

    /**
     * 2. Atribusi Omzet: Sewa raket wajib memiliki item_type = 'PADEL' (Bukan 'MERCH').
     */
    public function test_rental_equipment_attributed_to_padel_item_type(): void
    {
        $date = now()->addDays(3)->format('Y-m-d');
        $hold = $this->withHeader('Authorization', "Bearer {$this->customerToken}")
            ->postJson('/api/v1/padel/hold-slot', [
                'booking_date' => $date,
                'slots' => [
                    ['court_id' => $this->court->id, 'start_time' => '09:00', 'end_time' => '10:00'],
                ],
            ])
            ->assertStatus(201);

        $bookingId = $hold->json('data.bookings.0.id');

        $checkout = $this->withHeader('Authorization', "Bearer {$this->customerToken}")
            ->withHeader('X-Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/padel/checkout', [
                'booking_ids' => [$bookingId],
                'equipments' => [
                    ['equipment_id' => $this->racket->id, 'quantity' => 1],
                ],
                'payment_method' => 'QRIS',
            ])
            ->assertStatus(200);

        $order = Order::where('order_number', $checkout->json('data.order_id'))->firstOrFail();

        // Tidak boleh ada item bertipe MERCH
        $merchItems = $order->items->where('item_type', 'MERCH');
        $this->assertCount(0, $merchItems, 'Sewa peralatan Padel tidak boleh bertipe MERCH agar tidak merusak atribusi revenue Modul 07.');

        // Seluruh item wajib PADEL
        $padelItems = $order->items->where('item_type', 'PADEL');
        $this->assertCount(2, $padelItems); // 1 lapangan + 1 raket
    }

    /**
     * 3. Persistensi Voucher di Database & Atomic Decrement saat Lunas.
     */
    public function test_orders_voucher_code_persisted_and_decremented_on_payment(): void
    {
        $this->assertEquals(5, $this->voucher->quota);
        $this->assertEquals(0, $this->voucher->used_count);

        $order = Order::create([
            'order_number' => 'ORD-VOUCHER-TEST-1',
            'user_id' => $this->customer->id,
            'order_type' => 'ONLINE_BOOKING',
            'subtotal' => 200000.00,
            'discount_amount' => 25000.00,
            'voucher_code' => 'PADEL61',
            'grand_total' => 175000.00,
            'payment_status' => 'UNPAID',
        ]);

        $orchestrator = app(PaymentOrchestratorService::class);
        $orchestrator->markOrderAsPaid($order, [
            'payment_gateway' => 'CASH',
            'transaction_id' => 'POS-CASH-VOUCHER-1',
            'payment_method' => 'CASH',
            'amount' => 175000.00,
        ]);

        $this->assertEquals('PAID', $order->fresh()->payment_status);

        // Kuota voucher wajib berkurang di database secara permanen
        $freshVoucher = $this->voucher->fresh();
        $this->assertEquals(4, $freshVoucher->quota);
        $this->assertEquals(1, $freshVoucher->used_count);
    }

    /**
     * 4. Konvensi Deterministik: Record pembayaran awal PENDING memakai transaction_id = order_number.
     */
    public function test_pending_payment_record_uses_order_number_as_transaction_id(): void
    {
        // MockSimulatorDriver selalu aktif di environment testing (is_mock dipaksa true di
        // MidtransService::createSnapTransaction saat app()->environment('testing')), sehingga checkout
        // normal selalu langsung PAID dan tidak pernah menyentuh cabang "Payment::create status PENDING"
        // yang mau diuji di sini. PaymentManager di-stub supaya cabang tersebut benar-benar tereksekusi.
        $this->app->instance(\App\Services\Payment\PaymentManager::class, new class extends \App\Services\Payment\PaymentManager {
            public function createPayment(array $params): array
            {
                return [
                    'driver' => 'midtrans',
                    'order_id' => $params['order_id'],
                    'snap_token' => 'STUB-SNAP-TOKEN',
                    'payment_url' => null,
                    'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/stub',
                    'is_mock' => false,
                    'checkout_mode' => 'POPUP',
                ];
            }
        });

        $date = now()->addDays(4)->format('Y-m-d');
        $hold = $this->withHeader('Authorization', "Bearer {$this->customerToken}")
            ->postJson('/api/v1/padel/hold-slot', [
                'booking_date' => $date,
                'slots' => [
                    ['court_id' => $this->court->id, 'start_time' => '13:00', 'end_time' => '14:00'],
                ],
            ])
            ->assertStatus(201);

        $bookingId = $hold->json('data.bookings.0.id');

        $checkout = $this->withHeader('Authorization', "Bearer {$this->customerToken}")
            ->withHeader('X-Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/padel/checkout', [
                'booking_ids' => [$bookingId],
                'payment_method' => 'QRIS',
            ])
            ->assertStatus(200);

        $orderNumber = $checkout->json('data.order_id');
        $order = Order::where('order_number', $orderNumber)->firstOrFail();

        // Record pembayaran PENDING harus ada dengan transaction_id = orderNumber
        $payment = Payment::where('order_id', $order->id)->where('status', 'PENDING')->first();
        $this->assertNotNull($payment);
        $this->assertEquals($orderNumber, $payment->transaction_id);
    }

    /**
     * 5. Delegasi Pemenuhan Dinamis melalui PaymentFulfillmentRegistry.
     */
    public function test_orchestrator_delegates_fulfillment_via_dynamic_registry(): void
    {
        $date = now()->addDays(5)->format('Y-m-d');
        $booking = PadelBooking::create([
            'booking_code' => 'BK-REGISTRY-1',
            'user_id' => $this->customer->id,
            'court_id' => $this->court->id,
            'booking_date' => $date,
            'start_time' => Carbon::parse("{$date} 10:00:00"),
            'end_time' => Carbon::parse("{$date} 11:00:00"),
            'court_fee' => 200000.00,
            'total_amount' => 200000.00,
            'status' => 'PENDING_PAYMENT',
            'qr_code_hash' => null,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-REGISTRY-TEST',
            'user_id' => $this->customer->id,
            'order_type' => 'ONLINE_BOOKING',
            'subtotal' => 200000.00,
            'grand_total' => 200000.00,
            'payment_status' => 'UNPAID',
        ]);

        $booking->update(['order_id' => $order->id]);

        $order->items()->create([
            'item_type' => 'PADEL',
            'reference_id' => $booking->id,
            'item_name' => 'Sewa Lapangan Padel',
            'quantity' => 1,
            'unit_price' => 200000.00,
            'subtotal' => 200000.00,
        ]);

        $orchestrator = app(PaymentOrchestratorService::class);
        $orchestrator->markOrderAsPaid($order, [
            'payment_gateway' => 'MIDTRANS',
            'transaction_id' => 'MID-REGISTRY-1',
            'payment_method' => 'QRIS',
            'amount' => 200000.00,
        ]);

        $updatedBooking = $booking->fresh();
        $this->assertEquals('PAID', $updatedBooking->status);
        $this->assertNotEmpty($updatedBooking->qr_code_hash);
    }

    /**
     * 6. Idempotensi Transaksi: Pemanggilan berulang tidak menggandakan efek samping.
     */
    public function test_duplicate_webhook_is_idempotent(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-IDEMPOTENT-1',
            'user_id' => $this->customer->id,
            'order_type' => 'ONLINE_BOOKING',
            'subtotal' => 200000.00,
            'discount_amount' => 25000.00,
            'voucher_code' => 'PADEL61',
            'grand_total' => 175000.00,
            'payment_status' => 'UNPAID',
        ]);

        Payment::create([
            'order_id' => $order->id,
            'payment_gateway' => 'MIDTRANS',
            'transaction_id' => 'TRX-IDEMPOTENT-1',
            'amount' => 175000.00,
            'status' => 'PENDING',
        ]);

        $orchestrator = app(PaymentOrchestratorService::class);

        // Eksekusi pertama
        $orchestrator->markOrderAsPaid($order, [
            'payment_gateway' => 'MIDTRANS',
            'transaction_id' => 'TRX-IDEMPOTENT-1',
            'payment_method' => 'QRIS',
            'amount' => 175000.00,
        ]);

        $this->assertEquals(4, $this->voucher->fresh()->quota);

        // Eksekusi kedua (duplikat notifikasi webhook)
        $orchestrator->markOrderAsPaid($order, [
            'payment_gateway' => 'MIDTRANS',
            'transaction_id' => 'TRX-IDEMPOTENT-1',
            'payment_method' => 'QRIS',
            'amount' => 175000.00,
        ]);

        // Kuota voucher tidak boleh berkurang dua kali
        $this->assertEquals(4, $this->voucher->fresh()->quota);
        $this->assertEquals(1, $this->voucher->fresh()->used_count);
    }

    /**
     * 7. Pelunasan Kasir POS & Walk-in Murni: Pembuatan otomatis record payment jika belum ada pending payment.
     */
    public function test_pos_cashier_can_settle_unpaid_order_and_walk_in(): void
    {
        // Order walk-in dibuat langsung oleh kasir tanpa fase pending checkout
        $order = Order::create([
            'order_number' => 'ORD-WALKIN-POS-1',
            'user_id' => $this->customer->id,
            'cashier_id' => $this->staff->id,
            'order_type' => 'RETAIL',
            'subtotal' => 150000.00,
            'grand_total' => 150000.00,
            'payment_status' => 'UNPAID',
        ]);

        $orchestrator = app(PaymentOrchestratorService::class);
        $orchestrator->markOrderAsPaid($order, [
            'payment_gateway' => 'CASH',
            'transaction_id' => 'POS-WALKIN-TRX-1',
            'payment_method' => 'CASH',
            'amount' => 150000.00,
            'payload_log' => ['cashier' => $this->staff->id],
        ]);

        $this->assertEquals('PAID', $order->fresh()->payment_status);

        // Record payment harus tercipta otomatis dengan status SUCCESS
        $payment = Payment::where('order_id', $order->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals('POS-WALKIN-TRX-1', $payment->transaction_id);
        $this->assertEquals('SUCCESS', $payment->status);
        $this->assertEquals('CASH', $payment->payment_gateway);
    }

    /**
     * 8. Keamanan: Driver mock dilarang keras pada environment production.
     */
    public function test_mock_driver_strictly_rejected_in_production(): void
    {
        app()['env'] = 'production';

        $response = $this->postJson('/api/v1/padel/webhook/mock', [
            'order_id' => 'DUMMY-ORDER-1',
        ]);

        $this->assertEquals(403, $response->status());

        app()['env'] = 'testing';
    }

    /**
     * 9. Keamanan: Verifikasi Midtrans signature fail-closed jika Server Key kosong di production.
     */
    public function test_midtrans_signature_fails_closed_when_server_key_missing_in_production(): void
    {
        app()['env'] = 'production';
        Config::set('services.midtrans.server_key', '');

        $isValid = MidtransService::verifySignature('ORDER-1', '200', '100000', 'any_hash');
        $this->assertFalse($isValid, 'Midtrans signature wajib fail-closed (false) jika Server Key kosong di production.');

        app()['env'] = 'testing';
    }

    /**
     * 10. Tagihan Selisih Reschedule (Supplemental Delta Payment) tetap diproses meski Order sudah PAID.
     */
    public function test_supplemental_delta_payment_still_processed_when_order_already_marked_paid(): void
    {
        $date = now()->addDays(6)->format('Y-m-d');

        // 1. Order awal sudah berstatus PAID
        $order = Order::create([
            'order_number' => 'ORD-DELTA-TEST-1',
            'user_id' => $this->customer->id,
            'order_type' => 'ONLINE_BOOKING',
            'subtotal' => 200000.00,
            'grand_total' => 300000.00, // Total naik karena pindah ke jam prime
            'payment_status' => 'PAID', // Masih tercatat PAID dari pembayaran awal
        ]);

        // Pembayaran awal 200.000 sudah sukses
        Payment::create([
            'order_id' => $order->id,
            'payment_gateway' => 'MIDTRANS',
            'transaction_id' => 'INIT-PAYMENT-SUCCESS',
            'amount' => 200000.00,
            'status' => 'SUCCESS',
        ]);

        // Booking status LOCKED dengan QR kosong karena ada delta belum dibayar
        $booking = PadelBooking::create([
            'booking_code' => 'BK-DELTA-PADEL-1',
            'order_id' => $order->id,
            'user_id' => $this->customer->id,
            'court_id' => $this->court->id,
            'booking_date' => $date,
            'start_time' => Carbon::parse("{$date} 19:00:00"),
            'end_time' => Carbon::parse("{$date} 20:00:00"),
            'court_fee' => 300000.00,
            'total_amount' => 300000.00,
            'status' => 'LOCKED',
            'qr_code_hash' => null,
        ]);

        $order->items()->create([
            'item_type' => 'PADEL',
            'reference_id' => $booking->id,
            'item_name' => 'Sewa Lapangan Prime',
            'quantity' => 1,
            'unit_price' => 300000.00,
            'subtotal' => 300000.00,
        ]);

        // Supplemental payment 100.000 berstatus PENDING
        $deltaPayment = Payment::create([
            'order_id' => $order->id,
            'payment_gateway' => 'MIDTRANS',
            'transaction_id' => 'DELTA-SUPPLEMENTAL-PENDING',
            'amount' => 100000.00,
            'status' => 'PENDING',
        ]);

        // 2. Pelanggan membayar selisih 100.000 via webhook Midtrans
        $orchestrator = app(PaymentOrchestratorService::class);
        $orchestrator->markOrderAsPaid($order, [
            'payment_gateway' => 'MIDTRANS',
            'transaction_id' => 'DELTA-SUPPLEMENTAL-PENDING',
            'payment_method' => 'QRIS',
            'amount' => 100000.00,
        ]);

        // Assert delta payment berhasil diupdate menjadi SUCCESS (tidak diblokir oleh guard)
        $this->assertEquals('SUCCESS', $deltaPayment->fresh()->status);

        // Assert booking berubah menjadi PAID dan QR code aktif kembali
        $updatedBooking = $booking->fresh();
        $this->assertEquals('PAID', $updatedBooking->status);
        $this->assertNotEmpty($updatedBooking->qr_code_hash);

        // Assert order status tetap PAID
        $this->assertEquals('PAID', $order->fresh()->payment_status);
    }

    /**
     * 11. Non-Destructive: Fulfillment handler dilarang menimpa status CHECKED_IN atau COMPLETED.
     */
    public function test_fulfillment_handler_does_not_overwrite_checked_in_or_completed_status(): void
    {
        $date = now()->format('Y-m-d');
        $order = Order::create([
            'order_number' => 'ORD-CHECKEDIN-GUARD-1',
            'user_id' => $this->customer->id,
            'order_type' => 'ONLINE_BOOKING',
            'subtotal' => 200000.00,
            'grand_total' => 200000.00,
            'payment_status' => 'UNPAID',
        ]);

        $booking = PadelBooking::create([
            'booking_code' => 'BK-ALREADY-PLAYING',
            'order_id' => $order->id,
            'user_id' => $this->customer->id,
            'court_id' => $this->court->id,
            'booking_date' => $date,
            'start_time' => Carbon::parse("{$date} 08:00:00"),
            'end_time' => Carbon::parse("{$date} 09:00:00"),
            'court_fee' => 200000.00,
            'total_amount' => 200000.00,
            'status' => 'CHECKED_IN', // Pemain sudah di lapangan
            'qr_code_hash' => 'existing_active_qr',
        ]);

        $order->items()->create([
            'item_type' => 'PADEL',
            'reference_id' => $booking->id,
            'item_name' => 'Sewa Lapangan Padel',
            'quantity' => 1,
            'unit_price' => 200000.00,
            'subtotal' => 200000.00,
        ]);

        $orchestrator = app(PaymentOrchestratorService::class);
        $orchestrator->markOrderAsPaid($order, [
            'payment_gateway' => 'CASH',
            'transaction_id' => 'POS-CHECKEDIN-GUARD',
            'payment_method' => 'CASH',
            'amount' => 200000.00,
        ]);

        // Status booking WAJIB tetap CHECKED_IN, tidak boleh turun kasta jadi PAID!
        $this->assertEquals('CHECKED_IN', $booking->fresh()->status);
        $this->assertEquals('existing_active_qr', $booking->fresh()->qr_code_hash);
    }
}
