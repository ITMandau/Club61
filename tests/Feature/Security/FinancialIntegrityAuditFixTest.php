<?php

namespace Tests\Feature\Security;

use App\Exceptions\SlotConflictException;
use App\Filament\Pages\BookOfflineCourt;
use App\Filament\Pages\PengaturanBiayaPajak;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\ClubFinanceSetting;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\Pos\PosCashierShift;
use App\Models\Role;
use App\Models\User;
use App\Services\Padel\PadelBookingService;
use App\Services\Payment\MidtransService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class FinancialIntegrityAuditFixTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $staffUser;
    protected PadelCourt $court;
    protected PadelBookingService $bookingService;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \App\Services\Permission\Club61PermissionMatrix::syncAllPermissions('web');

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'kitchen', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        $this->customer = User::factory()->customer()->create();

        $this->staffUser = User::factory()->create([
            'role' => 'cashier',
            'is_active' => true,
        ]);
        $this->staffUser->assignRole('cashier');

        $this->court = PadelCourt::create([
            'name' => 'Court Alpha',
            'type' => 'INDOOR',
            'hourly_rate_regular' => 200000.00,
            'hourly_rate_prime' => 300000.00,
            'is_active' => true,
        ]);

        ClubFinanceSetting::firstOrCreate(['id' => 1], [
            'is_admin_fee_enabled' => false,
            'is_tax_enabled' => false,
        ]);

        $this->bookingService = app(PadelBookingService::class);
    }

    /**
     * Test 1: Jalur webhook kedua (/api/v1/payments/webhook) didelegasikan terpusat ke orchestrator
     */
    public function test_secondary_webhook_endpoint_delegates_to_orchestrator(): void
    {
        $dateStr = now()->addDays(2)->format('Y-m-d');
        $orderNumber = 'ORD-SEC-WH-001';

        $booking = PadelBooking::create([
            'court_id' => $this->court->id,
            'user_id' => $this->customer->id,
            'booking_date' => $dateStr,
            'start_time' => Carbon::parse("{$dateStr} 09:00:00"),
            'end_time' => Carbon::parse("{$dateStr} 10:00:00"),
            'status' => 'PENDING_PAYMENT',
            'court_fee' => 200000.00,
            'equipment_fee' => 0.00,
            'total_amount' => 200000.00,
            'booking_code' => 'PAD-WH2-01',
        ]);

        $order = Order::create([
            'order_number' => $orderNumber,
            'user_id' => $this->customer->id,
            'order_type' => 'ONLINE_BOOKING',
            'subtotal' => 200000.00,
            'grand_total' => 200000.00,
            'payment_status' => 'UNPAID',
        ]);
        $booking->update(['order_id' => $order->id]);

        Payment::create([
            'order_id' => $order->id,
            'payment_gateway' => 'MIDTRANS',
            'transaction_id' => $orderNumber,
            'amount' => 200000.00,
            'payment_method' => 'QRIS',
            'status' => 'PENDING',
        ]);

        $serverKey = config('services.midtrans.server_key') ?: 'dummy_server_key';
        config(['services.midtrans.server_key' => $serverKey]);

        $signature = hash('sha512', $orderNumber . '200' . '200000' . $serverKey);

        $payload = [
            'order_id' => $orderNumber,
            'status_code' => '200',
            'gross_amount' => '200000',
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
            'payment_type' => 'qris',
        ];

        $response = $this->postJson('/api/v1/payments/webhook', $payload);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Verifikasi Order dan Booking terupdate via PaymentOrchestratorService
        $this->assertSame('PAID', $order->fresh()->payment_status);
        $this->assertSame('PAID', $booking->fresh()->status);
        $this->assertNotEmpty($booking->fresh()->qr_code_hash);
        $this->assertSame('SUCCESS', Payment::where('order_id', $order->id)->first()->status);
    }

    /**
     * Test 2: Checkout tunai oleh staf wajib ada shift aktif (guard shift check)
     */
    public function test_staff_cash_checkout_strictly_requires_active_shift(): void
    {
        $dateStr = now()->addDays(2)->format('Y-m-d');
        $hold = $this->bookingService->holdBatchSlots([
            [
                'court_id' => $this->court->id,
                'start_time' => '11:00',
                'end_time' => '12:00',
            ],
        ], $dateStr, $this->staffUser);

        $bookingId = $hold['bookings'][0]['id'];

        // Skenario A: Belum ada shift aktif -> Wajib ditolak dengan Exception
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Tidak ada shift kasir yang aktif untuk loket [PADEL_FRONTDESK]. Silakan buka shift terlebih dahulu.');

        $this->bookingService->checkout(
            bookingIds: [$bookingId],
            equipments: [],
            voucherCode: null,
            paymentMethod: 'CASH',
            idempotencyKey: 'IDEM-STAFF-CASH-NO-SHIFT',
            user: $this->staffUser
        );
    }

    /**
     * Test 3: Checkout tunai oleh staf dengan shift aktif berhasil dan mengikat pos_shift_id
     */
    public function test_staff_cash_checkout_with_active_shift_attaches_pos_shift_id(): void
    {
        // Buka shift kasir aktif
        $shift = PosCashierShift::create([
            'shift_number' => 'SHIFT-CASH-TEST-01',
            'counter' => 'PADEL_FRONTDESK',
            'status' => 'OPEN',
            'opened_by_id' => $this->staffUser->id,
            'opened_at' => now(),
            'starting_cash' => 200000.00,
            'expected_cash' => 200000.00,
        ]);

        $dateStr = now()->addDays(2)->format('Y-m-d');
        $hold = $this->bookingService->holdBatchSlots([
            [
                'court_id' => $this->court->id,
                'start_time' => '12:00',
                'end_time' => '13:00',
            ],
        ], $dateStr, $this->staffUser);

        $bookingId = $hold['bookings'][0]['id'];

        $result = $this->bookingService->checkout(
            bookingIds: [$bookingId],
            equipments: [],
            voucherCode: null,
            paymentMethod: 'CASH',
            idempotencyKey: 'IDEM-STAFF-CASH-WITH-SHIFT',
            user: $this->staffUser
        );

        $this->assertTrue($result['success']);
        $this->assertSame('PAID', $result['data']['payment_status']);

        $order = Order::where('order_number', $result['data']['order_id'])->firstOrFail();
        $this->assertSame($shift->id, $order->pos_shift_id, 'Order wajib terikat ke active shift');

        $payment = Payment::where('order_id', $order->id)->firstOrFail();
        $this->assertSame($shift->id, $payment->pos_shift_id, 'Payment wajib terikat ke active shift');
        $this->assertSame('CASHIER_POS', $payment->payment_gateway);
        $this->assertSame('SUCCESS', $payment->status);
    }

    /**
     * Test 4: Whitelist otorisasi manage_tax_and_fees menolak peran kustom tanpa izin eksplisit
     */
    public function test_whitelist_authorization_manage_tax_and_fees_rejects_custom_role_without_permission(): void
    {
        // Buat peran kustom baru: finance_viewer (yang pada logika lama lolos isAdmin blacklist)
        $viewerRole = Role::firstOrCreate(['name' => 'finance_viewer', 'guard_name' => 'web']);

        $viewerUser = User::factory()->create([
            'role' => 'finance_viewer',
            'is_active' => true,
        ]);
        $viewerUser->assignRole('finance_viewer');

        $this->actingAs($viewerUser);

        // Akses method saveSettings() -> Wajib ditolak dengan 403 Forbidden
        try {
            $component = new PengaturanBiayaPajak();
            $component->saveSettings();
            $this->fail('User dengan peran kustom tanpa permission seharusnya gagal 403.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            $this->assertStringContainsString('manage_tax_and_fees', $e->getMessage());
        }

        // Sekarang berikan permission manage_tax_and_fees secara eksplisit
        $viewerUser->givePermissionTo('manage_tax_and_fees');

        // Panggil kembali saveSettings() -> Harus berhasil tanpa 403
        $component = new PengaturanBiayaPajak();
        $component->mount();
        $component->taxRate = 12.00;
        $component->saveSettings();

        $this->assertSame(12.00, (float) ClubFinanceSetting::first()->tax_rate);
    }

    /**
     * Test 5: Pengecekan izin buka dan tutup shift kasir
     */
    public function test_shift_open_and_close_authorization_guards(): void
    {
        $kitchenUser = User::factory()->create([
            'role' => 'kitchen',
            'is_active' => true,
        ]);
        $kitchenUser->assignRole('kitchen');

        $this->actingAs($kitchenUser);

        // User kitchen tanpa open_pos_shift wajib ditolak 403
        $component = new BookOfflineCourt();

        try {
            $component->executeOpenShift();
            $this->fail('Kitchen user tidak boleh membuka shift kasir.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            $this->assertStringContainsString('open_pos_shift', $e->getMessage());
        }

        try {
            $component->executeCloseShift();
            $this->fail('Kitchen user tidak boleh menutup shift kasir.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            $this->assertStringContainsString('close_pos_shift', $e->getMessage());
        }
    }

    /**
     * Test 6: Kuncian atomik jadwal reschedule mencegah bentrok antrean (Anti-TOCTOU)
     */
    public function test_reschedule_atomic_cache_lock_prevents_slot_collision(): void
    {
        $dateStr = now()->addDays(3)->format('Y-m-d');
        $booking = PadelBooking::create([
            'court_id' => $this->court->id,
            'user_id' => $this->customer->id,
            'booking_date' => $dateStr,
            'start_time' => Carbon::parse("{$dateStr} 08:00:00"),
            'end_time' => Carbon::parse("{$dateStr} 09:00:00"),
            'status' => 'PAID',
            'court_fee' => 200000.00,
            'total_amount' => 200000.00,
            'booking_code' => 'PAD-TOCTOU-TEST',
            'qr_code_hash' => 'ORIGINAL-HASH',
        ]);

        $adminUser = User::factory()->create(['role' => 'admin']);
        $adminUser->assignRole('admin');

        // Pasang kuncian cache pada slot target 15:00
        $targetLockKey = "padel_lock:{$this->court->id}:{$dateStr}:1500";
        Cache::put($targetLockKey, 'ANOTHER-TRANSACTION', 600);

        // Reschedule ke jam 15:00 wajib melempar SlotConflictException
        $this->expectException(SlotConflictException::class);
        $this->expectExceptionMessage("Slot {$this->court->name} jam 15:00 sedang dikunci transaksi lain.");

        $this->bookingService->adminRescheduleBooking(
            bookingId: $booking->id,
            newCourtId: $this->court->id,
            newDate: $dateStr,
            newStartTimeStr: '15:00',
            reason: 'Pindah jam sore',
            adminUser: $adminUser
        );
    }
}
