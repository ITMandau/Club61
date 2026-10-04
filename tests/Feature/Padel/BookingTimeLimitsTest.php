<?php

namespace Tests\Feature\Padel;

use App\Filament\Pages\MetodePembayaranOnline;
use App\Models\Audit\ActivityLog;
use App\Models\Padel\BookingTimeSetting;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\Order;
use App\Models\User;
use App\Services\Padel\BookingTimeService;
use App\Services\Padel\PadelBookingService;
use App\Services\Payment\MidtransService;
use App\Services\Payment\PaymentOrchestratorService;
use App\Services\Permission\Club61PermissionMatrix;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Waktu tahan slot & batas bayar dari pengaturan admin, disimpan per booking. Dulu booking dihanguskan 15 menit
 * sejak slot DITAHAN sementara sesi Midtrans berlaku 15 menit sejak KLIK BAYAR (customer yang bayar di menit ke-9
 * kehilangan slotnya padahal halaman bayar masih berlaku), dan bayar ulang memberi sesi Midtrans 15 menit baru.
 */
class BookingTimeLimitsTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'SB-Mid-server-TIMES';

    protected User $customer;

    protected PadelCourt $court;

    protected string $date;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');
        $this->customer = User::factory()->customer()->create();
        $this->court = PadelCourt::create([
            'name' => 'Court Time', 'type' => 'INDOOR', 'hourly_rate_regular' => 200000, 'hourly_rate_prime' => 300000, 'is_active' => true,
        ]);
        $this->date = now()->addDays(3)->format('Y-m-d');
    }

    private function setTimes(int $hold, int $window): void
    {
        BookingTimeSetting::query()->update(['slot_hold_minutes' => $hold, 'payment_window_minutes' => $window]);
        app(BookingTimeService::class)->flush();
    }

    /** Midtrans sungguhan (bukan token mock) — Snap & Status API dipalsukan. */
    private function realMidtrans(): void
    {
        $this->app['env'] = 'staging';
        config(['services.midtrans.server_key' => self::KEY, 'services.midtrans.is_production' => false]);
        Http::fake([
            'app.sandbox.midtrans.com/*' => Http::response(['token' => 'snap-'.Str::random(6), 'redirect_url' => 'https://snap'], 201),
            // Customer belum memilih metode di Snap → Midtrans belum kenal transaksinya.
            'api.sandbox.midtrans.com/*' => Http::response(['status_code' => '404', 'status_message' => 'Transaction doesn\'t exist.'], 404),
        ]);
    }

    private function hold(): PadelBooking
    {
        $id = $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/padel/hold-slot', [
            'booking_date' => $this->date,
            'slots' => [['court_id' => $this->court->id, 'start_time' => '10:00', 'end_time' => '11:00']],
        ])->assertCreated()->json('data.bookings.0.id');

        return PadelBooking::findOrFail($id);
    }

    private function checkout(PadelBooking $booking): void
    {
        $this->actingAs($this->customer, 'sanctum')->withHeader('X-Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/padel/checkout', ['booking_ids' => [$booking->id], 'payment_method' => 'QRIS'])
            ->assertOk();
    }

    private function snapExpiryDurations(): array
    {
        return Http::recorded()
            ->filter(fn ($pair) => str_contains($pair[0]->url(), 'snap/v1/transactions'))
            ->map(fn ($pair) => $pair[0]['expiry']['duration'])
            ->values()->all();
    }

    private function release(): void
    {
        app(PadelBookingService::class)->releaseExpiredLocks();
    }

    public function test_hold_duration_follows_the_admin_setting(): void
    {
        $this->setTimes(7, 15);
        Carbon::setTestNow(now()->startOfMinute());

        $response = $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/padel/hold-slot', [
            'booking_date' => $this->date,
            'slots' => [['court_id' => $this->court->id, 'start_time' => '10:00', 'end_time' => '11:00']],
        ])->assertCreated()->assertJsonPath('data.hold_seconds_remaining', 420);
        $booking = PadelBooking::findOrFail($response->json('data.bookings.0.id'));
        $this->assertTrue($booking->expires_at->equalTo(now()->addMinutes(7)));

        Carbon::setTestNow(now()->addMinutes(6));
        $this->release();
        $this->assertSame('LOCKED', $booking->fresh()->status);

        Carbon::setTestNow(now()->addMinutes(2));
        $this->release();
        $this->assertSame('EXPIRED', $booking->fresh()->status);
    }

    public function test_payment_deadline_starts_when_the_customer_clicks_pay(): void
    {
        $this->setTimes(10, 15);
        $this->realMidtrans();
        Carbon::setTestNow(now()->startOfMinute());
        $booking = $this->hold();

        // Klik bayar di menit ke-9 (hampir habis waktu tahan).
        Carbon::setTestNow(now()->addMinutes(9));
        $this->checkout($booking);
        $booking->refresh();
        $this->assertSame('PENDING_PAYMENT', $booking->status);
        $this->assertTrue($booking->expires_at->equalTo(now()->addMinutes(15)));
        $this->assertSame([15], $this->snapExpiryDurations(), 'batas bayar yang sama dikirim ke Midtrans');

        // Menit ke-20 sejak hold: dulu sudah dihanguskan (15 menit sejak hold), padahal sesi Midtrans masih berlaku.
        Carbon::setTestNow(now()->addMinutes(11));
        $this->release();
        $this->assertSame('PENDING_PAYMENT', $booking->fresh()->status);

        // Lewat batas bayar tapi masih dalam jeda notifikasi → belum dilepas.
        Carbon::setTestNow(now()->addMinutes(6));
        $this->release();
        $this->assertSame('PENDING_PAYMENT', $booking->fresh()->status);

        // Lewat batas bayar + jeda → dilepas.
        Carbon::setTestNow(now()->addMinutes(2));
        $this->release();
        $this->assertSame('EXPIRED', $booking->fresh()->status);
    }

    public function test_retrying_payment_does_not_extend_the_deadline(): void
    {
        $this->setTimes(10, 15);
        $this->realMidtrans();
        Carbon::setTestNow(now()->startOfMinute());
        $booking = $this->hold();
        $this->checkout($booking);
        $deadline = $booking->fresh()->expires_at;

        // Ganti metode di menit ke-10: sesi Midtrans baru hanya diberi sisa 5 menit.
        Carbon::setTestNow(now()->addMinutes(10));
        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/padel/bookings/{$booking->id}/retry-payment", ['payment_method' => 'BCA_VA'])
            ->assertOk();
        $this->assertSame([15, 5], $this->snapExpiryDurations());
        $this->assertTrue($booking->fresh()->expires_at->equalTo($deadline), 'batas bayar booking tidak berubah');

        // Sisa < 2 menit → ditolak, tidak membuat sesi Midtrans baru.
        Carbon::setTestNow($deadline->copy()->subSeconds(90));
        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/padel/bookings/{$booking->id}/retry-payment", ['payment_method' => 'QRIS'])
            ->assertStatus(422);
        $this->assertCount(2, $this->snapExpiryDurations());
    }

    public function test_settings_changes_only_apply_to_new_bookings(): void
    {
        $this->setTimes(10, 15);
        Carbon::setTestNow(now()->startOfMinute());
        $booking = $this->hold();

        $this->setTimes(5, 15);
        Carbon::setTestNow(now()->addMinutes(6));
        $this->release();
        $this->assertSame('LOCKED', $booking->fresh()->status, 'hold yang sedang berjalan tetap 10 menit');
    }

    public function test_admin_can_change_the_times_with_validation_and_audit(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);

        Livewire::test(MetodePembayaranOnline::class)
            ->callAction('bookingTimes', ['slot_hold_minutes' => 3, 'payment_window_minutes' => 20])
            ->assertHasActionErrors(['slot_hold_minutes']);
        $this->assertSame(10, app(BookingTimeService::class)->holdMinutes());

        Livewire::test(MetodePembayaranOnline::class)
            ->callAction('bookingTimes', ['slot_hold_minutes' => 8, 'payment_window_minutes' => 20])
            ->assertHasNoActionErrors();

        $this->app->forgetInstance(BookingTimeService::class);
        $this->assertSame(8, app(BookingTimeService::class)->holdMinutes());
        $this->assertSame(20, app(BookingTimeService::class)->paymentWindowMinutes());
        $this->assertSame(1, ActivityLog::where('subject_type', 'BookingTimeSetting')->count());
    }

    public function test_booking_times_need_their_own_permission(): void
    {
        // Boleh kelola metode bayar ≠ boleh atur batas waktu: izinnya terpisah.
        $kitchen = User::factory()->kitchen()->create();
        $role = \App\Models\Role::findByName('kitchen', 'web');
        $role->givePermissionTo(['View:MetodePembayaranOnline', 'manage_online_payment_methods']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($kitchen);

        Livewire::test(MetodePembayaranOnline::class)
            ->assertActionHidden('bookingTimes')
            ->call('saveBookingTimes', ['slot_hold_minutes' => 30, 'payment_window_minutes' => 60])
            ->assertForbidden();
        $this->assertSame(15, app(BookingTimeService::class)->paymentWindowMinutes());

        $role->givePermissionTo('manage_booking_time_limits');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $kitchen->unsetRelation('roles')->unsetRelation('permissions');

        Livewire::test(MetodePembayaranOnline::class)
            ->assertActionVisible('bookingTimes')
            ->callAction('bookingTimes', ['slot_hold_minutes' => 12, 'payment_window_minutes' => 20])
            ->assertHasNoActionErrors();
        $this->app->forgetInstance(BookingTimeService::class);
        $this->assertSame(20, app(BookingTimeService::class)->paymentWindowMinutes());
    }

    public function test_booking_time_permission_is_super_admin_only_by_default(): void
    {
        $this->assertContains('manage_booking_time_limits', \App\Services\Permission\Club61PermissionMatrix::BACKDOOR_PERMISSIONS);
        $this->assertNotContains('manage_booking_time_limits', \App\Services\Permission\Club61PermissionMatrix::defaultRolePermissions('admin'));
        $this->assertContains('manage_booking_time_limits', \App\Services\Permission\Club61PermissionMatrix::defaultRolePermissions('super_admin'));
    }

    public function test_card_capture_is_paid_only_when_fraud_status_is_accept(): void
    {
        $this->assertSame('PAID', MidtransService::normalizeStatus('capture', 'accept'));
        $this->assertSame('PAID', MidtransService::normalizeStatus('capture', null));
        $this->assertSame('CHALLENGE', MidtransService::normalizeStatus('capture', 'challenge'));
        $this->assertSame('CHALLENGE', MidtransService::normalizeStatus('capture', 'deny'));
    }

    public function test_webhook_replies_503_on_unexpected_errors_so_midtrans_retries(): void
    {
        config(['services.midtrans.server_key' => self::KEY]);
        Order::create(['order_number' => 'ORD-503', 'user_id' => $this->customer->id, 'order_type' => 'ONLINE_BOOKING', 'subtotal' => 100000, 'grand_total' => 100000, 'payment_status' => 'UNPAID']);
        $this->mock(PaymentOrchestratorService::class)->shouldReceive('markOrderAsPaid')->andThrow(new \RuntimeException('Deadlock'));

        $this->postJson('/api/v1/padel/webhook/midtrans', [
            'order_id' => 'ORD-503', 'status_code' => '200', 'gross_amount' => '100000.00',
            'signature_key' => hash('sha512', 'ORD-503'.'200'.'100000.00'.self::KEY),
            'transaction_status' => 'settlement', 'payment_type' => 'qris',
        ])->assertStatus(503);
    }
}
