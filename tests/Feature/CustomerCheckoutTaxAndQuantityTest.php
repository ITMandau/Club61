<?php

namespace Tests\Feature;

use App\Models\Padel\CourtEquipment;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\ClubFinanceSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerCheckoutTaxAndQuantityTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected string $customerToken;
    protected PadelCourt $court;
    protected CourtEquipment $racket;
    protected CourtEquipment $ball;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::factory()->create([
            'role' => 'CUSTOMER',
            'is_active' => true,
        ]);
        $this->customerToken = $this->customer->createToken('test-customer')->plainTextToken;

        $this->court = PadelCourt::create([
            'name' => 'Court 1 - Panoramic Indoor',
            'type' => 'INDOOR',
            'hourly_rate_regular' => 450000.00,
            'hourly_rate_prime' => 450000.00,
            'is_active' => true,
        ]);

        $this->racket = CourtEquipment::create([
            'name' => 'Raket Babolat Counter Viper',
            'type' => 'RACKET',
            'rental_price' => 50000.00,
            'stock_quantity' => 20,
        ]);

        $this->ball = CourtEquipment::create([
            'name' => 'Bola Padel Pro (1 Can / 3 Pcs)',
            'type' => 'BALL',
            'rental_price' => 35000.00,
            'stock_quantity' => 50,
        ]);

        Cache::forget(ClubFinanceSetting::CACHE_KEY);
    }

    /**
     * 1. Test Endpoint Publik /api/v1/padel/finance-settings mengembalikan data dinamis
     */
    public function test_public_finance_settings_endpoint_reflects_club_settings(): void
    {
        // Kondisi awal default: pajak & fee nonaktif
        $res1 = $this->getJson('/api/v1/padel/finance-settings');
        $res1->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_tax_enabled', false)
            ->assertJsonPath('data.is_admin_fee_enabled', false);

        // Update pengaturan di database
        $settings = ClubFinanceSetting::firstOrCreate(['id' => 1]);
        $settings->update([
            'is_tax_enabled' => true,
            'tax_name' => 'PB1 Pajak Restoran & Hiburan',
            'tax_type' => 'PERCENTAGE',
            'tax_rate' => 10.00,
            'tax_channels' => 'ALL',
            'is_admin_fee_enabled' => true,
            'admin_fee_name' => 'Biaya Layanan Club 61',
            'admin_fee_type' => 'FIXED',
            'admin_fee_amount' => 5000.00,
            'admin_fee_channels' => 'ONLINE_ONLY',
        ]);
        Cache::forget(ClubFinanceSetting::CACHE_KEY);

        $res2 = $this->getJson('/api/v1/padel/finance-settings');
        $res2->assertStatus(200)
            ->assertJsonPath('data.is_tax_enabled', true)
            ->assertJsonPath('data.tax_name', 'PB1 Pajak Restoran & Hiburan')
            ->assertJsonPath('data.tax_rate', 10)
            ->assertJsonPath('data.is_admin_fee_enabled', true)
            ->assertJsonPath('data.admin_fee_name', 'Biaya Layanan Club 61')
            ->assertJsonPath('data.admin_fee_amount', 5000);
    }

    /**
     * 2. Test Customer Checkout Route renders successfully with clubFinanceSettings
     */
    public function test_customer_checkout_web_view_loads_with_settings(): void
    {
        $response = $this->actingAs($this->customer)
            ->get('/checkout');

        $response->assertStatus(200);
        $response->assertViewHas('clubFinanceSettings');
        $response->assertSee('Equipment Rental &amp; Add-ons', false);
        $response->assertSee('Payment Summary', false);
        // Pastikan 11% VAT hardcoded sudah tidak ada lagi
        $response->assertDontSee('11% VAT (Included)');
    }

    /**
     * 3. Test Checkout API dengan Pengaturan Finansial Nonaktif (Pajak 0, Fee 0)
     */
    public function test_checkout_api_with_disabled_tax_and_fees(): void
    {
        $settings = ClubFinanceSetting::firstOrCreate(['id' => 1]);
        $settings->update([
            'is_tax_enabled' => false,
            'is_admin_fee_enabled' => false,
        ]);
        Cache::forget(ClubFinanceSetting::CACHE_KEY);

        $booking = PadelBooking::create([
            'booking_code' => 'BK-' . strtoupper(Str::random(8)),
            'user_id' => $this->customer->id,
            'court_id' => $this->court->id,
            'booking_date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '14:00',
            'end_time' => '15:00',
            'status' => 'LOCKED',
            'court_fee' => 450000.00,
            'equipment_fee' => 0,
            'total_amount' => 450000.00,
            'expires_at' => now()->addMinutes(10),
        ]);

        // 1 raket (@50.000) -> Subtotal: 500.000, Gateway Fee QRIS: 2.800, Grand Total: 502.800
        $response = $this->actingAs($this->customer)
            ->withHeader('X-Idempotency-Key', 'IDEM-' . uniqid())
            ->postJson('/api/v1/padel/checkout', [
                'booking_ids' => [$booking->id],
                'equipments' => [
                    ['equipment_id' => $this->racket->id, 'quantity' => 1],
                ],
                'payment_method' => 'QRIS',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // Ketika admin fee & pajak dinonaktifkan di panel admin, TIDAK BOLEH ada biaya fee midtrans liar
        $this->assertDatabaseHas('orders', [
            'subtotal' => 500000,
            'tax_amount' => 0,
            'service_charge' => 0, // Admin fee nonaktif -> Rp 0
            'grand_total' => 500000,
        ]);
    }

    /**
     * 4. Test Checkout API supports Equipment Quantity and Centralized Tax & Fee
     */
    public function test_checkout_api_with_multiple_equipment_quantities(): void
    {
        // Aktifkan pajak 10% dan biaya admin Rp 3.000
        $settings = ClubFinanceSetting::firstOrCreate(['id' => 1]);
        $settings->update([
            'is_tax_enabled' => true,
            'tax_name' => 'PB1 Pajak Daerah',
            'tax_type' => 'PERCENTAGE',
            'tax_rate' => 10.00,
            'tax_channels' => 'ALL',
            'is_admin_fee_enabled' => true,
            'admin_fee_name' => 'Biaya Admin',
            'admin_fee_type' => 'FIXED',
            'admin_fee_amount' => 3000.00,
            'admin_fee_channels' => 'ONLINE_ONLY',
        ]);
        Cache::forget(ClubFinanceSetting::CACHE_KEY);

        // Buat booking slot yang di-hold
        $booking = PadelBooking::create([
            'booking_code' => 'BK-' . strtoupper(Str::random(8)),
            'user_id' => $this->customer->id,
            'court_id' => $this->court->id,
            'booking_date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'LOCKED',
            'court_fee' => 450000.00,
            'equipment_fee' => 0,
            'total_amount' => 450000.00,
            'expires_at' => now()->addMinutes(10),
        ]);

        // Customer checkout: 2 raket (@50.000 = 100.000) dan 3 can bola (@35.000 = 105.000)
        // Subtotal = 450.000 + 100.000 + 105.000 = 655.000
        // Pajak 10% = 65.500
        // Admin Fee = 3.000 (mengikuti setting blade admin, tanpa tambahan gateway fee liar)
        // Grand Total = 655.000 + 65.500 + 3.000 = 723.500
        $response = $this->actingAs($this->customer)
            ->withHeader('X-Idempotency-Key', 'IDEM-' . uniqid())
            ->postJson('/api/v1/padel/checkout', [
                'booking_ids' => [$booking->id],
                'equipments' => [
                    ['equipment_id' => $this->racket->id, 'quantity' => 2],
                    ['equipment_id' => $this->ball->id, 'quantity' => 3],
                ],
                'payment_method' => 'QRIS',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // Verifikasi tabel padel_booking_equipments menyimpan kuantitas secara benar
        $this->assertDatabaseHas('padel_booking_equipments', [
            'booking_id' => $booking->id,
            'equipment_id' => $this->racket->id,
            'quantity' => 2,
            'unit_price' => 50000.00,
            'subtotal' => 100000.00,
        ]);

        $this->assertDatabaseHas('padel_booking_equipments', [
            'booking_id' => $booking->id,
            'equipment_id' => $this->ball->id,
            'quantity' => 3,
            'unit_price' => 35000.00,
            'subtotal' => 105000.00,
        ]);

        // Verifikasi Order Items menyimpan kuantitas sewa alat
        $this->assertDatabaseHas('order_items', [
            'reference_id' => $this->racket->id,
            'quantity' => 2,
            'unit_price' => 50000.00,
            'subtotal' => 100000.00,
        ]);

        $this->assertDatabaseHas('order_items', [
            'reference_id' => $this->ball->id,
            'quantity' => 3,
            'unit_price' => 35000.00,
            'subtotal' => 105000.00,
        ]);

        // Verifikasi pesanan dan total finansial murni mengikuti admin setting
        $this->assertDatabaseHas('orders', [
            'subtotal' => 655000,
            'tax_amount' => 65500,
            'service_charge' => 3000, // Admin Fee (3000), tanpa surcharge liar
            'grand_total' => 723500,
        ]);
    }

    /**
     * 5. Test Slot Kadaluwarsa (> 10 Menit) Otomatis Release dan Berstatus AVAILABLE di Schedule Matrix
     */
    public function test_expired_locked_slot_is_auto_released_and_available_in_schedule_matrix(): void
    {
        $targetDate = now()->addDays(2)->format('Y-m-d');

        // Buat booking LOCKED yang dibuat 11 menit lalu (sudah lewat hold duration 10 menit)
        $expiredBooking = PadelBooking::create([
            'booking_code' => 'BK-' . strtoupper(Str::random(8)),
            'user_id' => $this->customer->id,
            'court_id' => $this->court->id,
            'booking_date' => $targetDate,
            'start_time' => "{$targetDate} 09:00:00",
            'end_time' => "{$targetDate} 10:00:00",
            'status' => 'LOCKED',
            'court_fee' => 450000.00,
            'equipment_fee' => 0,
            'total_amount' => 450000.00,
        ]);

        \Illuminate\Support\Facades\DB::table('padel_bookings')
            ->where('id', $expiredBooking->id)
            ->update([
                'created_at' => now()->subMinutes(11),
                'updated_at' => now()->subMinutes(11),
            ]);

        // Ambil schedule matrix untuk target date
        $bookingService = app(\App\Services\Padel\PadelBookingService::class);
        $matrix = $bookingService->getScheduleMatrix($targetDate);

        // Cari slot jam 09:00 - 10:00 pada court tersebut
        $courtSlots = collect($matrix['courts'])->firstWhere('court_id', $this->court->id)['slots'];
        $slot0900 = collect($courtSlots)->firstWhere('local_start', '09:00');

        // Slot harus berstatus AVAILABLE (bukan LOCKED / In Checkout)
        $this->assertNotNull($slot0900);
        $this->assertEquals('AVAILABLE', $slot0900['status'], 'Slot yang waktu lock-nya sudah habis harus otomatis dilepas dan berstatus AVAILABLE');

        // Status booking di database harus sudah ter-update menjadi EXPIRED
        $expiredBooking->refresh();
        $this->assertEquals('EXPIRED', $expiredBooking->status);
    }

    /**
     * 6. Test Slot PENDING_PAYMENT yang melewati batas 15 menit otomatis dilepas
     */
    public function test_expired_pending_payment_slot_is_auto_released_and_available_in_schedule_matrix(): void
    {
        $targetDate = now()->addDays(3)->format('Y-m-d');

        $pendingBooking = PadelBooking::create([
            'booking_code' => 'BK-' . strtoupper(Str::random(8)),
            'user_id' => $this->customer->id,
            'court_id' => $this->court->id,
            'booking_date' => $targetDate,
            'start_time' => "{$targetDate} 14:00:00",
            'end_time' => "{$targetDate} 15:00:00",
            'status' => 'PENDING_PAYMENT',
            'court_fee' => 450000.00,
            'equipment_fee' => 0,
            'total_amount' => 450000.00,
        ]);

        \Illuminate\Support\Facades\DB::table('padel_bookings')
            ->where('id', $pendingBooking->id)
            ->update([
                'created_at' => now()->subMinutes(16),
                'updated_at' => now()->subMinutes(16),
            ]);

        $bookingService = app(\App\Services\Padel\PadelBookingService::class);
        $matrix = $bookingService->getScheduleMatrix($targetDate);

        $courtSlots = collect($matrix['courts'])->firstWhere('court_id', $this->court->id)['slots'];
        $slot1400 = collect($courtSlots)->firstWhere('local_start', '14:00');

        $this->assertNotNull($slot1400);
        $this->assertEquals('AVAILABLE', $slot1400['status'], 'Slot PENDING_PAYMENT yang melebihi batas waktu 15 menit harus otomatis AVAILABLE');

        $pendingBooking->refresh();
        $this->assertEquals('EXPIRED', $pendingBooking->status);
    }

    /**
     * 7. Test Booking Masa Lampau (PAID tapi sudah selesai main) otomatis tersinkronisasi
     */
    public function test_past_paid_booking_is_synced_and_slot_freed(): void
    {
        $pastDate = now()->subDays(1)->format('Y-m-d');

        $pastBooking = PadelBooking::create([
            'booking_code' => 'BK-' . strtoupper(Str::random(8)),
            'user_id' => $this->customer->id,
            'court_id' => $this->court->id,
            'booking_date' => $pastDate,
            'start_time' => "{$pastDate} 10:00:00",
            'end_time' => "{$pastDate} 11:00:00",
            'status' => 'PAID',
            'court_fee' => 450000.00,
            'equipment_fee' => 0,
            'total_amount' => 450000.00,
        ]);

        $bookingService = app(\App\Services\Padel\PadelBookingService::class);
        // Membuka schedule matriks tanggal apapun akan menjalankan syncExpiredAndCompletedBookings
        $matrix = $bookingService->getScheduleMatrix(now()->format('Y-m-d'));

        $pastBooking->refresh();
        $this->assertEquals('EXPIRED', $pastBooking->status, 'Booking masa lampau yang tidak check-in otomatis berstatus EXPIRED (No-Show)');
    }
}
