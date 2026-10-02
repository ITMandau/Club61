<?php

namespace Tests\Feature\Payment;

use App\Filament\Pages\KelolaPemesanan;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\Pos\Refund;
use App\Models\User;
use App\Services\Padel\PadelBookingService;
use App\Services\Permission\Club61PermissionMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kasus nyata: customer sudah bayar di Midtrans, tapi webhook tidak pernah sampai (URL notifikasi
 * belum didaftarkan / server down). Sistem wajib tetap tahu uangnya masuk — bukan menghanguskan
 * booking dan melepas slot ke orang lain.
 */
class MidtransReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'SB-Mid-server-TEST-KEY';

    protected User $customer;

    protected Order $order;

    protected PadelBooking $booking;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.midtrans.server_key' => self::KEY, 'services.midtrans.is_production' => false]);

        $this->customer = User::factory()->customer()->create();
        $court = PadelCourt::create([
            'name' => 'Court Rekon',
            'type' => 'INDOOR',
            'hourly_rate_regular' => 200000,
            'hourly_rate_prime' => 300000,
            'is_active' => true,
        ]);

        $this->order = Order::create([
            'order_number' => 'ORD-PAD-REKON01',
            'user_id' => $this->customer->id,
            'order_type' => 'ONLINE_BOOKING',
            'subtotal' => 200000,
            'grand_total' => 200000,
            'payment_status' => 'UNPAID',
        ]);

        $start = now()->addDays(2)->setTime(10, 0);
        $this->booking = PadelBooking::create([
            'booking_code' => 'BK-PAD-REKON01',
            'user_id' => $this->customer->id,
            'court_id' => $court->id,
            'order_id' => $this->order->id,
            'booking_date' => $start->format('Y-m-d'),
            'start_time' => $start,
            'end_time' => $start->copy()->addHour(),
            'court_fee' => 200000,
            'total_amount' => 200000,
            'status' => 'PENDING_PAYMENT',
        ]);

        Payment::create([
            'order_id' => $this->order->id,
            'payment_gateway' => 'MIDTRANS',
            'transaction_id' => 'ORD-PAD-REKON01',
            'amount' => 200000,
            'payment_method' => 'BCA_VA',
            'status' => 'PENDING',
        ]);

        $this->ageTo(20);
    }

    /** Mundurkan umur booking & payment (menit) — expiry pending payment = 15 menit. */
    private function ageTo(int $minutes): void
    {
        $at = now()->subMinutes($minutes);
        PadelBooking::whereKey($this->booking->id)->update(['created_at' => $at]);
        Payment::where('order_id', $this->order->id)->update(['created_at' => $at, 'updated_at' => $at]);
    }

    private function midtransStatus(string $orderId, string $transactionStatus, string $gross = '200000.00'): array
    {
        return [
            'status_code' => $transactionStatus === 'settlement' ? '200' : '407',
            'transaction_status' => $transactionStatus,
            'order_id' => $orderId,
            'gross_amount' => $gross,
            'payment_type' => 'bank_transfer',
            'fraud_status' => 'accept',
            'signature_key' => hash('sha512', $orderId.($transactionStatus === 'settlement' ? '200' : '407').$gross.self::KEY),
        ];
    }

    private function fakeMidtrans(array $byOrderId): void
    {
        Http::fake(function ($request) use ($byOrderId) {
            foreach ($byOrderId as $orderId => $body) {
                if (str_contains($request->url(), '/v2/'.rawurlencode($orderId).'/status')) {
                    return Http::response($body);
                }
            }

            return Http::response(['status_code' => '404', 'status_message' => "Transaction doesn't exist."]);
        });
    }

    public function test_expiry_job_settles_instead_of_expiring_when_midtrans_says_paid(): void
    {
        $this->fakeMidtrans(['ORD-PAD-REKON01' => $this->midtransStatus('ORD-PAD-REKON01', 'settlement')]);

        app(PadelBookingService::class)->releaseExpiredLocks();

        $this->assertSame('PAID', $this->booking->fresh()->status);
        $this->assertSame('PAID', $this->order->fresh()->payment_status);
        $this->assertSame('SUCCESS', Payment::where('order_id', $this->order->id)->value('status'));
        $this->assertSame(0, Refund::count());
    }

    public function test_expiry_job_still_expires_when_midtrans_says_expired(): void
    {
        $this->fakeMidtrans(['ORD-PAD-REKON01' => $this->midtransStatus('ORD-PAD-REKON01', 'expire')]);

        app(PadelBookingService::class)->releaseExpiredLocks();

        $this->assertSame('EXPIRED', $this->booking->fresh()->status);
        $this->assertSame('CANCELLED', $this->order->fresh()->payment_status);
    }

    public function test_midtrans_unreachable_holds_booking_up_to_sixty_minutes_then_expires(): void
    {
        Http::fake(fn () => throw new ConnectionException('Midtrans down'));
        $service = app(PadelBookingService::class);

        $service->releaseExpiredLocks();
        $this->assertSame('PENDING_PAYMENT', $this->booking->fresh()->status, 'Jangan hanguskan booking kalau belum tahu status bayarnya');

        $this->ageTo(70);
        \Illuminate\Support\Facades\Cache::flush();
        $service->releaseExpiredLocks();
        $this->assertSame('EXPIRED', $this->booking->fresh()->status);
    }

    public function test_abandoned_order_is_closed_so_periodic_reconcile_stops_asking_midtrans(): void
    {
        $this->fakeMidtrans(['ORD-PAD-REKON01' => $this->midtransStatus('ORD-PAD-REKON01', 'expire')]);

        $this->artisan('payment:reconcile-midtrans')->assertSuccessful();
        $this->assertSame('FAILED', Payment::where('order_id', $this->order->id)->value('status'));

        // Putaran berikutnya (5 menit lagi) tidak boleh menanyakan order ini lagi.
        Http::fake();
        $this->artisan('payment:reconcile-midtrans')->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_unknown_transaction_is_only_closed_after_thirty_minutes(): void
    {
        $this->fakeMidtrans([]); // semua id → 404 (customer belum memilih metode bayar)

        $this->artisan('payment:reconcile-midtrans')->assertSuccessful();
        $this->assertSame('PENDING', Payment::where('order_id', $this->order->id)->value('status'));

        $this->ageTo(40);
        $this->artisan('payment:reconcile-midtrans')->assertSuccessful();
        $this->assertSame('FAILED', Payment::where('order_id', $this->order->id)->value('status'));
    }

    public function test_late_webhook_still_settles_after_payment_was_closed(): void
    {
        $this->fakeMidtrans(['ORD-PAD-REKON01' => $this->midtransStatus('ORD-PAD-REKON01', 'expire')]);
        $this->artisan('payment:reconcile-midtrans')->assertSuccessful();

        $signature = hash('sha512', 'ORD-PAD-REKON01'.'200'.'200000.00'.self::KEY);
        $this->postJson('/api/v1/padel/webhook/midtrans', [
            'order_id' => 'ORD-PAD-REKON01',
            'status_code' => '200',
            'gross_amount' => '200000.00',
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
            'payment_type' => 'bank_transfer',
        ])->assertOk();

        $this->assertSame('PAID', $this->order->fresh()->payment_status);
        $this->assertSame('SUCCESS', Payment::where('order_id', $this->order->id)->value('status'));
    }

    public function test_retried_payment_with_suffixed_order_id_is_found(): void
    {
        Payment::where('order_id', $this->order->id)->update(['payload_log' => [
            'midtrans_order_id' => 'ORD-PAD-REKON01_1727000000',
            'midtrans_order_ids' => ['ORD-PAD-REKON01_1727000000'],
        ]]);
        $this->fakeMidtrans(['ORD-PAD-REKON01_1727000000' => $this->midtransStatus('ORD-PAD-REKON01_1727000000', 'settlement')]);

        $this->artisan('payment:reconcile-midtrans')->assertSuccessful();

        $this->assertSame('PAID', $this->booking->fresh()->status);
        $this->assertSame('PAID', $this->order->fresh()->payment_status);
    }

    public function test_forged_status_response_with_bad_signature_is_ignored(): void
    {
        $forged = $this->midtransStatus('ORD-PAD-REKON01', 'settlement');
        $forged['signature_key'] = str_repeat('0', 128);
        $this->fakeMidtrans(['ORD-PAD-REKON01' => $forged]);

        $this->artisan('payment:reconcile-midtrans')->assertSuccessful();

        $this->assertSame('PENDING_PAYMENT', $this->booking->fresh()->status);
        $this->assertNotSame('PAID', $this->order->fresh()->payment_status);
    }

    public function test_invoice_polling_turns_ticket_paid_without_webhook(): void
    {
        $this->ageTo(3);
        $this->fakeMidtrans(['ORD-PAD-REKON01' => $this->midtransStatus('ORD-PAD-REKON01', 'settlement')]);

        $this->actingAs($this->customer, 'sanctum')
            ->getJson("/api/v1/padel/bookings/{$this->booking->id}/ticket?verify_payment=1")
            ->assertOk()
            ->assertJsonPath('data.status', 'PAID');
    }

    public function test_ticket_endpoint_does_not_call_midtrans_without_verify_flag(): void
    {
        $this->ageTo(3);
        Http::fake();

        $this->actingAs($this->customer, 'sanctum')
            ->getJson("/api/v1/padel/bookings/{$this->booking->id}/ticket")
            ->assertOk()
            ->assertJsonPath('data.status', 'PENDING_PAYMENT');

        Http::assertNothingSent();
    }

    public function test_staff_check_button_confirms_payment_and_is_permission_gated(): void
    {
        Club61PermissionMatrix::syncAllPermissions('web');
        $this->ageTo(3);
        $this->fakeMidtrans(['ORD-PAD-REKON01' => $this->midtransStatus('ORD-PAD-REKON01', 'settlement')]);

        // Bisa buka halaman, tapi izin settle_unpaid_booking-nya dicabut.
        $staff = User::factory()->admin()->create();
        \App\Models\Role::findByName('admin', 'web')->revokePermissionTo('settle_unpaid_booking');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->actingAs($staff);
        Livewire::test(KelolaPemesanan::class)
            ->assertDontSeeHtml('Cek Status Pembayaran ke Midtrans')
            ->call('checkMidtransPayment', $this->booking->id);
        $this->assertSame('PENDING_PAYMENT', $this->booking->fresh()->status);

        $this->actingAs(User::factory()->receptionist()->create());
        Livewire::test(KelolaPemesanan::class)
            ->assertSeeHtml('Cek Status Pembayaran ke Midtrans')
            ->call('checkMidtransPayment', $this->booking->id);

        $this->assertSame('PAID', $this->booking->fresh()->status);
    }
}
