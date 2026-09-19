<?php

namespace Tests\Feature;

use App\Exceptions\SlotConflictException;
use App\Models\Padel\CourtEquipment;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelBookingEquipment;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\Pos\PosCashierShift;
use App\Models\Pos\Refund;
use App\Models\User;
use App\Services\Padel\PadelBookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PadelAdminOverrideTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $customer;
    protected PadelCourt $court1;
    protected PadelCourt $court2;
    protected CourtEquipment $racket;
    protected PadelBookingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PadelBookingService::class);

        $this->admin = User::factory()->create([
            'role' => 'ADMIN',
            'name' => 'Admin Medan',
            'is_active' => true,
        ]);

        $this->customer = User::factory()->create([
            'role' => 'CUSTOMER',
            'name' => 'Andi Wijaya',
            'email' => 'andi@gmail.com',
            'is_active' => true,
        ]);

        $this->court1 = PadelCourt::create([
            'name' => 'Court 1 (Panoramic Pro)',
            'type' => 'INDOOR',
            'hourly_rate_regular' => 200000.00,
            'hourly_rate_prime' => 300000.00,
            'is_active' => true,
        ]);

        $this->court2 = PadelCourt::create([
            'name' => 'Court 2 (Panoramic Elite)',
            'type' => 'INDOOR',
            'hourly_rate_regular' => 200000.00,
            'hourly_rate_prime' => 300000.00,
            'is_active' => true,
        ]);

        $this->racket = CourtEquipment::create([
            'name' => 'Bullpadel Hack 03',
            'type' => 'RACKET',
            'rental_price' => 50000.00,
            'stock_quantity' => 10,
        ]);
    }

    /**
     * Invarian 1: Validasi Anti-Tanggal Lampau.
     */
    public function test_admin_reschedule_rejects_past_date(): void
    {
        $today = now()->addDays(2)->format('Y-m-d');
        $booking = PadelBooking::create([
            'booking_code' => 'BK-TEST-PAST',
            'user_id' => $this->customer->id,
            'court_id' => $this->court1->id,
            'booking_date' => $today,
            'start_time' => Carbon::parse("{$today} 08:00:00"),
            'end_time' => Carbon::parse("{$today} 11:00:00"),
            'court_fee' => 600000.00,
            'total_amount' => 600000.00,
            'status' => 'PAID',
            'qr_code_hash' => 'hash_test_past',
        ]);

        $pastDate = now()->subDays(1)->format('Y-m-d');

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Tanggal reschedule tidak boleh di masa lampau.');

        $this->service->adminRescheduleBooking(
            bookingId: $booking->id,
            newCourtId: $this->court2->id,
            newDate: $pastDate,
            newStartTimeStr: '08:00',
            reason: 'Test tanggal lampau',
            adminUser: $this->admin
        );
    }

    /**
     * Invarian 2: Anti-Jebakan Durasi Multi-Jam & Contiguous Check.
     */
    public function test_admin_reschedule_multi_hour_duration_lock_and_contiguous_check(): void
    {
        $targetDate = now()->addDays(3)->format('Y-m-d');

        // 1. Buat booking 3 jam (08:00 - 11:00)
        $booking = PadelBooking::create([
            'booking_code' => 'BK-TEST-3HRS',
            'user_id' => $this->customer->id,
            'court_id' => $this->court1->id,
            'booking_date' => $targetDate,
            'start_time' => Carbon::parse("{$targetDate} 08:00:00"),
            'end_time' => Carbon::parse("{$targetDate} 11:00:00"),
            'court_fee' => 600000.00,
            'total_amount' => 600000.00,
            'status' => 'PAID',
            'qr_code_hash' => 'initial_hash_3hrs',
        ]);

        // Cek helper getAvailableRescheduleSlots: durasi harus otomatis 3 jam
        $slotInfo = $this->service->getAvailableRescheduleSlots(
            bookingId: $booking->id,
            targetCourtId: $this->court2->id,
            targetDate: $targetDate
        );

        $this->assertEquals(3, $slotInfo['duration_hours']);
        $this->assertNotEmpty($slotInfo['slots']);
        $this->assertStringContainsString('3 Jam', $slotInfo['slots'][0]['label']);

        // 2. Simulasikan ada booking lain yang 'menyelip' di jam 20:00 - 21:00 pada Court 2
        PadelBooking::create([
            'booking_code' => 'BK-BLOCKING-SLOT',
            'user_id' => User::factory()->create()->id,
            'court_id' => $this->court2->id,
            'booking_date' => $targetDate,
            'start_time' => Carbon::parse("{$targetDate} 20:00:00"),
            'end_time' => Carbon::parse("{$targetDate} 21:00:00"),
            'court_fee' => 300000.00,
            'total_amount' => 300000.00,
            'status' => 'PAID',
            'qr_code_hash' => 'blocker_hash',
        ]);

        // Coba reschedule 3 jam ke 19:00 (19:00 - 22:00) yang bertabrakan dengan jam 20:00
        try {
            $this->service->adminRescheduleBooking(
                bookingId: $booking->id,
                newCourtId: $this->court2->id,
                newDate: $targetDate,
                newStartTimeStr: '19:00',
                reason: 'Reschedule tabrakan',
                adminUser: $this->admin
            );
            $this->fail('Harusnya melempar SlotConflictException karena ada slot di tengah 3 jam yang terisi.');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(SlotConflictException::class, $e);
        }

        // 3. Reschedule ke blok 3 jam yang 100% kosong (16:00 - 19:00) -> Harus Sukses!
        $result = $this->service->adminRescheduleBooking(
            bookingId: $booking->id,
            newCourtId: $this->court2->id,
            newDate: $targetDate,
            newStartTimeStr: '16:00',
            reason: 'Pindah ke Court 2',
            adminUser: $this->admin
        );

        $this->assertTrue($result['success']);
        $updated = $booking->fresh();
        $this->assertEquals($this->court2->id, $updated->court_id);
        $this->assertEquals('16:00', $updated->start_time->format('H:i'));
        $this->assertEquals('19:00', $updated->end_time->format('H:i'));
        $this->assertEquals(1, $updated->reschedule_count);
        $this->assertNotEquals('initial_hash_3hrs', $updated->qr_code_hash);
    }

    /**
     * Invarian 3: Flat Equipment Zero-Overhead via Order ID.
     */
    public function test_admin_reschedule_flat_equipment_persists_seamlessly(): void
    {
        $targetDate = now()->addDays(4)->format('Y-m-d');

        $order = Order::create([
            'order_number' => 'ORD-PADEL-100',
            'user_id' => $this->customer->id,
            'subtotal' => 250000.00,
            'grand_total' => 250000.00,
            'payment_status' => 'PAID',
        ]);

        $booking = PadelBooking::create([
            'booking_code' => 'BK-TEST-FLAT',
            'order_id' => $order->id,
            'user_id' => $this->customer->id,
            'court_id' => $this->court1->id,
            'booking_date' => $targetDate,
            'start_time' => Carbon::parse("{$targetDate} 09:00:00"),
            'end_time' => Carbon::parse("{$targetDate} 10:00:00"),
            'court_fee' => 200000.00,
            'equipment_fee' => 50000.00,
            'total_amount' => 250000.00,
            'status' => 'PAID',
            'qr_code_hash' => 'hash_flat',
        ]);

        $equipment = PadelBookingEquipment::create([
            'order_id' => $order->id,
            'booking_id' => $booking->id,
            'equipment_id' => $this->racket->id,
            'quantity' => 1,
            'unit_price' => 50000.00,
            'subtotal' => 50000.00,
        ]);

        // Reschedule lapangan
        $this->service->adminRescheduleBooking(
            bookingId: $booking->id,
            newCourtId: $this->court2->id,
            newDate: $targetDate,
            newStartTimeStr: '11:00',
            reason: 'Ganti jam saja, raket tetap',
            adminUser: $this->admin
        );

        // Record sewa raket tetap ada di order dan subtotalnya tidak terganggu
        $this->assertDatabaseHas('padel_booking_equipments', [
            'id' => $equipment->id,
            'order_id' => $order->id,
            'quantity' => 1,
            'subtotal' => 50000.00,
        ]);
    }

    /**
     * Invarian 4: Eksekusi Price Delta Kurang Bayar (Reguler ke Prime) & Tagihan Menggantung.
     */
    public function test_admin_reschedule_underpayment_creates_supplemental_payment_and_locks_qr(): void
    {
        // Gunakan hari kerja agar 09:00 Reguler dan 19:00 Prime Time
        $weekday = now()->next(Carbon::TUESDAY)->format('Y-m-d');

        $order = Order::create([
            'order_number' => 'ORD-PADEL-UNDERPAY',
            'user_id' => $this->customer->id,
            'subtotal' => 200000.00,
            'grand_total' => 200000.00,
            'payment_status' => 'PAID',
        ]);

        Payment::create([
            'order_id' => $order->id,
            'payment_gateway' => 'MIDTRANS',
            'transaction_id' => 'MID-INIT-1',
            'amount' => 200000.00,
            'payment_method' => 'QRIS',
            'status' => 'SUCCESS',
        ]);

        $booking = PadelBooking::create([
            'booking_code' => 'BK-TEST-UNDERPAY',
            'order_id' => $order->id,
            'user_id' => $this->customer->id,
            'court_id' => $this->court1->id,
            'booking_date' => $weekday,
            'start_time' => Carbon::parse("{$weekday} 09:00:00"),
            'end_time' => Carbon::parse("{$weekday} 10:00:00"),
            'court_fee' => 200000.00,
            'total_amount' => 200000.00,
            'status' => 'PAID',
            'qr_code_hash' => 'valid_hash_before_delta',
        ]);

        // Reschedule dari Reguler (09:00, 200k) ke Prime (19:00, 300k) -> Delta = +100k
        // Kasir memilih OPSI: Tagihan Gantung (isDeltaPaid = false)
        $res = $this->service->adminRescheduleBooking(
            bookingId: $booking->id,
            newCourtId: $this->court1->id,
            newDate: $weekday,
            newStartTimeStr: '19:00',
            reason: 'Pindah ke jam prime',
            adminUser: $this->admin,
            paymentMethod: 'CASH',
            isDeltaPaid: false
        );

        $this->assertEquals(100000.00, $res['delta']);
        $this->assertTrue($res['is_locked']);

        $updated = $booking->fresh();
        $this->assertEquals('LOCKED', $updated->status);
        $this->assertNull($updated->qr_code_hash, 'QR Code wajib ditahan saat kurang bayar belum lunas.');

        // Record supplemental payment harus tercatat di database dengan status PENDING
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'amount' => 100000.00,
            'status' => 'PENDING',
        ]);

        // Order grand_total disesuaikan menjadi 300.000
        $this->assertEquals(300000.00, $order->fresh()->grand_total);

        // QA DEFENSE: Garbage collector TIDAK boleh menyentuh booking reschedule yang memiliki riwayat bayar
        $booking->update(['created_at' => now()->subMinutes(30)]);
        $released = $this->service->releaseExpiredLocks();
        $this->assertEquals(0, $released, 'Garbage collector 10 menit tidak boleh merilis booking reschedule.');
        $this->assertEquals('LOCKED', $booking->fresh()->status);
    }

    /**
     * Invarian 4b: Pelunasan Online / Retry Payment Hanya Menagih Nominal Delta (Bukan Total Pesanan).
     */
    public function test_rescheduled_booking_retry_payment_only_charges_pending_delta(): void
    {
        $weekday = now()->next(Carbon::WEDNESDAY)->format('Y-m-d');

        $order = Order::create([
            'order_number' => 'ORD-PADEL-DELTA-RETRY',
            'user_id' => $this->customer->id,
            'subtotal' => 200000.00,
            'grand_total' => 300000.00,
            'payment_status' => 'PARTIAL',
        ]);

        Payment::create([
            'order_id' => $order->id,
            'payment_gateway' => 'MIDTRANS',
            'transaction_id' => 'MID-INIT-SUCCESS',
            'amount' => 200000.00,
            'payment_method' => 'BCA_VA',
            'status' => 'SUCCESS',
        ]);

        $booking = PadelBooking::create([
            'booking_code' => 'BK-TEST-DELTA-RETRY',
            'order_id' => $order->id,
            'user_id' => $this->customer->id,
            'court_id' => $this->court1->id,
            'booking_date' => $weekday,
            'start_time' => Carbon::parse("{$weekday} 19:00:00"),
            'end_time' => Carbon::parse("{$weekday} 20:00:00"),
            'court_fee' => 300000.00,
            'total_amount' => 300000.00,
            'status' => 'LOCKED',
            'reschedule_count' => 1,
            'qr_code_hash' => null,
        ]);

        Payment::create([
            'order_id' => $order->id,
            'payment_gateway' => 'CASH',
            'transaction_id' => 'SUPP-DELTA-PENDING',
            'amount' => 100000.00,
            'payment_method' => 'CASH',
            'status' => 'PENDING',
        ]);

        // 1. Retry dengan CASH
        $resCash = $this->service->retryPayment($booking->id, 'CASH', $this->customer);
        $this->assertTrue($resCash['success']);
        $this->assertTrue($resCash['is_cash']);
        $this->assertEquals(100000.00, $resCash['grand_total'], 'Nominal CASH harus persis delta 100.000');

        // 2. Data tiket API harus membaca has_pending_delta
        $ticket = $this->service->getTicket($booking->id, $this->customer);
        $this->assertTrue($ticket->has_pending_delta);
        $this->assertEquals(100000.00, $ticket->unpaid_delta);
        $this->assertEquals(200000.00, $ticket->total_paid);
    }

    /**
     * Invarian 5: Pelunasan Tagihan Menggantung (Quick Settle) & Rilis QR Tiket.
     */
    public function test_admin_settle_supplemental_payment_releases_qr(): void
    {
        $date = now()->addDays(5)->format('Y-m-d');

        $order = Order::create([
            'order_number' => 'ORD-PADEL-SETTLE',
            'user_id' => $this->customer->id,
            'subtotal' => 300000.00,
            'grand_total' => 300000.00,
            'payment_status' => 'PARTIAL',
        ]);

        $booking = PadelBooking::create([
            'booking_code' => 'BK-TEST-SETTLE',
            'order_id' => $order->id,
            'user_id' => $this->customer->id,
            'court_id' => $this->court1->id,
            'booking_date' => $date,
            'start_time' => Carbon::parse("{$date} 19:00:00"),
            'end_time' => Carbon::parse("{$date} 20:00:00"),
            'court_fee' => 300000.00,
            'total_amount' => 300000.00,
            'status' => 'LOCKED',
            'qr_code_hash' => null, // Ditahan
        ]);

        Payment::create([
            'order_id' => $order->id,
            'payment_gateway' => 'CASH',
            'transaction_id' => 'SUPP-TEST-123',
            'amount' => 100000.00,
            'payment_method' => 'CASH',
            'status' => 'PENDING',
        ]);

        // Buka shift kasir aktif
        $shift = PosCashierShift::create([
            'shift_number' => 'SFT-PADEL-' . now()->format('Ymd') . '-0001',
            'counter' => 'PADEL_FRONTDESK',
            'status' => 'OPEN',
            'opened_by_id' => $this->admin->id,
            'opened_at' => now(),
            'starting_cash' => 200000.00,
            'expected_cash' => 200000.00,
        ]);

        // Kasir melunasi tagihan saat pemain tiba di lokasi
        $res = $this->service->adminSettleSupplementalPayment(
            bookingId: $booking->id,
            paymentMethod: 'CASH',
            adminUser: $this->admin
        );

        $this->assertTrue($res['success']);
        $updated = $booking->fresh();
        $this->assertEquals('PAID', $updated->status);
        $this->assertNotNull($updated->qr_code_hash, 'QR code harus dirilis saat pelunasan berhasil.');

        $this->assertDatabaseHas('payments', [
            'transaction_id' => 'SUPP-TEST-123',
            'status' => 'SUCCESS',
        ]);
    }

    /**
     * Invarian 6: Eksekusi Price Delta Lebih Bayar (Prime ke Reguler) & Pencatatan Deposit ke Tabel Refunds.
     */
    public function test_admin_reschedule_overpayment_creates_refund_deposit_record(): void
    {
        $weekday = now()->next(Carbon::WEDNESDAY)->format('Y-m-d');

        $order = Order::create([
            'order_number' => 'ORD-PADEL-OVERPAY',
            'user_id' => $this->customer->id,
            'subtotal' => 300000.00,
            'grand_total' => 300000.00,
            'payment_status' => 'PAID',
        ]);

        $initialPayment = Payment::create([
            'order_id' => $order->id,
            'payment_gateway' => 'MIDTRANS',
            'transaction_id' => 'MID-OVERPAY-1',
            'amount' => 300000.00,
            'payment_method' => 'QRIS',
            'status' => 'SUCCESS',
        ]);

        $booking = PadelBooking::create([
            'booking_code' => 'BK-TEST-OVERPAY',
            'order_id' => $order->id,
            'user_id' => $this->customer->id,
            'court_id' => $this->court1->id,
            'booking_date' => $weekday,
            'start_time' => Carbon::parse("{$weekday} 19:00:00"),
            'end_time' => Carbon::parse("{$weekday} 20:00:00"),
            'court_fee' => 300000.00,
            'total_amount' => 300000.00,
            'status' => 'PAID',
            'qr_code_hash' => 'hash_prime_original',
        ]);

        // Pindah dari Prime (19:00, 300k) ke Reguler (09:00, 200k) -> Delta = -100k
        $res = $this->service->adminRescheduleBooking(
            bookingId: $booking->id,
            newCourtId: $this->court1->id,
            newDate: $weekday,
            newStartTimeStr: '09:00',
            reason: 'Member ingin main pagi',
            adminUser: $this->admin
        );

        $this->assertEquals(-100000.00, $res['delta']);
        $this->assertFalse($res['is_locked']);

        // Data refund dicatat ke tabel refunds sebagai saldo deposit member
        $this->assertDatabaseHas('refunds', [
            'order_id' => $order->id,
            'payment_id' => $initialPayment->id,
            'refund_amount' => 100000.00,
            'status' => 'PROCESSED',
        ]);

        $updated = $booking->fresh();
        $this->assertEquals('PAID', $updated->status);
        $this->assertEquals(200000.00, $updated->court_fee);
        $this->assertEquals(200000.00, $order->fresh()->grand_total);
    }

    /**
     * Invarian 7: Admin Cancel & Refund Mematikan QR dan Membebaskan Slot.
     */
    public function test_admin_cancel_and_refund_revokes_qr_and_frees_court_slot(): void
    {
        $date = now()->addDays(6)->format('Y-m-d');

        $order = Order::create([
            'order_number' => 'ORD-PADEL-CANCEL',
            'user_id' => $this->customer->id,
            'subtotal' => 200000.00,
            'grand_total' => 200000.00,
            'payment_status' => 'PAID',
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'payment_gateway' => 'MIDTRANS',
            'transaction_id' => 'MID-CANCEL-1',
            'amount' => 200000.00,
            'payment_method' => 'QRIS',
            'status' => 'SUCCESS',
        ]);

        $booking = PadelBooking::create([
            'booking_code' => 'BK-TEST-CANCEL',
            'order_id' => $order->id,
            'user_id' => $this->customer->id,
            'court_id' => $this->court1->id,
            'booking_date' => $date,
            'start_time' => Carbon::parse("{$date} 10:00:00"),
            'end_time' => Carbon::parse("{$date} 11:00:00"),
            'court_fee' => 200000.00,
            'total_amount' => 200000.00,
            'status' => 'PAID',
            'qr_code_hash' => 'active_qr_before_cancel',
        ]);

        // Eksekusi Admin Cancel & Refund
        $res = $this->service->adminCancelAndRefund(
            bookingId: $booking->id,
            refundAmount: 200000.00,
            refundMethod: 'TUNAI_KASIR',
            reasonCategory: 'SALAH_BAYAR',
            notes: 'Customer salah jam, refund tunai kasir',
            adminUser: $this->admin
        );

        $this->assertTrue($res['success']);
        $updated = $booking->fresh();
        $this->assertEquals('REFUNDED', $updated->status);
        $this->assertNull($updated->qr_code_hash, 'QR code harus dimatikan.');

        // Refund tercatat di tabel refunds
        $this->assertDatabaseHas('refunds', [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'refund_amount' => 200000.00,
            'status' => 'PROCESSED',
        ]);

        // Slot 10:00 - 11:00 harus kembali AVAILABLE pada matriks ketersediaan
        $matrix = $this->service->getScheduleMatrix($date);
        $court1Matrix = collect($matrix['courts'])->firstWhere('court_id', $this->court1->id);
        $slot10 = collect($court1Matrix['slots'])->firstWhere('local_start', '10:00');

        $this->assertEquals('AVAILABLE', $slot10['status'], 'Slot lapangan harus kembali AVAILABLE untuk publik.');
    }

    /**
     * Invarian 8: Livewire Component Filament Kelola Pemesanan Renders & Interacts Correctly.
     */
    public function test_filament_kelola_pemesanan_livewire_page(): void
    {
        $today = now()->addDays(2)->format('Y-m-d');
        $booking = PadelBooking::create([
            'booking_code' => 'BK-TEST-LIVEWIRE',
            'user_id' => $this->customer->id,
            'court_id' => $this->court1->id,
            'booking_date' => $today,
            'start_time' => Carbon::parse("{$today} 08:00:00"),
            'end_time' => Carbon::parse("{$today} 11:00:00"),
            'court_fee' => 600000.00,
            'total_amount' => 600000.00,
            'status' => 'PAID',
            'qr_code_hash' => 'hash_lw',
        ]);

        $this->actingAs($this->admin);

        \Livewire\Livewire::test(\App\Filament\Pages\KelolaPemesanan::class)
            ->assertStatus(200)
            ->assertSee('Kelola Pemesanan &amp; Tiket Masuk', false)
            ->assertDontSee('Note / Keterangan')
            ->set('search', 'BK-TEST-LIVEWIRE')
            ->assertSee('BK-TEST-LIVEWIRE')
            ->set('search', 'NONEXISTENT_TICKET')
            ->assertDontSee('BK-TEST-LIVEWIRE')
            ->assertSee('Tidak ada reservasi yang cocok')
            ->set('search', '')
            ->assertSee('BK-TEST-LIVEWIRE')
            ->set('perPage', 5)
            ->assertSet('perPage', 5)
            ->call('setTab', 'CANCELLED')
            ->assertSee('Note / Keterangan')
            ->call('setTab', 'ALL')
            ->assertDontSee('Note / Keterangan')
            ->call('openRescheduleModal', $booking->id)
            ->assertSet('showRescheduleModal', true)
            ->assertSet('rescheduleDurationHours', 3)
            ->assertSee('Durasi Terkunci: 3 Jam')
            ->call('openCheckInModal', 'BK-TEST-LIVEWIRE')
            ->assertSet('showCheckInModal', true)
            ->assertSet('checkInQuery', 'BK-TEST-LIVEWIRE')
            ->call('closeCheckInModal')
            ->assertSet('showCheckInModal', false);
    }

    /**
     * Invarian 9: Booking System Livewire Page & Live Court Status.
     */
    public function test_booking_system_livewire_page(): void
    {
        $this->actingAs($this->admin);

        \Livewire\Livewire::test(\App\Filament\Pages\BookingSystem::class)
            ->assertStatus(200)
            ->assertSee('Booking System &amp; Monitoring Lapangan', false)
            ->assertSee('Scan QR / Check-In Gate')
            ->call('openCheckInModal')
            ->assertSet('showCheckInModal', true)
            ->call('closeCheckInModal')
            ->assertSet('showCheckInModal', false);
    }

    /**
     * Invarian 10: Analytics PM Financial Report Aggregates Gross, Refund, and Net Revenue.
     */
    public function test_analytics_financial_pm_report(): void
    {
        $this->actingAs($this->admin);

        \Livewire\Livewire::test(\App\Filament\Pages\Analytics::class)
            ->assertStatus(200)
            ->assertSee('Laporan Uang Masuk &amp; Analisis Finansial', false)
            ->assertSee('Total Uang Masuk Kotor (Gross)')
            ->assertSee('Total Refund Dikeluarkan')
            ->assertSee('Pendapatan Bersih (Net Revenue)')
            ->call('setPeriod', 'THIS_MONTH')
            ->assertSet('period', 'THIS_MONTH')
            ->call('setPeriod', 'ALL')
            ->assertSet('period', 'ALL');
    }

    /**
     * Invarian 11: POS Kasir Check-In Endpoint.
     */
    public function test_pos_cashier_checkin_endpoint(): void
    {
        $today = now()->format('Y-m-d');
        $startTime = now()->addMinutes(15);
        $endTime = $startTime->copy()->addHours(1);

        $booking = PadelBooking::create([
            'booking_code' => 'BK-POS-CHECKIN',
            'user_id' => $this->customer->id,
            'court_id' => $this->court1->id,
            'booking_date' => $today,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'court_fee' => 200000.00,
            'total_amount' => 200000.00,
            'status' => 'PAID',
            'qr_code_hash' => 'hash_pos_valid',
        ]);

        $this->actingAs($this->admin);

        // Scan by booking_code
        $response = $this->postJson('/pos/check-in', [
            'code' => 'BK-POS-CHECKIN',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'already_checked_in' => false,
                    'booking_code' => 'BK-POS-CHECKIN',
                ],
            ]);

        $this->assertEquals('CHECKED_IN', $booking->fresh()->status);
        $this->assertNotNull($booking->fresh()->checked_in_at);
    }
}

