<?php

namespace Tests\Feature;

use App\Exceptions\SlotConflictException;
use App\Models\Padel\CourtEquipment;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\Pos\PosCashierShift;
use App\Models\User;
use App\Services\Padel\PadelBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WalkInBookingTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;
    protected User $adminUser;
    protected User $customerUser;
    protected PadelCourt $court1;
    protected PadelCourt $court2;
    protected CourtEquipment $racket;
    protected CourtEquipment $ball;
    protected PadelBookingService $service;
    protected string $bookingDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PadelBookingService::class);

        // Sinkronisasi permissions agar role dapat digunakan dalam test
        \App\Services\Permission\Club61PermissionMatrix::syncAllPermissions('web');

        $this->cashier = User::factory()->cashier()->create([
            'name' => 'Kasir Frontdesk Club61',
            'is_active' => true,
        ]);

        $this->cashier->givePermissionTo([
            'View:BookOfflineCourt',
            'process_walkin_booking',
        ]);

        $this->adminUser = User::factory()->admin()->create([
            'name' => 'Admin Test Club61',
            'is_active' => true,
        ]);

        $this->adminUser->givePermissionTo([
            'View:BookOfflineCourt',
            'process_walkin_booking',
        ]);

        $this->customerUser = User::factory()->customer()->create([
            'name' => 'Customer Biasa',
            'phone' => '081999000111',
            'is_active' => true,
        ]);

        $this->court1 = PadelCourt::create([
            'name' => 'Court 1 - Panoramic Indoor',
            'type' => 'INDOOR',
            'hourly_rate_regular' => 300000.00,
            'hourly_rate_prime' => 450000.00,
            'is_active' => true,
        ]);

        $this->court2 = PadelCourt::create([
            'name' => 'Court 2 - Panoramic Indoor',
            'type' => 'INDOOR',
            'hourly_rate_regular' => 300000.00,
            'hourly_rate_prime' => 450000.00,
            'is_active' => true,
        ]);

        $this->racket = CourtEquipment::create([
            'name' => 'Raket Babolat Counter Viper',
            'type' => 'RACKET',
            'rental_price' => 50000.00,
            'stock_quantity' => 10,
        ]);

        $this->ball = CourtEquipment::create([
            'name' => 'Bola Padel Pro (1 Can)',
            'type' => 'BALL',
            'rental_price' => 35000.00,
            'stock_quantity' => 20,
        ]);

        // Gunakan tanggal yang pasti di masa depan untuk menghindari validasi jam past
        $this->bookingDate = now()->addDays(3)->format('Y-m-d');

        // Buka shift kasir aktif untuk loket Padel Frontdesk agar transaksi POS dapat diproses
        PosCashierShift::create([
            'shift_number' => 'SFT-PADEL-' . now()->format('Ymd') . '-0001',
            'counter' => 'PADEL_FRONTDESK',
            'status' => 'OPEN',
            'opened_by_id' => $this->cashier->id,
            'opened_at' => now(),
            'starting_cash' => 500000,
            'expected_cash' => 500000,
        ]);
    }

    /**
     * Test 1: Nomor telepon yang sama menggunakan ulang user eksisting (Smart Deduplication).
     */
    public function test_walk_in_customer_phone_deduplication(): void
    {
        // Buat customer eksisting dengan nomor telepon tertentu
        $existingCustomer = User::factory()->customer()->create([
            'name' => 'Budi Lama',
            'phone' => '081234567890',
            'is_active' => true,
        ]);

        $initialUserCount = User::count();

        // findOrCreateWalkInCustomer dengan nomor yang sama tidak boleh membuat row baru
        $result = $this->service->findOrCreateWalkInCustomer(
            name: 'Nama Baru Yang Berbeda',
            phone: '081234567890'
        );

        $this->assertEquals($existingCustomer->id, $result->id);
        $this->assertEquals($initialUserCount, User::count());
    }

    /**
     * Test 2: Normalisasi format nomor telepon internasional ke format lokal.
     */
    public function test_phone_normalization_international_format(): void
    {
        $existingCustomer = User::factory()->customer()->create([
            'name' => 'Customer Internasional',
            'phone' => '081298765432',
            'is_active' => true,
        ]);

        // +628... harus di-normalize ke 08...
        $result1 = $this->service->findOrCreateWalkInCustomer('Test', '+6281298765432');
        $this->assertEquals($existingCustomer->id, $result1->id);

        // 628... juga harus di-normalize ke 08...
        $result2 = $this->service->findOrCreateWalkInCustomer('Test', '6281298765432');
        $this->assertEquals($existingCustomer->id, $result2->id);
    }

    /**
     * Test 3: Nomor telepon baru membuat user baru dengan registration_source = 'WALK_IN' dan email sintetis.
     */
    public function test_walk_in_new_customer_creation(): void
    {
        $initialUserCount = User::count();

        $newCustomer = $this->service->findOrCreateWalkInCustomer(
            name: 'Pemain Walk-In Baru',
            phone: '089912345678'
        );

        // Harus membuat 1 user baru
        $this->assertEquals($initialUserCount + 1, User::count());

        // registration_source harus 'WALK_IN'
        $this->assertEquals('WALK_IN', $newCustomer->registration_source);

        // Email harus memiliki suffix @walkin.club61.internal
        $this->assertStringContainsString('@walkin.club61.internal', $newCustomer->email);
        $this->assertStringStartsWith('walkin-', $newCustomer->email);

        // Role harus customer
        $this->assertTrue($newCustomer->fresh()->hasRole('customer'));

        // Phone harus tersimpan dengan benar
        $this->assertEquals('089912345678', $newCustomer->phone);

        // Password default harus 6 digit terakhir nomor HP (345678)
        $this->assertTrue(Hash::check('345678', $newCustomer->password));
    }

    /**
     * Test 3b: Pelanggan walk-in dapat login ke web menggunakan No HP dan 6 digit terakhir nomor HP.
     */
    public function test_walk_in_customer_can_login_with_phone_and_last_six_digits(): void
    {
        $customer = $this->service->findOrCreateWalkInCustomer(
            name: 'Pemain POS',
            phone: '081234567890'
        );

        $response = $this->post('/login', [
            'email' => '081234567890',
            'password' => '567890',
        ]);

        $this->assertAuthenticatedAs($customer);
    }

    /**
     * Test 4: Kasir dapat membooking 2 lapangan sekaligus dalam 1 transaksi (Multi-Court Batch).
     */
    public function test_walk_in_multi_court_batch_booking(): void
    {
        $customer = $this->service->findOrCreateWalkInCustomer(
            name: 'Pemain Batch',
            phone: '081300000001'
        );

        $result = $this->service->processWalkInCheckout(
            customer: $customer,
            slots: [
                [
                    'court_id' => $this->court1->id,
                    'start_time' => '09:00:00',
                    'end_time' => '10:00:00',
                ],
                [
                    'court_id' => $this->court2->id,
                    'start_time' => '09:00:00',
                    'end_time' => '10:00:00',
                ],
            ],
            bookingDate: $this->bookingDate,
            equipments: [],
            paymentMethod: 'CASH',
            cashier: $this->cashier,
            autoCheckIn: false
        );

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['bookings']);

        // Kedua booking harus berasal dari court yang berbeda
        $courtIds = $result['bookings']->pluck('court_id')->toArray();
        $this->assertContains($this->court1->id, $courtIds);
        $this->assertContains($this->court2->id, $courtIds);
    }

    /**
     * Test 5: Settlement langsung POS - order_type = 'WALK_IN', cashier_id terisi, payment SUCCESS.
     */
    public function test_walk_in_clean_pos_settlement_and_order_type(): void
    {
        $customer = $this->service->findOrCreateWalkInCustomer(
            name: 'Pemain POS Test',
            phone: '081300000002'
        );

        $result = $this->service->processWalkInCheckout(
            customer: $customer,
            slots: [
                [
                    'court_id' => $this->court1->id,
                    'start_time' => '10:00:00',
                    'end_time' => '11:00:00',
                ],
            ],
            bookingDate: $this->bookingDate,
            equipments: [],
            paymentMethod: 'CASH',
            cashier: $this->cashier,
            autoCheckIn: false
        );

        $this->assertTrue($result['success']);

        $order = $result['order'];

        // order_type WAJIB = 'WALK_IN' (bukan ONLINE_BOOKING)
        $this->assertEquals('WALK_IN', $order->order_type);

        // cashier_id WAJIB terisi
        $this->assertEquals($this->cashier->id, $order->cashier_id);

        // payment_status WAJIB = 'PAID'
        $this->assertEquals('PAID', $order->payment_status);

        // Harus ada payment record SUCCESS dengan gateway CASHIER_POS
        $payment = Payment::where('order_id', $order->id)->where('status', 'SUCCESS')->first();
        $this->assertNotNull($payment);
        $this->assertEquals('CASHIER_POS', $payment->payment_gateway);

        // Booking harus berstatus PAID
        $booking = $result['bookings']->first();
        $this->assertEquals('PAID', $booking->status);

        // QR Code hash harus sudah diisi
        $this->assertNotNull($booking->qr_code_hash);
    }

    /**
     * Test 6: Sewa alat lapangan tercatat dengan item_type = 'PADEL' (bukan PADEL_EQUIPMENT atau MERCH).
     */
    public function test_rental_equipment_attributed_to_padel_item_type(): void
    {
        $customer = $this->service->findOrCreateWalkInCustomer(
            name: 'Pemain Sewa Alat',
            phone: '081300000003'
        );

        $result = $this->service->processWalkInCheckout(
            customer: $customer,
            slots: [
                [
                    'court_id' => $this->court1->id,
                    'start_time' => '11:00:00',
                    'end_time' => '12:00:00',
                ],
            ],
            bookingDate: $this->bookingDate,
            equipments: [
                ['equipment_id' => $this->racket->id, 'quantity' => 2],
                ['equipment_id' => $this->ball->id, 'quantity' => 1],
            ],
            paymentMethod: 'EDC_BCA',
            cashier: $this->cashier,
            autoCheckIn: false
        );

        $this->assertTrue($result['success']);

        $order = $result['order'];

        // Seluruh order_items harus memiliki item_type = 'PADEL'
        $itemTypes = $order->items->pluck('item_type')->unique()->toArray();
        $this->assertEquals(['PADEL'], array_values($itemTypes));

        // Grand total harus mencakup lapangan + sewa alat
        $expectedEquipmentTotal = (50000.00 * 2) + (35000.00 * 1); // 135.000

        // Tarif lapangan bergantung pada hari (reguler vs prime/weekend)
        $isWeekend = \Carbon\Carbon::parse($this->bookingDate)->isWeekend();
        $expectedCourtFee = $isWeekend ? 450000.00 : 300000.00; // jam 11:00 bukan prime weekday
        $expectedGrandTotal = $expectedCourtFee + $expectedEquipmentTotal;
        $this->assertEquals($expectedGrandTotal, $result['grand_total']);
    }

    /**
     * Test 7: Opsi auto check-in software langsung set status CHECKED_IN dan checked_in_at.
     */
    public function test_walk_in_auto_check_in_immediately_updates_status(): void
    {
        $customer = $this->service->findOrCreateWalkInCustomer(
            name: 'Pemain Auto CheckIn',
            phone: '081300000004'
        );

        $result = $this->service->processWalkInCheckout(
            customer: $customer,
            slots: [
                [
                    'court_id' => $this->court1->id,
                    'start_time' => '12:00:00',
                    'end_time' => '13:00:00',
                ],
            ],
            bookingDate: $this->bookingDate,
            equipments: [],
            paymentMethod: 'QRIS_STATIS',
            cashier: $this->cashier,
            autoCheckIn: true
        );

        $this->assertTrue($result['success']);
        $this->assertTrue($result['auto_checked_in']);

        $booking = $result['bookings']->first();

        // Status harus CHECKED_IN (bukan PAID)
        $this->assertEquals('CHECKED_IN', $booking->status);

        // checked_in_at harus terisi
        $this->assertNotNull($booking->checked_in_at);
    }

    /**
     * Test 8: Pelanggan yang sama booking ulang tidak membuat akun duplikat.
     */
    public function test_returning_walkin_customer_reuses_same_account(): void
    {
        // Booking pertama -> membuat akun baru
        $customer1 = $this->service->findOrCreateWalkInCustomer(
            name: 'Pelanggan Setia',
            phone: '081400000001'
        );

        // Booking kedua dengan nomor yang sama -> harus pakai akun yang sama
        $customer2 = $this->service->findOrCreateWalkInCustomer(
            name: 'Nama Berbeda Tapi HP Sama',
            phone: '081400000001'
        );

        $this->assertEquals($customer1->id, $customer2->id);
        $this->assertEquals(1, User::where('phone', '081400000001')->count());
    }

    /**
     * Test 9: Akses halaman Walk-In Booking diizinkan untuk cashier dan admin.
     */
    public function test_walk_in_filament_page_shield_access(): void
    {
        // Kasir harus memiliki permission View:BookOfflineCourt
        $this->assertTrue($this->cashier->can('View:BookOfflineCourt'));

        // Admin harus memiliki permission View:BookOfflineCourt
        $this->assertTrue($this->adminUser->can('View:BookOfflineCourt'));

        // Customer TIDAK boleh memiliki permission View:BookOfflineCourt
        $this->assertFalse($this->customerUser->can('View:BookOfflineCourt'));
    }

    /**
     * Test 10: SlotConflictException ditangkap dengan benar saat slot sudah diambil.
     */
    public function test_walk_in_slot_conflict_throws_proper_exception(): void
    {
        // Booking pertama dari customer online
        $onlineCustomer = User::factory()->customer()->create();
        $this->service->holdBatchSlots(
            slots: [
                [
                    'court_id' => $this->court1->id,
                    'start_time' => '14:00:00',
                    'end_time' => '15:00:00',
                ],
            ],
            bookingDate: $this->bookingDate,
            user: $onlineCustomer
        );

        // Walk-in kasir pada slot yang sama harus melempar SlotConflictException
        $walkInCustomer = $this->service->findOrCreateWalkInCustomer(
            name: 'Pemain Walk-In Konflik',
            phone: '081500000001'
        );

        $this->expectException(SlotConflictException::class);

        $this->service->processWalkInCheckout(
            customer: $walkInCustomer,
            slots: [
                [
                    'court_id' => $this->court1->id,
                    'start_time' => '14:00:00',
                    'end_time' => '15:00:00',
                ],
            ],
            bookingDate: $this->bookingDate,
            equipments: [],
            paymentMethod: 'CASH',
            cashier: $this->cashier,
            autoCheckIn: false
        );
    }

    /**
     * Test 11: EDC_BCA dan QRIS_STATIS tidak dikenakan biaya gateway (0 service_charge).
     */
    public function test_walk_in_pos_payment_methods_have_zero_service_charge(): void
    {
        $customer = $this->service->findOrCreateWalkInCustomer(
            name: 'Test Service Charge',
            phone: '081600000001'
        );

        $result = $this->service->processWalkInCheckout(
            customer: $customer,
            slots: [
                [
                    'court_id' => $this->court1->id,
                    'start_time' => '15:00:00',
                    'end_time' => '16:00:00',
                ],
            ],
            bookingDate: $this->bookingDate,
            equipments: [],
            paymentMethod: 'EDC_MANDIRI',
            cashier: $this->cashier,
            autoCheckIn: false
        );

        $this->assertTrue($result['success']);

        // service_charge harus 0 untuk metode POS offline
        $this->assertEquals(0, (float) $result['order']->service_charge);
    }
}
