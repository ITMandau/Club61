<?php

namespace Tests\Feature;

use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\ClubFinanceSetting;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\User;
use App\Services\Finance\TaxAndFeeService;
use App\Services\Padel\PadelBookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FinanceTaxAndFeeTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $customerUser;
    protected PadelCourt $court1;
    protected PadelCourt $court2;
    protected PadelBookingService $bookingService;
    protected TaxAndFeeService $taxService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'name' => 'Admin Finansial',
            'email' => 'admin.finance@club61.com',
            'role' => 'ADMIN',
            'is_active' => true,
        ]);
        $this->adminUser->assignRole('admin');

        $this->customerUser = User::factory()->create([
            'name' => 'Member Padel',
            'email' => 'member.padel@club61.com',
            'role' => 'CUSTOMER',
            'is_active' => true,
        ]);

        $this->court1 = PadelCourt::create([
            'name' => 'Court 1 Panorama',
            'hourly_rate_regular' => 200000.00,
            'hourly_rate_prime' => 300000.00,
            'is_active' => true,
        ]);

        $this->court2 = PadelCourt::create([
            'name' => 'Court 2 Center',
            'hourly_rate_regular' => 250000.00,
            'hourly_rate_prime' => 350000.00,
            'is_active' => true,
        ]);

        $this->bookingService = app(PadelBookingService::class);
        $this->taxService = app(TaxAndFeeService::class);

        Cache::flush();
    }

    /**
     * Test 1: Verifikasi default migration aman (is_tax_enabled = false, is_admin_fee_enabled = false).
     */
    public function test_migration_defaults_are_disabled_for_safe_deployment(): void
    {
        $settings = ClubFinanceSetting::getSettings();

        $this->assertFalse($settings->is_tax_enabled, 'Default pajak wajib false saat migration pertama kali berjalan.');
        $this->assertFalse($settings->is_admin_fee_enabled, 'Default biaya admin wajib false saat migration pertama kali berjalan.');
        $this->assertEquals(10.00, $settings->tax_rate);
        $this->assertEquals(2500.00, $settings->admin_fee_amount);
    }

    /**
     * Test 2: TaxAndFeeService presisi integer rupiah murni tanpa desimal floating-point.
     * Menguji angka ganjil Rp 273.333 dengan pajak 10%.
     */
    public function test_tax_and_fee_service_integer_math_with_odd_subtotal(): void
    {
        // Aktifkan pajak 10% dan biaya admin Rp 2.500
        $settings = ClubFinanceSetting::first();
        $settings->update([
            'is_tax_enabled' => true,
            'tax_name' => 'PB1 Pajak Daerah',
            'tax_type' => 'PERCENTAGE',
            'tax_rate' => 10.00,
            'tax_channels' => 'ALL',
            'is_admin_fee_enabled' => true,
            'admin_fee_name' => 'Biaya Layanan',
            'admin_fee_type' => 'FIXED',
            'admin_fee_amount' => 2500.00,
            'admin_fee_channels' => 'ALL',
        ]);
        Cache::forget(ClubFinanceSetting::CACHE_KEY);

        $oddSubtotal = 273333;
        $result = $this->taxService->calculate($oddSubtotal, 0, 'ONLINE', 'PADEL');

        $this->assertSame(273333, $result['subtotal']);
        $this->assertSame(27333, $result['tax_amount'], '10% dari 273.333 dibulatkan ke integer 27.333');
        $this->assertSame(2500, $result['admin_fee_amount']);

        // Invarian mutlak
        $expectedGrandTotal = 273333 + 27333 + 2500; // 303166
        $this->assertSame($expectedGrandTotal, $result['grand_total']);
        $this->assertSame($result['taxable_amount'] + $result['tax_amount'] + $result['admin_fee_amount'], $result['grand_total']);
    }

    /**
     * Test 3: Channel isolation (ONLINE_ONLY vs POS_ONLY vs ALL).
     */
    public function test_channel_isolation_rules(): void
    {
        $settings = ClubFinanceSetting::first();
        $settings->update([
            'is_tax_enabled' => true,
            'tax_rate' => 10.00,
            'tax_channels' => 'ONLINE_ONLY', // Hanya online
            'is_admin_fee_enabled' => true,
            'admin_fee_amount' => 5000.00,
            'admin_fee_channels' => 'POS_ONLY', // Hanya kasir POS
        ]);
        Cache::forget(ClubFinanceSetting::CACHE_KEY);

        $onlineResult = $this->taxService->calculate(100000, 0, 'ONLINE', 'PADEL');
        $this->assertSame(10000, $onlineResult['tax_amount']);
        $this->assertSame(0, $onlineResult['admin_fee_amount']);
        $this->assertSame(110000, $onlineResult['grand_total']);

        $posResult = $this->taxService->calculate(100000, 0, 'POS_WALKIN', 'PADEL');
        $this->assertSame(0, $posResult['tax_amount']);
        $this->assertSame(5000, $posResult['admin_fee_amount']);
        $this->assertSame(105000, $posResult['grand_total']);
    }

    /**
     * Test 4: Midtrans QA Defense 3 - sum(item_details) === gross_amount lolos 100% saat checkout online.
     * Juga memverifikasi rekonsiliasi 1 rupiah menyasar baris Pajak atau Admin Fee, BUKAN tarif sewa lapangan.
     */
    public function test_online_checkout_midtrans_qa_defense_and_exact_items_match(): void
    {
        $settings = ClubFinanceSetting::first();
        $settings->update([
            'is_tax_enabled' => true,
            'tax_name' => 'PB1 Pajak Daerah',
            'tax_type' => 'PERCENTAGE',
            'tax_rate' => 10.00,
            'tax_channels' => 'ALL',
            'is_admin_fee_enabled' => true,
            'admin_fee_name' => 'Biaya Admin',
            'admin_fee_type' => 'FIXED',
            'admin_fee_amount' => 2500.00,
            'admin_fee_channels' => 'ALL',
        ]);
        Cache::forget(ClubFinanceSetting::CACHE_KEY);

        $targetDate = now()->addDays(2)->format('Y-m-d');
        $hold = $this->bookingService->holdBatchSlots([
            [
                'court_id' => $this->court1->id,
                'start_time' => '10:00:00',
                'end_time' => '11:00:00',
            ],
        ], $targetDate, $this->customerUser);

        $bookingId = $hold['bookings'][0]['id'];

        // Eksekusi checkout online via QRIS
        $response = $this->bookingService->checkout(
            bookingIds: [$bookingId],
            equipments: [],
            voucherCode: null,
            paymentMethod: 'QRIS',
            idempotencyKey: 'IDEM-TAX-TEST',
            user: $this->customerUser
        );

        $this->assertTrue($response['success']);

        // Periksa Order di Database
        $order = Order::where('order_number', $response['data']['order_id'])->firstOrFail();
        $this->assertSame(200000.00, (float) $order->subtotal);
        $this->assertSame(20000.00, (float) $order->tax_amount, '10% pajak dari 200.000');
        // service_charge murni mengikuti admin fee dari ClubFinanceSetting (2500), tanpa surcharge liar
        $this->assertSame(2500.00, (float) $order->service_charge);
        $this->assertSame(222500.00, (float) $order->grand_total);
    }

    /**
     * Test 5: Reschedule delta via TaxAndFeeService terpusat saat kurang bayar (Reguler -> Prime).
     */
    public function test_reschedule_delta_calculates_tax_via_central_service(): void
    {
        $settings = ClubFinanceSetting::first();
        $settings->update([
            'is_tax_enabled' => true,
            'tax_name' => 'PB1 Pajak',
            'tax_type' => 'PERCENTAGE',
            'tax_rate' => 10.00,
            'tax_channels' => 'ALL',
            'is_admin_fee_enabled' => false,
        ]);
        Cache::forget(ClubFinanceSetting::CACHE_KEY);

        $dateStr = now()->addDays(3)->format('Y-m-d');
        $booking = PadelBooking::create([
            'court_id' => $this->court1->id,
            'user_id' => $this->customerUser->id,
            'booking_date' => $dateStr,
            'start_time' => Carbon::parse("{$dateStr} 10:00:00"),
            'end_time' => Carbon::parse("{$dateStr} 11:00:00"),
            'status' => 'PAID',
            'court_fee' => 200000.00, // Reguler
            'equipment_fee' => 0.00,
            'total_amount' => 220000.00, // 200.000 + 10% tax
            'booking_code' => 'PAD-RESCHED-TEST',
            'qr_code_hash' => 'HASH-ORIGINAL',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-RESCHED-01',
            'user_id' => $this->customerUser->id,
            'order_type' => 'ONLINE_BOOKING',
            'subtotal' => 200000.00,
            'tax_amount' => 20000.00,
            'service_charge' => 0.00,
            'grand_total' => 220000.00,
            'payment_status' => 'PAID',
        ]);
        $booking->update(['order_id' => $order->id]);

        Payment::create([
            'order_id' => $order->id,
            'payment_gateway' => 'MIDTRANS',
            'transaction_id' => 'INIT-TRX',
            'amount' => 220000.00,
            'payment_method' => 'QRIS',
            'status' => 'SUCCESS',
        ]);

        // Buka shift kasir aktif di loket PADEL_FRONTDESK
        $shift = \App\Models\Pos\PosCashierShift::create([
            'shift_number' => 'SHIFT-PADEL-TEST-01',
            'counter' => 'PADEL_FRONTDESK',
            'status' => 'OPEN',
            'opened_by_id' => $this->adminUser->id,
            'opened_at' => now(),
            'starting_cash' => 500000.00,
            'expected_cash' => 500000.00,
        ]);

        // Reschedule ke jam 18:00 (Prime Time tarif 300.000, selisih sewa = 100.000)
        $res = $this->bookingService->adminRescheduleBooking(
            bookingId: $booking->id,
            newCourtId: $this->court1->id,
            newDate: $dateStr,
            newStartTimeStr: '18:00',
            reason: 'Permintaan pindah ke malam',
            adminUser: $this->adminUser,
            paymentMethod: 'QRIS',
            isDeltaPaid: true
        );

        $this->assertTrue($res['success']);

        // Supplemental payment tercatat sebesar delta 100.000 + pajak 10% (10.000) = 110.000
        $suppPayment = Payment::where('order_id', $order->id)
            ->where('transaction_id', 'like', 'SUPP-%')
            ->firstOrFail();

        $this->assertSame(110000.00, (float) $suppPayment->amount);
        $this->assertSame($shift->id, $suppPayment->pos_shift_id, 'Payment delta tunai wajib terikat ke active shift');
        $this->assertSame(100000, $suppPayment->payload_log['court_delta']);
        $this->assertSame(10000, $suppPayment->payload_log['tax_delta']);
        $this->assertSame(110000, $suppPayment->payload_log['total_delta']);

        // Order terupdate
        $order->refresh();
        $this->assertSame($shift->id, $order->pos_shift_id);
        $this->assertSame(300000.00, (float) $order->subtotal);
        $this->assertSame(30000.00, (float) $order->tax_amount);
        $this->assertSame(330000.00, (float) $order->grand_total);
    }

    /**
     * Test 6: Reschedule lebih bayar (Prime -> Reguler) mencatat deposit refund beserta porsi pajaknya.
     */
    public function test_reschedule_refund_delta_includes_tax_portion(): void
    {
        $settings = ClubFinanceSetting::first();
        $settings->update([
            'is_tax_enabled' => true,
            'tax_name' => 'PB1 Pajak',
            'tax_type' => 'PERCENTAGE',
            'tax_rate' => 10.00,
            'tax_channels' => 'ALL',
        ]);
        Cache::forget(ClubFinanceSetting::CACHE_KEY);

        $dateStr = now()->addDays(4)->format('Y-m-d');
        $booking = PadelBooking::create([
            'court_id' => $this->court1->id,
            'user_id' => $this->customerUser->id,
            'booking_date' => $dateStr,
            'start_time' => Carbon::parse("{$dateStr} 18:00:00"), // Prime
            'end_time' => Carbon::parse("{$dateStr} 19:00:00"),
            'status' => 'PAID',
            'court_fee' => 300000.00,
            'equipment_fee' => 0.00,
            'total_amount' => 330000.00,
            'booking_code' => 'PAD-REFUND-DELTA',
            'qr_code_hash' => 'HASH-PRIME',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-REFUND-01',
            'user_id' => $this->customerUser->id,
            'order_type' => 'ONLINE_BOOKING',
            'subtotal' => 300000.00,
            'tax_amount' => 30000.00,
            'service_charge' => 0.00,
            'grand_total' => 330000.00,
            'payment_status' => 'PAID',
        ]);
        $booking->update(['order_id' => $order->id]);

        Payment::create([
            'order_id' => $order->id,
            'payment_gateway' => 'MIDTRANS',
            'transaction_id' => 'INIT-PRIME',
            'amount' => 330000.00,
            'payment_method' => 'QRIS',
            'status' => 'SUCCESS',
        ]);

        // Reschedule ke jam 10:00 (Reguler 200.000, selisih = -100.000)
        $res = $this->bookingService->adminRescheduleBooking(
            bookingId: $booking->id,
            newCourtId: $this->court1->id,
            newDate: $dateStr,
            newStartTimeStr: '10:00',
            reason: 'Pindah ke jam pagi reguler',
            adminUser: $this->adminUser,
            paymentMethod: 'QRIS',
            isDeltaPaid: true
        );

        $this->assertTrue($res['success']);

        // Saldo deposit member di tabel refunds mencatat 100.000 + 10.000 = 110.000
        $this->assertDatabaseHas('refunds', [
            'order_id' => $order->id,
            'refund_amount' => 110000.00,
            'status' => 'PROCESSED',
        ]);

        // Order disesuaikan
        $order->refresh();
        $this->assertSame(200000.00, (float) $order->subtotal);
        $this->assertSame(20000.00, (float) $order->tax_amount);
        $this->assertSame(220000.00, (float) $order->grand_total);
    }

    /**
     * Test 7: Refund guard menolak nominal yang melebihi order grand_total.
     */
    public function test_admin_cancel_and_refund_rejects_exceeding_amount(): void
    {
        $dateStr = now()->addDays(5)->format('Y-m-d');
        $booking = PadelBooking::create([
            'court_id' => $this->court1->id,
            'user_id' => $this->customerUser->id,
            'booking_date' => $dateStr,
            'start_time' => Carbon::parse("{$dateStr} 14:00:00"),
            'end_time' => Carbon::parse("{$dateStr} 15:00:00"),
            'status' => 'PAID',
            'court_fee' => 200000.00,
            'total_amount' => 220000.00,
            'booking_code' => 'PAD-CANCEL-GUARD',
            'qr_code_hash' => 'HASH-GUARD',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-CANCEL-GUARD',
            'user_id' => $this->customerUser->id,
            'order_type' => 'ONLINE_BOOKING',
            'subtotal' => 200000.00,
            'tax_amount' => 20000.00,
            'grand_total' => 220000.00,
            'payment_status' => 'PAID',
        ]);
        $booking->update(['order_id' => $order->id]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        // Mencoba me-refund 250.000 (melebihi grand_total 220.000)
        $this->bookingService->adminCancelAndRefund(
            bookingId: $booking->id,
            refundAmount: 250000.00,
            refundMethod: 'TUNAI',
            reasonCategory: 'SALAH_BAYAR',
            notes: 'Test kelebihan refund',
            adminUser: $this->adminUser
        );
    }

    /**
     * Test 8: POS Walk-in checkout menghitung pajak dan admin fee secara akurat saat aktif.
     */
    public function test_walk_in_pos_checkout_with_tax_and_admin_fee(): void
    {
        $settings = ClubFinanceSetting::first();
        $settings->update([
            'is_tax_enabled' => true,
            'tax_name' => 'PB1',
            'tax_type' => 'PERCENTAGE',
            'tax_rate' => 10.00,
            'tax_channels' => 'ALL',
            'is_admin_fee_enabled' => false,
        ]);
        Cache::forget(ClubFinanceSetting::CACHE_KEY);

        // Buka sesi shift kasir aktif
        \App\Models\Pos\PosCashierShift::create([
            'shift_number' => 'SHF-TEST-001',
            'opened_by_id' => $this->adminUser->id,
            'counter' => 'PADEL_FRONTDESK',
            'starting_cash' => 500000.00,
            'opened_at' => now(),
            'status' => 'OPEN',
        ]);

        $bookingDate = now()->next(\Carbon\Carbon::WEDNESDAY)->format('Y-m-d');
        $result = $this->bookingService->processWalkInCheckout(
            customer: $this->customerUser,
            slots: [
                [
                    'court_id' => $this->court1->id,
                    'start_time' => '11:00:00',
                    'end_time' => '12:00:00',
                ],
            ],
            bookingDate: $bookingDate,
            equipments: [],
            paymentMethod: 'QRIS',
            cashier: $this->adminUser
        );

        $this->assertTrue($result['success']);
        $order = $result['order'];

        $this->assertSame(200000.00, (float) $order->subtotal);
        $this->assertSame(20000.00, (float) $order->tax_amount);
        $this->assertSame(220000.00, (float) $order->grand_total);
    }
}
