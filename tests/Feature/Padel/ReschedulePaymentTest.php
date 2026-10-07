<?php

namespace Tests\Feature\Padel;

use App\Filament\Pages\KelolaPemesanan;
use App\Models\Audit\ActivityLog;
use App\Models\Padel\CourtEquipment;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\ClubFinanceSetting;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\Pos\PosCashierShift;
use App\Models\Pos\Refund;
use App\Models\User;
use App\Services\Finance\TaxAndFeeService;
use App\Services\Padel\PadelBookingService;
use App\Services\Permission\Club61PermissionMatrix;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Reschedule & selisih bayar: benefit member/sponsor ikut pindah, bayar di frontdesk wajib bukti +
 * shift, tagihan ke customer bisa dibayar via Midtrans (dan direkonsiliasi), jadwal lebih murah
 * = selisih hangus (kebijakan PM 30 Sep 2026), dan slot hasil reschedule tidak bisa di-double-book.
 */
class ReschedulePaymentTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'SB-Mid-server-RESCHEDULE';

    protected PadelBookingService $service;

    protected User $admin;

    protected User $customer;

    protected PadelCourt $court;

    protected string $date;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');

        // getSettings() membaca singleton id = 1 — ubah record itu, jangan bikin record baru.
        ClubFinanceSetting::getSettings()->update([
            'is_admin_fee_enabled' => true, 'admin_fee_name' => 'Biaya Layanan', 'admin_fee_type' => 'PERCENTAGE',
            'admin_fee_amount' => 3, 'admin_fee_channels' => 'ALL',
            'is_tax_enabled' => false, 'tax_channels' => 'ALL',
        ]);
        Cache::forget(ClubFinanceSetting::CACHE_KEY);

        $this->service = app(PadelBookingService::class);
        $this->admin = User::factory()->superAdmin()->create(['name' => 'Owner']);
        $this->customer = User::factory()->customer()->create(['name' => 'Andi']);
        $this->court = PadelCourt::create([
            'name' => 'Court Resched',
            'type' => 'INDOOR',
            'hourly_rate_regular' => 200000,
            'hourly_rate_prime' => 300000,
            'is_active' => true,
        ]);
        $this->date = now()->next(Carbon::WEDNESDAY)->format('Y-m-d');
    }

    private function paidBooking(string $start = '10:00', int $hours = 1, float $courtFee = 200000, float $memberDiscount = 0, string $code = 'BK-RS-001'): PadelBooking
    {
        $order = Order::create([
            'order_number' => 'ORD-'.$code,
            'user_id' => $this->customer->id,
            'order_type' => 'ONLINE_BOOKING',
            'subtotal' => $courtFee,
            'grand_total' => $courtFee,
            'payment_status' => 'PAID',
        ]);
        Payment::create([
            'order_id' => $order->id,
            'payment_gateway' => 'MIDTRANS',
            'transaction_id' => 'ORD-'.$code,
            'amount' => $courtFee,
            'payment_method' => 'BCA_VA',
            'status' => 'SUCCESS',
        ]);
        $startAt = Carbon::parse("{$this->date} {$start}");

        return PadelBooking::create([
            'booking_code' => $code,
            'order_id' => $order->id,
            'user_id' => $this->customer->id,
            'court_id' => $this->court->id,
            'booking_date' => $this->date,
            'start_time' => $startAt,
            'end_time' => $startAt->copy()->addHours($hours),
            'court_fee' => $courtFee,
            'member_discount_court' => $memberDiscount,
            'total_amount' => $courtFee,
            'status' => 'PAID',
            'qr_code_hash' => 'QR-'.$code,
        ]);
    }

    private function openShift(): PosCashierShift
    {
        return PosCashierShift::create([
            'shift_number' => 'SFT-PADEL-RS-'.Str::random(4),
            'counter' => 'PADEL_FRONTDESK',
            'status' => 'OPEN',
            'opened_by_id' => $this->admin->id,
            'opened_at' => now(),
            'starting_cash' => 0,
            'expected_cash' => 0,
        ]);
    }

    private function reschedule(PadelBooking $booking, string $start, bool $payNow = false, array $proof = [], string $method = 'QRIS'): array
    {
        return $this->service->adminRescheduleBooking(
            bookingId: $booking->id,
            newCourtId: $this->court->id,
            newDate: $this->date,
            newStartTimeStr: $start,
            reason: 'Permintaan customer',
            adminUser: $this->admin,
            paymentMethod: $method,
            isDeltaPaid: $payNow,
            paymentProof: $proof,
        );
    }

    private function feeFor(float $courtDelta): float
    {
        return (float) app(TaxAndFeeService::class)->calculate($courtDelta, 0, 'ONLINE', 'PADEL')['grand_total'];
    }

    public function test_quota_covered_booking_moved_to_prime_time_is_not_charged_again(): void
    {
        // 2 jam ditanggung penuh kuota member (court_fee 0, diskon = harga normal 400.000).
        $booking = $this->paidBooking('10:00', 2, 0, 400000);

        $result = $this->reschedule($booking, '18:00', payNow: true);

        $this->assertSame(0.0, $result['total_charge']);
        $booking->refresh();
        $this->assertSame('PAID', $booking->status);
        $this->assertEquals(0, $booking->court_fee);
        $this->assertEquals(600000, $booking->member_discount_court, 'Kuota tetap menutup jadwal prime yang baru');
        $this->assertSame(1, Payment::where('order_id', $booking->order_id)->count(), 'Tidak ada tagihan baru');
    }

    public function test_member_percentage_discount_moves_with_the_booking(): void
    {
        // Diskon member 20%: harga normal 200.000 -> bayar 160.000.
        $booking = $this->paidBooking('10:00', 1, 160000, 40000);

        $sameTier = $this->service->getAvailableRescheduleSlots($booking->id, $this->court->id, $this->date);
        $slot11 = collect($sameTier['slots'])->firstWhere('start_time', '11:00');
        $this->assertEquals(0, $slot11['delta'], 'Jam dengan tarif sama tidak boleh menagih ulang diskon member');

        $result = $this->reschedule($booking, '18:00');

        // Prime 300.000 - 20% = 240.000 -> selisih 80.000 (bukan 140.000), + biaya layanan 3%.
        $this->assertEquals(80000, $result['delta']);
        $this->assertEquals(82400, $result['total_charge']);
        $this->assertEquals($this->feeFor(80000), $result['total_charge']);
        $booking->refresh();
        $this->assertEquals(240000, $booking->court_fee);
        $this->assertEquals(60000, $booking->member_discount_court);
    }

    public function test_pay_at_frontdesk_charges_exactly_what_the_modal_showed_with_proof_and_shift(): void
    {
        $booking = $this->paidBooking('16:00', 3, 200000 + 300000 + 300000, 0);
        $shift = $this->openShift();

        $preview = collect($this->service->getAvailableRescheduleSlots($booking->id, $this->court->id, $this->date)['slots'])
            ->firstWhere('start_time', '17:00');
        $this->assertEquals(100000, $preview['delta']);
        $this->assertEquals(3000, $preview['admin_fee_delta']);
        $this->assertEquals(103000, $preview['total_delta']);

        $result = $this->reschedule($booking, '17:00', payNow: true, proof: ['qris_provider' => 'BCA_QRIS', 'qris_rrn' => 'RRN778899']);

        $this->assertEquals($preview['total_delta'], $result['total_charge']);
        $deltaPayment = Payment::where('order_id', $booking->order_id)->where('transaction_id', 'like', 'SUPP-%')->sole();
        $this->assertSame('SUCCESS', $deltaPayment->status);
        $this->assertEquals($preview['total_delta'], $deltaPayment->amount);
        $this->assertSame($shift->id, $deltaPayment->pos_shift_id, 'Uang selisih wajib masuk rekap shift');
        $this->assertSame('RRN778899', $deltaPayment->payload_log['qris_details']['rrn']);

        $booking->refresh();
        $order = Order::find($booking->order_id);
        $this->assertSame('PAID', $booking->status);
        $this->assertNotSame('QR-BK-RS-001', $booking->qr_code_hash);
        $this->assertEquals((float) $order->grand_total, (float) Payment::where('order_id', $order->id)->where('status', 'SUCCESS')->sum('amount'));
    }

    public function test_pay_now_without_shift_or_proof_is_rejected_and_leaves_nothing_behind(): void
    {
        $booking = $this->paidBooking('10:00');

        try {
            $this->reschedule($booking, '18:00', payNow: true, proof: ['qris_rrn' => 'RRN123456']);
            $this->fail('Tanpa shift harus ditolak');
        } catch (HttpException $e) {
            $this->assertStringContainsString('shift', $e->getMessage());
        }

        $this->openShift();
        try {
            $this->reschedule($booking, '18:00', payNow: true, proof: ['qris_rrn' => '12']);
            $this->fail('RRN tidak valid harus ditolak');
        } catch (HttpException $e) {
            $this->assertStringContainsString('RRN', $e->getMessage());
        }

        $booking->refresh();
        $this->assertSame('10:00', $booking->start_time->format('H:i'));
        $this->assertSame('PAID', $booking->status);
        $this->assertNull(Cache::get("padel_lock:{$this->court->id}:{$this->date}:1800"), 'Kunci slot tidak boleh nyangkut');
        $this->assertSame(1, Payment::count());
    }

    public function test_one_payment_proof_cannot_settle_two_transactions(): void
    {
        $this->openShift();
        $first = $this->paidBooking('09:00', 1, 200000, 0, 'BK-RS-A');
        $second = $this->paidBooking('11:00', 1, 200000, 0, 'BK-RS-B');

        $this->reschedule($first, '18:00', payNow: true, proof: ['qris_rrn' => 'RRNSAME01']);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('sudah pernah dipakai');
        $this->reschedule($second, '19:00', payNow: true, proof: ['qris_rrn' => 'RRNSAME01']);
    }

    public function test_billed_delta_keeps_the_new_slot_occupied_even_after_cache_expires(): void
    {
        $booking = $this->paidBooking('10:00');
        $result = $this->reschedule($booking, '18:00');
        $this->assertTrue($result['is_locked']);

        // Simulasikan tagihan yang sudah lama menggantung + cache kunci slot hilang (TTL habis / cache:clear).
        PadelBooking::whereKey($booking->id)->update(['created_at' => now()->subDays(2)]);
        Cache::flush();

        $schedule = $this->getJson("/api/v1/padel/schedule?date={$this->date}")->assertOk()->json('data.courts.0.slots');
        $slots = collect($schedule)->keyBy('local_start');
        $this->assertNotSame('AVAILABLE', $slots['18:00']['status'], 'Slot hasil reschedule tidak boleh tampil kosong');
        $this->assertSame('AVAILABLE', $slots['10:00']['status'], 'Jadwal lama harus kosong lagi');

        $other = User::factory()->customer()->create();
        $this->actingAs($other, 'sanctum')->postJson('/api/v1/padel/hold-slot', [
            'booking_date' => $this->date,
            'slots' => [['court_id' => $this->court->id, 'start_time' => '18:00', 'end_time' => '19:00']],
        ])->assertStatus(409);
    }

    public function test_cannot_reschedule_unpaid_bookings_or_stack_a_second_unpaid_delta(): void
    {
        $booking = $this->paidBooking('10:00');
        $this->reschedule($booking, '18:00');

        try {
            $this->reschedule($booking->fresh(), '19:00');
            $this->fail('Tagihan selisih yang belum lunas tidak boleh ditumpuk');
        } catch (HttpException $e) {
            $this->assertStringContainsString('tagihan selisih', $e->getMessage());
        }

        $cart = PadelBooking::create([
            'booking_code' => 'BK-RS-CART',
            'user_id' => $this->customer->id,
            'court_id' => $this->court->id,
            'booking_date' => $this->date,
            'start_time' => Carbon::parse("{$this->date} 13:00"),
            'end_time' => Carbon::parse("{$this->date} 14:00"),
            'court_fee' => 200000,
            'total_amount' => 200000,
            'status' => 'LOCKED',
        ]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('sudah lunas');
        $this->reschedule($cart, '15:00');
    }

    public function test_cashier_settle_records_only_the_outstanding_delta(): void
    {
        $booking = $this->paidBooking('10:00');
        $result = $this->reschedule($booking, '18:00');
        $this->openShift();

        $this->service->adminSettleSupplementalPayment($booking->id, 'EDC_BCA', $this->admin, [
            'card_type' => 'DEBIT', 'card_last_4' => '1234', 'approval_code' => 'APR123', 'trace_number' => 'TRC456',
        ]);

        $delta = Payment::where('order_id', $booking->order_id)->where('transaction_id', 'like', 'SUPP-%')->sole();
        $this->assertSame('SUCCESS', $delta->status);
        $this->assertEquals($result['total_charge'], $delta->amount, 'Bukan seluruh grand_total order');
        $order = Order::find($booking->order_id);
        $this->assertEquals((float) $order->grand_total, (float) Payment::where('order_id', $order->id)->where('status', 'SUCCESS')->sum('amount'));
        $this->assertSame('PAID', $booking->fresh()->status);
        $this->assertNotNull($booking->fresh()->qr_code_hash);
    }

    public function test_moving_to_a_cheaper_slot_forfeits_the_difference_and_invoice_shows_it(): void
    {
        $booking = $this->paidBooking('18:00', 1, 300000);

        $result = $this->reschedule($booking, '10:00');

        $this->assertEquals(100000, $result['forfeited']);
        $this->assertSame(0, Refund::count());
        $booking->refresh();
        $this->assertEquals(300000, $booking->court_fee);
        $this->assertEquals(100000, $booking->reschedule_forfeited_amount);

        $this->actingAs($this->customer, 'sanctum')
            ->getJson("/api/v1/padel/bookings/{$booking->id}/ticket")
            ->assertOk()
            ->assertJsonPath('data.order_reschedule_forfeited', 100000)
            ->assertJsonPath('data.status', 'PAID');

        // Pindah naik lagi ke prime: sudah bayar 300.000, jadi tidak ada tagihan & tidak ada lagi yang hangus.
        $back = $this->reschedule($booking->fresh(), '19:00');
        $this->assertSame(0.0, $back['total_charge']);
        $this->assertEquals(0, $booking->fresh()->reschedule_forfeited_amount);
    }

    public function test_invoice_lists_paid_reschedule_charge_with_real_payment_method(): void
    {
        $booking = $this->paidBooking('10:00');
        $this->openShift();
        $result = $this->reschedule($booking, '18:00', payNow: true, proof: ['qris_rrn' => 'RRNINV001']);

        $ticket = $this->actingAs($this->customer, 'sanctum')
            ->getJson("/api/v1/padel/bookings/{$booking->id}/ticket")
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $ticket['reschedule_charges']);
        $this->assertSame('SUCCESS', $ticket['reschedule_charges'][0]['status']);
        $this->assertEquals($result['total_charge'], $ticket['reschedule_charges'][0]['amount']);
        $this->assertStringContainsString('Court Resched', $ticket['reschedule_charges'][0]['schedule_before']);
        $this->assertFalse($ticket['has_pending_delta']);
    }

    private function billAndPayOnline(PadelBooking $booking): string
    {
        config(['services.midtrans.server_key' => self::KEY]);
        $this->reschedule($booking, '18:00');

        $retry = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/padel/bookings/{$booking->id}/retry-payment", ['payment_method' => 'QRIS'])
            ->assertOk();

        Payment::query()->toBase()->where('order_id', $booking->order_id)->where('status', 'PENDING')
            ->update(['created_at' => now()->subMinutes(10)]);

        return $retry->json('suffixed_order_id');
    }

    private function midtransSettlement(string $orderId, string $gross): array
    {
        return [
            'status_code' => '200',
            'transaction_status' => 'settlement',
            'order_id' => $orderId,
            'gross_amount' => $gross,
            'payment_type' => 'qris',
            'fraud_status' => 'accept',
            'signature_key' => hash('sha512', $orderId.'200'.$gross.self::KEY),
        ];
    }

    public function test_delta_paid_online_is_reconciled_when_the_webhook_is_lost(): void
    {
        $booking = $this->paidBooking('10:00');
        $suffixed = $this->billAndPayOnline($booking);
        $this->assertStringContainsString('_DELTA_', $suffixed);
        $gross = number_format($this->feeFor(100000), 2, '.', '');

        Http::fake(fn ($request) => str_contains($request->url(), rawurlencode($suffixed))
            ? Http::response($this->midtransSettlement($suffixed, $gross))
            : Http::response(['status_code' => '404']));

        $this->artisan('payment:reconcile-midtrans')->assertSuccessful();

        $this->assertSame('PAID', $booking->fresh()->status);
        $this->assertNotNull($booking->fresh()->qr_code_hash);
        $this->assertSame(0, Payment::where('order_id', $booking->order_id)->where('status', 'PENDING')->count());
    }

    public function test_reconcile_does_not_mistake_the_original_payment_for_the_unpaid_delta(): void
    {
        $booking = $this->paidBooking('10:00');
        $this->billAndPayOnline($booking);

        // Midtrans bilang order awal "settlement" (memang sudah lunas dulu), tagihan selisih belum dibayar.
        Http::fake(fn ($request) => str_contains($request->url(), '/ORD-BK-RS-001/')
            ? Http::response($this->midtransSettlement('ORD-BK-RS-001', '200000.00'))
            : Http::response(['status_code' => '404']));

        $this->artisan('payment:reconcile-midtrans')->assertSuccessful();

        $this->assertSame('LOCKED', $booking->fresh()->status);
        $this->assertSame(1, Payment::where('order_id', $booking->order_id)->where('status', 'PENDING')->count());
    }

    public function test_abandoned_online_delta_session_stays_payable_instead_of_being_cancelled(): void
    {
        $booking = $this->paidBooking('10:00');
        $this->billAndPayOnline($booking);
        Payment::query()->toBase()->where('order_id', $booking->order_id)->where('status', 'PENDING')
            ->update(['created_at' => now()->subHour(), 'updated_at' => now()->subMinutes(40)]);
        Http::fake(['*' => Http::response(['status_code' => '404'])]);

        $this->artisan('payment:reconcile-midtrans')->assertSuccessful();

        $pending = Payment::where('order_id', $booking->order_id)->where('status', 'PENDING')->sole();
        $this->assertSame('CASHIER_POS', $pending->payment_gateway);
        $this->actingAs($this->customer, 'sanctum')
            ->getJson("/api/v1/padel/bookings/{$booking->id}/ticket")
            ->assertJsonPath('data.has_pending_delta', true);
    }

    public function test_retry_never_recharges_a_paid_order_when_its_bill_went_missing(): void
    {
        $booking = $this->paidBooking('10:00');
        $result = $this->reschedule($booking, '18:00');
        Payment::where('order_id', $booking->order_id)->where('status', 'PENDING')->update(['status' => 'FAILED']);

        $retry = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/padel/bookings/{$booking->id}/retry-payment", ['payment_method' => 'QRIS'])
            ->assertOk();

        $this->assertEquals($result['total_charge'], $retry->json('grand_total'), 'Hanya sisa tagihan, bukan seluruh order');
    }

    public function test_rescheduling_twice_into_an_overlapping_slot_works(): void
    {
        $booking = $this->paidBooking('10:00', 2, 400000);

        $this->reschedule($booking, '12:00');
        $second = $this->reschedule($booking->fresh(), '13:00'); // beririsan dengan jadwalnya sendiri (12-14)

        $this->assertTrue($second['success']);
        $this->assertSame('13:00', $booking->fresh()->start_time->format('H:i'));
        $this->assertSame($booking->id, Cache::get("padel_lock:{$this->court->id}:{$this->date}:1300"));
        $this->assertNull(Cache::get("padel_lock:{$this->court->id}:{$this->date}:1200"));
    }

    public function test_cancel_closes_the_unpaid_delta_and_caps_refund_to_money_received(): void
    {
        $booking = $this->paidBooking('10:00');
        $this->reschedule($booking, '18:00');

        // Selisih reschedule yang belum dibayar tidak ikut diajukan: refund = uang yang benar-benar masuk.
        $result = $this->service->requestCancelAndRefund($booking->id, 'CUACA', 'hujan', $this->admin);

        $this->assertEqualsWithDelta(200000, $result['refund_amount'], 0.01);
        $this->assertSame('REFUND_PENDING', $booking->fresh()->status);
        $this->assertSame(0, Payment::where('order_id', $booking->order_id)->where('status', 'PENDING')->count());
    }

    public function test_refund_request_cannot_be_submitted_twice(): void
    {
        $booking = $this->paidBooking('10:00');
        $this->service->requestCancelAndRefund($booking->id, 'CUACA', 'hujan', $this->admin);

        $this->expectException(HttpException::class);
        $this->service->requestCancelAndRefund($booking->id, 'CUACA', 'hujan lagi', $this->admin);
    }

    public function test_rescheduling_into_a_past_hour_is_rejected(): void
    {
        Carbon::setTestNow(Carbon::parse("{$this->date} 15:20"));
        $booking = $this->paidBooking('18:00', 1, 300000);

        $slots = collect($this->service->getAvailableRescheduleSlots($booking->id, $this->court->id, $this->date)['slots']);
        $this->assertNull($slots->firstWhere('start_time', '14:00'));
        $this->assertNotNull($slots->firstWhere('start_time', '15:00'), 'Jam berjalan masih boleh');

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('sudah lewat');
        $this->reschedule($booking, '10:00');
    }

    /** Modal reschedule HANYA mencatat pilihan cara bayar — tidak pernah menerima uang. */
    public function test_reschedule_modal_only_records_payment_choice_and_never_takes_money(): void
    {
        $booking = $this->paidBooking('16:00', 3, 800000);
        $this->openShift();
        $this->actingAs($this->admin);

        Livewire::test(KelolaPemesanan::class)
            ->call('openRescheduleModal', $booking->id)
            ->set('rescheduleStartTime', '17:00')
            ->assertSet('rescheduleQuote.delta', 100000.0)
            ->assertSee('Total Kurang Bayar')
            ->assertSee('Biaya layanan')
            ->assertSee('Bayar di Kasir')
            ->assertSee('Bayar Online (Midtrans)')
            ->assertDontSee('Nomor RRN')
            ->assertDontSee('Approval Code')
            ->set('rescheduleDeltaChannel', 'CASHIER')
            ->call('executeReschedule')
            ->assertSet('showRescheduleModal', false);

        $booking->refresh();
        $this->assertSame('17:00', $booking->start_time->format('H:i'));
        $this->assertSame('LOCKED', $booking->status, 'QR ditahan sampai selisih lunas');
        $bill = Payment::where('transaction_id', 'like', 'SUPP-%')->sole();
        $this->assertSame('PENDING', $bill->status);
        $this->assertEquals(103000, $bill->amount);
        $this->assertSame('CASHIER', $bill->payload_log['preferred_channel']);
    }

    public function test_choosing_online_payment_is_shown_on_customer_invoice(): void
    {
        $booking = $this->paidBooking('10:00');
        $this->actingAs($this->admin);

        Livewire::test(KelolaPemesanan::class)
            ->call('openRescheduleModal', $booking->id)
            ->set('rescheduleStartTime', '18:00')
            ->set('rescheduleDeltaChannel', 'ONLINE')
            ->call('executeReschedule');

        $this->actingAs($this->customer, 'sanctum')
            ->getJson("/api/v1/padel/bookings/{$booking->id}/ticket")
            ->assertJsonPath('data.has_pending_delta', true)
            ->assertJsonPath('data.pending_delta_channel', 'ONLINE');
    }

    /** Pelunasan dieksekusi di POS Walk-In, memakai layar bayar POS yang sama (bukti EDC/QRIS + shift). */
    public function test_cashier_settles_outstanding_bill_from_pos_walk_in(): void
    {
        $booking = $this->paidBooking('09:00', 1, 200000, 0, 'BK-RS-POS');
        $this->reschedule($booking, '21:00');
        $shift = $this->openShift();
        $this->actingAs($this->admin);

        // Alur sama dengan walk-in biasa: klik slot "Bayar" di grid -> customer & nominal otomatis terisi
        // di panel kanan -> Lanjut ke Pembayaran -> layar bayar yang sama -> tombol bayar yang sama.
        $pos = Livewire::test(\App\Filament\Pages\BookOfflineCourt::class)
            ->set('bookingDate', $this->date)
            ->assertSee('Bayar Selisih')
            ->assertSeeHtml("startSettlement('{$booking->id}')")
            ->call('startSettlement', $booking->id)
            ->assertSet('posStep', 'selection')
            ->assertSet('selectedCustomerId', $this->customer->id)
            ->assertSet('selectedCustomerName', 'Andi')
            ->assertSet('settleBill.amount', 103000.0)
            ->assertSee('Selisih yang harus dibayar')
            ->call('proceedToPayment')
            ->assertSet('posStep', 'payment')
            ->call('setPaymentMethod', 'DEBIT_CARD')
            ->set('edcTerminal', 'EDC_MANDIRI')
            ->set('edcLast4', '9876')
            ->set('edcApprovalCode', 'AP9988')
            ->set('edcTraceNumber', 'TR7766')
            ->call('submitWalkInBooking')
            ->assertSet('posStep', 'selection')
            ->assertSet('settleBill', null)
            ->assertSet('selectedCustomerId', null);

        $this->assertSame('PAID', $booking->fresh()->status);
        $this->assertNotNull($booking->fresh()->qr_code_hash);
        $paid = Payment::where('order_id', $booking->order_id)->where('transaction_id', 'like', 'SUPP-%')->sole();
        $this->assertSame('SUCCESS', $paid->status);
        $this->assertSame('EDC_MANDIRI', $paid->payment_method);
        $this->assertSame($shift->id, $paid->pos_shift_id);
        $this->assertSame('9876', $paid->payload_log['edc_details']['card_last_4']);
        $pos->assertDontSeeHtml("startSettlement('{$booking->id}')"); // slot sekarang tampil terisi biasa
    }

    public function test_pos_settlement_rejects_missing_proof_and_requires_shift(): void
    {
        $booking = $this->paidBooking('09:00', 1, 200000, 0, 'BK-RS-NOPROOF');
        $this->reschedule($booking, '21:00');
        $this->actingAs($this->admin); // super_admin pun wajib buka shift untuk menerima pelunasan

        $pos = Livewire::test(\App\Filament\Pages\BookOfflineCourt::class)
            ->call('startSettlement', $booking->id)
            ->call('proceedToPayment')
            ->assertSet('posStep', 'selection');

        $this->openShift();
        $pos->call('proceedToPayment')
            ->assertSet('posStep', 'payment')
            ->call('submitWalkInBooking') // QRIS tanpa RRN
            ->assertSet('posStep', 'payment');

        $this->assertSame('LOCKED', $booking->fresh()->status);
    }

    public function test_clicking_another_slot_cancels_the_selected_bill(): void
    {
        $booking = $this->paidBooking('09:00', 1, 200000, 0, 'BK-RS-SWITCH');
        $this->reschedule($booking, '21:00');
        $this->actingAs($this->admin);

        Livewire::test(\App\Filament\Pages\BookOfflineCourt::class)
            ->call('startSettlement', $booking->id)
            ->call('toggleSlot', $this->court->id, $this->court->name, '10:00:00', '11:00:00', 200000)
            ->assertSet('settleBill', null)
            ->assertSet('selectedCustomerId', null);
    }

    public function test_pay_at_pos_link_from_kelola_pemesanan_opens_the_bill_directly(): void
    {
        $booking = $this->paidBooking('09:00', 1, 200000, 0, 'BK-RS-LINK');
        $this->reschedule($booking, '21:00');
        $this->actingAs($this->admin);

        Livewire::test(KelolaPemesanan::class)
            ->assertSeeHtml('tagihan='.$booking->id);

        Livewire::withQueryParams(['tagihan' => $booking->id])
            ->test(\App\Filament\Pages\BookOfflineCourt::class)
            ->assertSet('settleBill.code', 'BK-RS-LINK')
            ->assertSet('bookingDate', $this->date) // grid langsung ke tanggal jadwalnya
            ->assertSet('selectedCustomerName', 'Andi');
    }

    public function test_settling_requires_permission(): void
    {
        $booking = $this->paidBooking('09:00', 1, 200000, 0, 'BK-RS-PERM');
        $this->reschedule($booking, '21:00');
        $kitchen = User::factory()->kitchen()->create();
        \App\Models\Role::findByName('kitchen', 'web')->givePermissionTo('View:BookOfflineCourt');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($kitchen);

        Livewire::test(\App\Filament\Pages\BookOfflineCourt::class)
            ->call('startSettlement', $booking->id)
            ->assertSet('settleBill', null);
    }

    public function test_reschedule_modal_refuses_unpaid_booking(): void
    {
        $booking = $this->paidBooking('10:00');
        $this->reschedule($booking, '18:00'); // tagihan selisih menggantung -> LOCKED
        $this->actingAs($this->admin);

        Livewire::test(KelolaPemesanan::class)
            ->call('openRescheduleModal', $booking->id)
            ->assertSet('showRescheduleModal', false);
    }

    private function fakeDeltaSettled(string $suffixed): void
    {
        $gross = number_format($this->feeFor(100000), 2, '.', '');
        Http::fake(fn ($request) => str_contains($request->url(), rawurlencode($suffixed))
            ? Http::response($this->midtransSettlement($suffixed, $gross))
            : Http::response(['status_code' => '404']));
    }

    public function test_invoice_page_turns_ticket_active_right_after_customer_pays_delta_online(): void
    {
        $booking = $this->paidBooking('10:00');
        $this->fakeDeltaSettled($this->billAndPayOnline($booking));

        $this->actingAs($this->customer, 'sanctum')
            ->getJson("/api/v1/padel/bookings/{$booking->id}/ticket?verify_payment=1")
            ->assertOk()
            ->assertJsonPath('data.status', 'PAID')
            ->assertJsonPath('data.has_pending_delta', false);
    }

    /** Kasus nyata 30 Sep: customer sudah bayar selisih via Midtrans, lalu kasir men-settle EDC = bayar dua kali. */
    public function test_cashier_settle_detects_customer_already_paid_online_and_does_not_charge_again(): void
    {
        $booking = $this->paidBooking('10:00');
        $this->fakeDeltaSettled($this->billAndPayOnline($booking));
        $this->openShift();

        $result = $this->service->adminSettleSupplementalPayment($booking->id, 'EDC_BCA', $this->admin, []); // tanpa bukti pun tidak perlu

        $this->assertSame('MIDTRANS', $result['settled_via']);
        $this->assertSame('PAID', $booking->fresh()->status);
        $this->assertSame(0, Payment::where('order_id', $booking->order_id)->where('payment_gateway', 'CASHIER_POS')->where('status', 'SUCCESS')->count(), 'Kasir tidak boleh mencatat uang lagi');
        $order = Order::find($booking->order_id);
        $this->assertEquals((float) $order->grand_total, (float) Payment::where('order_id', $order->id)->where('status', 'SUCCESS')->sum('amount'));

        // Layar pelunasan di POS juga tidak terbuka untuk tagihan yang sudah lunas online.
        $this->actingAs($this->admin);
        Livewire::test(\App\Filament\Pages\BookOfflineCourt::class)->call('startSettlement', $booking->id)->assertSet('settleBill', null);
    }

    public function test_cashier_settle_is_blocked_while_customer_is_still_paying_online(): void
    {
        $booking = $this->paidBooking('10:00');
        $suffixed = $this->billAndPayOnline($booking);
        $gross = number_format($this->feeFor(100000), 2, '.', '');
        Http::fake(fn ($request) => str_contains($request->url(), rawurlencode($suffixed))
            ? Http::response(array_merge($this->midtransSettlement($suffixed, $gross), [
                'status_code' => '201', 'transaction_status' => 'pending',
                'signature_key' => hash('sha512', $suffixed.'201'.$gross.self::KEY),
            ]))
            : Http::response(['status_code' => '404']));
        $this->openShift();

        try {
            $this->service->adminSettleSupplementalPayment($booking->id, 'QRIS', $this->admin, ['qris_rrn' => 'RRNBLOCK01']);
            $this->fail('Settle harus ditolak selama customer masih membayar online');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }

        $this->assertSame('LOCKED', $booking->fresh()->status);
        $this->assertSame(0, Payment::where('payment_gateway', 'CASHIER_POS')->where('status', 'SUCCESS')->count());
    }

    public function test_late_online_payment_after_cashier_settle_creates_pending_refund(): void
    {
        $booking = $this->paidBooking('10:00');
        $this->reschedule($booking, '18:00');
        $this->openShift();
        $this->service->adminSettleSupplementalPayment($booking->id, 'QRIS', $this->admin, ['qris_rrn' => 'RRNLATE01']);

        // Webhook Midtrans untuk tagihan yang sama datang belakangan (customer ternyata juga bayar online).
        config(['services.midtrans.server_key' => self::KEY]);
        $orderNumber = Order::find($booking->order_id)->order_number;
        $midtransId = $orderNumber.'_DELTA_1790000000';
        $gross = number_format($this->feeFor(100000), 2, '.', '');
        $this->postJson('/api/v1/padel/webhook/midtrans', [
            'order_id' => $midtransId,
            'status_code' => '200',
            'gross_amount' => $gross,
            'signature_key' => hash('sha512', $midtransId.'200'.$gross.self::KEY),
            'transaction_status' => 'settlement',
            'payment_type' => 'qris',
        ])->assertOk();

        $refund = Refund::where('order_id', $booking->order_id)->sole();
        $this->assertSame('PENDING', $refund->status);
        $this->assertEquals($this->feeFor(100000), $refund->refund_amount);
        $this->assertSame(1, ActivityLog::where('event', 'payment.overpaid')->where('severity', 'CRITICAL')->count());
    }

    public function test_unpaid_delta_blocks_check_in(): void
    {
        $booking = $this->paidBooking('10:00');
        $this->reschedule($booking, '18:00');
        Carbon::setTestNow(Carbon::parse("{$this->date} 17:50"));

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('tagihan selisih');
        $this->service->checkIn($booking->booking_code, $this->admin);
    }

    public function test_admin_sees_midtrans_check_button_for_unpaid_delta(): void
    {
        $booking = $this->paidBooking('10:00');
        $this->reschedule($booking, '18:00');
        $this->actingAs($this->admin);

        Livewire::test(KelolaPemesanan::class)
            ->assertSeeHtml('Cek Status Pembayaran ke Midtrans')
            ->assertSee('Tagihan Selisih');
    }

    public function test_equipment_stock_is_logged_only_when_edited_on_master_data(): void
    {
        $this->actingAs($this->admin);
        $racket = CourtEquipment::create(['name' => 'Raket', 'type' => 'RACKET', 'rental_price' => 50000, 'stock_quantity' => 10, 'is_active' => true]);

        $this->app->instance('request', Request::create('/admin/kelola-pemesanan'));
        $racket->decrement('stock_quantity'); // efek samping check-in
        $this->assertSame(0, ActivityLog::where('event', 'court_equipment.updated')->count());

        $this->app->instance('request', Request::create('/admin/master-data'));
        $racket->update(['stock_quantity' => 20]); // edit manual stok
        $this->assertSame(1, ActivityLog::where('event', 'court_equipment.updated')->count());
    }
}
