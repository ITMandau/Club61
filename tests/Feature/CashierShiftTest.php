<?php

namespace Tests\Feature;

use App\Filament\Pages\BookOfflineCourt;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\Pos\PosCashierShift;
use App\Models\User;
use App\Services\Padel\PadelBookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class CashierShiftTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;
    protected User $cashier2;
    protected User $superAdmin;
    protected User $customer;
    protected PadelCourt $court;
    protected PadelBookingService $service;
    protected string $bookingDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PadelBookingService::class);

        \App\Services\Permission\Club61PermissionMatrix::syncAllPermissions('web');

        $this->cashier = User::factory()->cashier()->create([
            'name' => 'Kasir Pagi',
            'is_active' => true,
        ]);
        $this->cashier->givePermissionTo(['View:BookOfflineCourt', 'process_walkin_booking']);

        $this->cashier2 = User::factory()->cashier()->create([
            'name' => 'Kasir Malam',
            'is_active' => true,
        ]);
        $this->cashier2->givePermissionTo(['View:BookOfflineCourt', 'process_walkin_booking']);

        $this->superAdmin = User::factory()->superAdmin()->create([
            'name' => 'Super Administrator',
            'is_active' => true,
        ]);

        $this->customer = User::factory()->customer()->create([
            'name' => 'Customer Setia',
            'phone' => '081234567890',
            'is_active' => true,
        ]);

        $this->court = PadelCourt::create([
            'name' => 'Court 1 Centre Court',
            'type' => 'INDOOR',
            'hourly_rate_regular' => 200000.00,
            'hourly_rate_prime' => 300000.00,
            'is_active' => true,
        ]);

        $this->bookingDate = Carbon::parse('next Tuesday')->format('Y-m-d');
    }

    public function test_cashier_can_open_shift_with_starting_cash(): void
    {
        $shiftNumber = PosCashierShift::generateShiftNumber('PADEL_FRONTDESK');
        $dateStr = Carbon::now('Asia/Jakarta')->format('Ymd');

        $this->assertStringStartsWith("SFT-PADEL-{$dateStr}-", $shiftNumber);

        $shift = PosCashierShift::create([
            'shift_number' => $shiftNumber,
            'counter' => 'PADEL_FRONTDESK',
            'status' => 'OPEN',
            'opened_by_id' => $this->cashier->id,
            'opened_at' => Carbon::now('Asia/Jakarta'),
            'starting_cash' => 500000.00,
            'expected_cash' => 500000.00,
            'opening_notes' => 'Modal awal shift pagi',
        ]);

        $this->assertDatabaseHas('pos_cashier_shifts', [
            'id' => $shift->id,
            'shift_number' => $shiftNumber,
            'counter' => 'PADEL_FRONTDESK',
            'status' => 'OPEN',
            'opened_by_id' => $this->cashier->id,
            'starting_cash' => 500000.00,
        ]);

        $activeShift = PosCashierShift::getActiveShift('PADEL_FRONTDESK');
        $this->assertNotNull($activeShift);
        $this->assertEquals($shift->id, $activeShift->id);
    }

    public function test_multi_counter_isolation(): void
    {
        $dateStr = Carbon::now('Asia/Jakarta')->format('Ymd');

        $padelShift = PosCashierShift::create([
            'shift_number' => PosCashierShift::generateShiftNumber('PADEL_FRONTDESK'),
            'counter' => 'PADEL_FRONTDESK',
            'status' => 'OPEN',
            'opened_by_id' => $this->cashier->id,
            'opened_at' => Carbon::now('Asia/Jakarta'),
            'starting_cash' => 500000.00,
            'expected_cash' => 500000.00,
        ]);

        $fnbShift = PosCashierShift::create([
            'shift_number' => PosCashierShift::generateShiftNumber('FNB_COUNTER'),
            'counter' => 'FNB_COUNTER',
            'status' => 'OPEN',
            'opened_by_id' => $this->cashier2->id,
            'opened_at' => Carbon::now('Asia/Jakarta'),
            'starting_cash' => 300000.00,
            'expected_cash' => 300000.00,
        ]);

        $this->assertStringStartsWith("SFT-PADEL-{$dateStr}-", $padelShift->shift_number);
        $this->assertStringStartsWith("SFT-FNB-{$dateStr}-", $fnbShift->shift_number);

        $activePadel = PosCashierShift::getActiveShift('PADEL_FRONTDESK');
        $activeFnb = PosCashierShift::getActiveShift('FNB_COUNTER');

        $this->assertEquals($padelShift->id, $activePadel->id);
        $this->assertEquals($fnbShift->id, $activeFnb->id);
    }

    public function test_walk_in_transaction_fails_if_no_active_shift(): void
    {
        $this->assertNull(PosCashierShift::getActiveShift('PADEL_FRONTDESK'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Tidak ada shift kasir yang aktif untuk loket [PADEL_FRONTDESK]. Silakan buka shift terlebih dahulu.');

        $this->service->processWalkInCheckout(
            customer: $this->customer,
            slots: [
                [
                    'court_id' => $this->court->id,
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
    }

    public function test_admin_settle_cashier_payment_fails_if_no_active_shift(): void
    {
        $this->assertNull(PosCashierShift::getActiveShift('PADEL_FRONTDESK'));

        $booking = PadelBooking::create([
            'booking_code' => 'BK-TEST-NOSHIFT',
            'user_id' => $this->customer->id,
            'court_id' => $this->court->id,
            'booking_date' => $this->bookingDate,
            'start_time' => Carbon::parse("{$this->bookingDate} 10:00"),
            'end_time' => Carbon::parse("{$this->bookingDate} 11:00"),
            'court_fee' => 200000.00,
            'total_amount' => 200000.00,
            'status' => 'PENDING_PAYMENT',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Tidak ada shift kasir yang aktif untuk loket [PADEL_FRONTDESK]. Silakan buka shift terlebih dahulu.');

        $this->service->adminSettleCashierPayment(
            bookingId: $booking->id,
            paymentMethod: 'CASH',
            amountReceived: 200000.00,
            cashierUser: $this->cashier
        );
    }

    public function test_walk_in_transaction_attaches_pos_shift_id_to_payment_and_order(): void
    {
        $shift = PosCashierShift::create([
            'shift_number' => PosCashierShift::generateShiftNumber('PADEL_FRONTDESK'),
            'counter' => 'PADEL_FRONTDESK',
            'status' => 'OPEN',
            'opened_by_id' => $this->cashier->id,
            'opened_at' => Carbon::now('Asia/Jakarta'),
            'starting_cash' => 500000.00,
            'expected_cash' => 500000.00,
        ]);

        $result = $this->service->processWalkInCheckout(
            customer: $this->customer,
            slots: [
                [
                    'court_id' => $this->court->id,
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

        $order = $result['order']->fresh();
        $this->assertEquals($shift->id, $order->pos_shift_id);

        $payment = Payment::where('order_id', $order->id)->where('status', 'SUCCESS')->first();
        $this->assertNotNull($payment);
        $this->assertEquals($shift->id, $payment->pos_shift_id);
    }

    public function test_blind_cash_count_closing_calculates_variance_accurately(): void
    {
        // 1. Kasir buka shift pagi dengan modal awal Rp 500.000
        $shift = PosCashierShift::create([
            'shift_number' => PosCashierShift::generateShiftNumber('PADEL_FRONTDESK'),
            'counter' => 'PADEL_FRONTDESK',
            'status' => 'OPEN',
            'opened_by_id' => $this->cashier->id,
            'opened_at' => Carbon::now('Asia/Jakarta'),
            'starting_cash' => 500000.00,
            'expected_cash' => 500000.00,
        ]);

        // 2. Transaksi 1: Walk-In Bayar Tunai Rp 200.000
        $this->service->processWalkInCheckout(
            customer: $this->customer,
            slots: [
                [
                    'court_id' => $this->court->id,
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

        // 3. Transaksi 2: Walk-In Bayar QRIS Rp 200.000
        $this->service->processWalkInCheckout(
            customer: $this->customer,
            slots: [
                [
                    'court_id' => $this->court->id,
                    'start_time' => '11:00:00',
                    'end_time' => '12:00:00',
                ],
            ],
            bookingDate: $this->bookingDate,
            equipments: [],
            paymentMethod: 'QRIS',
            cashier: $this->cashier,
            autoCheckIn: false
        );

        // 4. Hitung summary sistem
        $summary = $shift->calculateSummary();

        $this->assertEquals(200000.00, $summary['total_cash_sales']);
        $this->assertEquals(200000.00, $summary['total_qris_sales']);
        $this->assertEquals(400000.00, $summary['total_sales']);
        $this->assertEquals(2, $summary['total_transactions']);
        // Ekspektasi kas: modal awal (500k) + penjualan tunai (200k) = 700k
        $this->assertEquals(700000.00, $summary['expected_cash']);

        // Skenario A: Kasir malam closing dengan uang fisik PAS (Rp 700.000)
        $actualCashInput = 700000.00;
        $variance = $actualCashInput - $summary['expected_cash'];
        $this->assertEquals(0.00, $variance);

        // Skenario B: Kasir malam closing dengan uang fisik KURANG (Rp 680.000 -> short Rp 20.000)
        $actualCashInputShort = 680000.00;
        $varianceShort = $actualCashInputShort - $summary['expected_cash'];
        $this->assertEquals(-20000.00, $varianceShort);

        // Eksekusi closing resmi
        $shift->update([
            'status' => 'CLOSED',
            'closed_by_id' => $this->cashier2->id,
            'closed_at' => Carbon::now('Asia/Jakarta'),
            'expected_cash' => $summary['expected_cash'],
            'actual_cash' => $actualCashInputShort,
            'cash_difference' => $varianceShort,
            'total_cash_sales' => $summary['total_cash_sales'],
            'total_qris_sales' => $summary['total_qris_sales'],
            'total_sales' => $summary['total_sales'],
            'total_transactions' => $summary['total_transactions'],
            'closing_notes' => 'Terdapat selisih kurang Rp 20.000 uang kembalian',
        ]);

        $this->assertDatabaseHas('pos_cashier_shifts', [
            'id' => $shift->id,
            'status' => 'CLOSED',
            'closed_by_id' => $this->cashier2->id,
            'actual_cash' => 680000.00,
            'cash_difference' => -20000.00,
        ]);

        // Pastikan tidak ada shift aktif lagi
        $this->assertNull(PosCashierShift::getActiveShift('PADEL_FRONTDESK'));
    }

    public function test_reschedule_supplemental_payment_links_to_new_shift(): void
    {
        // Shift 1 (Senin): Customer bayar awal
        $shift1 = PosCashierShift::create([
            'shift_number' => PosCashierShift::generateShiftNumber('PADEL_FRONTDESK'),
            'counter' => 'PADEL_FRONTDESK',
            'status' => 'OPEN',
            'opened_by_id' => $this->cashier->id,
            'opened_at' => Carbon::now('Asia/Jakarta')->subDay(),
            'starting_cash' => 300000.00,
            'expected_cash' => 300000.00,
        ]);

        $result1 = $this->service->processWalkInCheckout(
            customer: $this->customer,
            slots: [
                [
                    'court_id' => $this->court->id,
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

        $order = $result1['order'];
        $booking = $result1['bookings']->first();

        // Tutup Shift 1
        $summary1 = $shift1->calculateSummary();
        $shift1->update([
            'status' => 'CLOSED',
            'closed_by_id' => $this->cashier->id,
            'closed_at' => Carbon::now('Asia/Jakarta')->subDay()->addHours(8),
            'expected_cash' => $summary1['expected_cash'],
            'actual_cash' => $summary1['expected_cash'],
            'cash_difference' => 0.00,
            'total_cash_sales' => $summary1['total_cash_sales'],
            'total_sales' => $summary1['total_sales'],
            'total_transactions' => $summary1['total_transactions'],
        ]);

        // Shift 2 (Selasa): Buka shift baru
        $shift2 = PosCashierShift::create([
            'shift_number' => PosCashierShift::generateShiftNumber('PADEL_FRONTDESK'),
            'counter' => 'PADEL_FRONTDESK',
            'status' => 'OPEN',
            'opened_by_id' => $this->cashier2->id,
            'opened_at' => Carbon::now('Asia/Jakarta'),
            'starting_cash' => 400000.00,
            'expected_cash' => 400000.00,
        ]);

        // Simulasikan customer mengajukan reschedule ke slot jam prime sehingga timbul tagihan selisih berstatus PENDING_PAYMENT
        $booking->update(['status' => 'PENDING_PAYMENT', 'court_fee' => 250000.00, 'total_amount' => 250000.00]);
        $order->update(['grand_total' => 250000.00, 'payment_status' => 'PARTIALLY_PAID']);

        // Buat pembayaran selisih reschedule di Shift 2 via adminSettleCashierPayment
        $settleResult = $this->service->adminSettleCashierPayment(
            bookingId: $booking->id,
            paymentMethod: 'CASH',
            amountReceived: 50000.00,
            cashierUser: $this->cashier2
        );

        $this->assertTrue($settleResult['success']);

        // Verifikasi: Payment kedua tercatat ke Shift 2, bukan Shift 1
        $payments = Payment::where('order_id', $order->id)->orderBy('created_at')->get();
        $this->assertCount(2, $payments);

        $this->assertEquals($shift1->id, $payments[0]->pos_shift_id);
        $this->assertEquals($shift2->id, $payments[1]->pos_shift_id);

        // Rekapitulasi Shift 2 harus mencatat penjualan kas Rp 50.000 dari pembayaran selisih tersebut
        $summary2 = $shift2->calculateSummary();
        $this->assertEquals(50000.00, $summary2['total_cash_sales']);
        $this->assertEquals(450000.00, $summary2['expected_cash']);
    }

    public function test_super_admin_can_bypass_shift_guard(): void
    {
        $this->assertNull(PosCashierShift::getActiveShift('PADEL_FRONTDESK'));

        $this->actingAs($this->superAdmin);

        $result = $this->service->processWalkInCheckout(
            customer: $this->customer,
            slots: [
                [
                    'court_id' => $this->court->id,
                    'start_time' => '10:00:00',
                    'end_time' => '11:00:00',
                ],
            ],
            bookingDate: $this->bookingDate,
            equipments: [],
            paymentMethod: 'CASH',
            cashier: $this->superAdmin,
            autoCheckIn: false
        );

        $this->assertTrue($result['success']);
        $this->assertNull($result['order']->pos_shift_id);
    }

    public function test_walkin_edc_payment_records_card_details_in_orchestrator_payload_log(): void
    {
        $shift = PosCashierShift::create([
            'shift_number' => PosCashierShift::generateShiftNumber('PADEL_FRONTDESK'),
            'counter' => 'PADEL_FRONTDESK',
            'status' => 'OPEN',
            'opened_by_id' => $this->cashier->id,
            'opened_at' => Carbon::now('Asia/Jakarta'),
            'starting_cash' => 500000.00,
            'expected_cash' => 500000.00,
        ]);

        $this->actingAs($this->cashier);

        $paymentMeta = [
            'terminal' => 'EDC_BCA',
            'card_type' => 'DEBIT',
            'card_issuer' => 'BCA',
            'card_last_4' => '8842',
            'approval_code' => 'APPR123',
            'trace_number' => 'TRC0098',
            'charged_amount' => 200000.00,
        ];

        $result = $this->service->processWalkInCheckout(
            customer: $this->customer,
            slots: [
                [
                    'court_id' => $this->court->id,
                    'start_time' => '10:00:00',
                    'end_time' => '11:00:00',
                ],
            ],
            bookingDate: $this->bookingDate,
            equipments: [],
            paymentMethod: 'EDC_BCA',
            cashier: $this->cashier,
            autoCheckIn: false,
            paymentMeta: $paymentMeta
        );

        $this->assertTrue($result['success']);

        $payment = Payment::where('order_id', $result['order']->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals($shift->id, $payment->pos_shift_id);
        $this->assertEquals('EDC_BCA', $payment->payment_method);
        $this->assertEquals('CASHIER_POS', $payment->payment_gateway);

        $payloadLog = $payment->payload_log;
        $this->assertIsArray($payloadLog);
        $this->assertArrayHasKey('edc_details', $payloadLog);
        $this->assertEquals('DEBIT', $payloadLog['edc_details']['card_type']);
        $this->assertEquals('BCA', $payloadLog['edc_details']['card_issuer']);
        $this->assertEquals('8842', $payloadLog['edc_details']['card_last_4']);
        $this->assertEquals('APPR123', $payloadLog['edc_details']['approval_code']);
        $this->assertEquals('TRC0098', $payloadLog['edc_details']['trace_number']);
    }

    public function test_walkin_qris_payment_records_rrn_in_orchestrator_payload_log(): void
    {
        $shift = PosCashierShift::create([
            'shift_number' => PosCashierShift::generateShiftNumber('PADEL_FRONTDESK'),
            'counter' => 'PADEL_FRONTDESK',
            'status' => 'OPEN',
            'opened_by_id' => $this->cashier->id,
            'opened_at' => Carbon::now('Asia/Jakarta'),
            'starting_cash' => 500000.00,
            'expected_cash' => 500000.00,
        ]);

        $this->actingAs($this->cashier);

        $paymentMeta = [
            'qris_provider' => 'BCA_QRIS',
            'qris_rrn' => '998877665544',
            'qris_sender_name' => 'Budi Santoso',
        ];

        $result = $this->service->processWalkInCheckout(
            customer: $this->customer,
            slots: [
                [
                    'court_id' => $this->court->id,
                    'start_time' => '11:00:00',
                    'end_time' => '12:00:00',
                ],
            ],
            bookingDate: $this->bookingDate,
            equipments: [],
            paymentMethod: 'QRIS_STATIS',
            cashier: $this->cashier,
            autoCheckIn: false,
            paymentMeta: $paymentMeta
        );

        $this->assertTrue($result['success']);

        $payment = Payment::where('order_id', $result['order']->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals($shift->id, $payment->pos_shift_id);

        $payloadLog = $payment->payload_log;
        $this->assertIsArray($payloadLog);
        $this->assertArrayHasKey('qris_details', $payloadLog);
        $this->assertEquals('BCA_QRIS', $payloadLog['qris_details']['provider']);
        $this->assertEquals('998877665544', $payloadLog['qris_details']['rrn']);
        $this->assertEquals('Budi Santoso', $payloadLog['qris_details']['sender_name']);
    }

    public function test_livewire_edc_and_cash_validation_and_draft_lifecycle(): void
    {
        PosCashierShift::create([
            'shift_number' => PosCashierShift::generateShiftNumber('PADEL_FRONTDESK'),
            'counter' => 'PADEL_FRONTDESK',
            'status' => 'OPEN',
            'opened_by_id' => $this->cashier->id,
            'opened_at' => Carbon::now('Asia/Jakarta'),
            'starting_cash' => 500000.00,
            'expected_cash' => 500000.00,
        ]);

        $this->actingAs($this->cashier);

        $slotKey = "{$this->court->id}_14:00:00";
        $slotData = [
            $slotKey => [
                'court_id' => $this->court->id,
                'court_name' => $this->court->name,
                'start_time' => '14:00:00',
                'end_time' => '15:00:00',
                'time_label' => '14:00 - 15:00',
                'price' => 200000.00,
            ],
        ];

        // 1. Uji Validasi EDC: Last 4 digit kurang dari 4 digit
        Livewire::test(BookOfflineCourt::class)
            ->set('bookingDate', $this->bookingDate)
            ->set('selectedSlots', $slotData)
            ->set('customerMode', 'quick_create')
            ->set('walkInName', 'Budi Santoso')
            ->set('walkInPhone', '081299887766')
            ->set('paymentMethod', 'EDC_BCA')
            ->set('edcLast4', '12')
            ->set('edcApprovalCode', 'APPR123')
            ->set('edcTraceNumber', 'TRC001')
            ->call('submitWalkInBooking');

        $this->assertEquals(0, Order::where('order_type', 'WALK_IN')->count());

        // 2. Uji Validasi Tunai: Uang diterima kurang dari total
        Livewire::test(BookOfflineCourt::class)
            ->set('bookingDate', $this->bookingDate)
            ->set('selectedSlots', $slotData)
            ->set('customerMode', 'quick_create')
            ->set('walkInName', 'Budi Santoso')
            ->set('walkInPhone', '081299887766')
            ->set('paymentMethod', 'CASH')
            ->set('cashReceived', 50000.00)
            ->call('submitWalkInBooking');

        $this->assertEquals(0, Order::where('order_type', 'WALK_IN')->count());

        // 3. Uji Siklus Simpan Draf, Pemulihan (Resume), dan Transisi Layar
        $component = Livewire::test(BookOfflineCourt::class)
            ->set('bookingDate', $this->bookingDate)
            ->set('selectedSlots', $slotData)
            ->set('customerMode', 'quick_create')
            ->set('walkInName', 'Budi Santoso')
            ->set('walkInPhone', '081299887766')
            ->call('proceedToPayment')
            ->assertSet('posStep', 'payment');

        // Cache draf harus terisi
        $draftKey = "pos_walkin_draft:{$this->cashier->id}";
        $this->assertTrue(Cache::has($draftKey));

        // Simulasikan kasir refresh / buka ulang halaman
        $component2 = Livewire::test(BookOfflineCourt::class)
            ->assertSet('hasPendingDraft', true);

        // Resume draf
        $component2->call('resumeDraft')
            ->assertSet('posStep', 'payment')
            ->assertSet('hasPendingDraft', false)
            ->assertCount('selectedSlots', 1);

        // Buang draf
        $component2->call('discardDraft')
            ->assertSet('posStep', 'selection')
            ->assertCount('selectedSlots', 0);

        $this->assertFalse(Cache::has($draftKey));
    }

    /**
     * Test 12: Taksonomi pembayaran DEBIT_CARD dan CREDIT_CARD dengan rincian bank dan jaringan kartu internasional.
     */
    public function test_walkin_debit_and_credit_card_taxonomy_and_validation(): void
    {
        $shift = PosCashierShift::create([
            'shift_number' => 'SFT-20260919-0099',
            'opened_by_id' => $this->cashier->id,
            'counter' => 'PADEL_FRONTDESK',
            'opened_at' => Carbon::now('Asia/Jakarta'),
            'status' => 'OPEN',
            'starting_cash' => 500000.00,
            'expected_cash' => 500000.00,
        ]);

        $this->actingAs($this->cashier);

        $slotData = [
            '10:00:00_1' => [
                'court_id' => $this->court->id,
                'court_name' => $this->court->name,
                'start_time' => '16:00:00',
                'end_time' => '17:00:00',
                'time_label' => '16:00 - 17:00',
                'price' => 200000.00,
            ],
        ];

        // 1. Sukses Pembayaran Kartu Debit via Livewire
        Livewire::test(BookOfflineCourt::class)
            ->set('bookingDate', $this->bookingDate)
            ->set('selectedSlots', $slotData)
            ->set('customerMode', 'quick_create')
            ->set('walkInName', 'Customer Debit')
            ->set('walkInPhone', '081211112222')
            ->call('setPaymentMethod', 'DEBIT_CARD')
            ->assertSet('edcCardType', 'DEBIT')
            ->set('edcTerminal', 'EDC_BCA')
            ->set('edcBank', 'MANDIRI')
            ->set('edcCardNetwork', 'GPN')
            ->set('edcLast4', '9876')
            ->set('edcApprovalCode', 'AUTH8899')
            ->set('edcTraceNumber', 'TRC7766')
            ->call('submitWalkInBooking');

        $orderDebit = Order::where('order_type', 'WALK_IN')->latest('id')->first();
        $this->assertNotNull($orderDebit);
        $paymentDebit = Payment::where('order_id', $orderDebit->id)->first();
        $this->assertEquals('DEBIT_CARD', $paymentDebit->payment_method);
        $this->assertEquals('GPN', $paymentDebit->payload_log['edc_details']['card_network']);
        $this->assertEquals('MANDIRI', $paymentDebit->payload_log['edc_details']['card_issuer']);

        // 2. Sukses Pembayaran Kartu Kredit via Livewire
        $slotDataCredit = [
            '17:00:00_1' => [
                'court_id' => $this->court->id,
                'court_name' => $this->court->name,
                'start_time' => '17:00:00',
                'end_time' => '18:00:00',
                'time_label' => '17:00 - 18:00',
                'price' => 250000.00,
            ],
        ];

        Livewire::test(BookOfflineCourt::class)
            ->set('bookingDate', $this->bookingDate)
            ->set('selectedSlots', $slotDataCredit)
            ->set('customerMode', 'quick_create')
            ->set('walkInName', 'Customer Credit')
            ->set('walkInPhone', '081233334444')
            ->call('setPaymentMethod', 'CREDIT_CARD')
            ->assertSet('edcCardType', 'CREDIT')
            ->set('edcTerminal', 'EDC_MANDIRI')
            ->set('edcBank', 'OVERSEAS')
            ->set('edcCardNetwork', 'VISA')
            ->set('edcLast4', '4321')
            ->set('edcApprovalCode', 'AUTH4455')
            ->set('edcTraceNumber', 'TRC1122')
            ->call('submitWalkInBooking');

        $orderCredit = Order::where('order_type', 'WALK_IN')->latest('id')->first();
        $this->assertNotNull($orderCredit);
        $paymentCredit = Payment::where('order_id', $orderCredit->id)->first();
        $this->assertEquals('CREDIT_CARD', $paymentCredit->payment_method);
        $this->assertEquals('VISA', $paymentCredit->payload_log['edc_details']['card_network']);
        $this->assertEquals('OVERSEAS', $paymentCredit->payload_log['edc_details']['card_issuer']);

        // 3. Verifikasi summary shift memuat debit dan credit
        $summary = $shift->calculateSummary();
        $this->assertEquals(200000.00, $summary['total_debit_sales']);
        $this->assertEquals(300000.00, $summary['total_credit_sales']);
        $this->assertEquals(500000.00, $summary['total_sales']);
    }
}
