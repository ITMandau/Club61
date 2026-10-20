<?php

namespace Tests\Feature\Pos;

use App\Filament\Pages\BookOfflineCourt;
use App\Models\Padel\CourtEquipment;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\Pos\PaymentGatewaySetting;
use App\Models\Pos\PosCashierShift;
use App\Models\User;
use App\Services\Padel\PadelBookingService;
use App\Services\Payment\Doku\DokuCheckoutService;
use App\Services\Payment\Doku\DokuPosQrisService;
use App\Services\Payment\Doku\DokuSessionGuard;
use App\Services\Payment\OnlineGatewayRouter;
use App\Services\Pos\PosAutoPayment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Modul 27 Tahap 3 — "Bayar Otomatis" kasir lewat DOKU Checkout (mode pop-up resmi: payment.url + ?view=iframe).
 * Semua HTTP ke DOKU di-fake (tanpa kunci asli).
 */
class DokuQrisPosTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;

    protected PadelCourt $court;

    protected CourtEquipment $ball;

    protected PosCashierShift $shift;

    protected string $bookingDate;

    /** Status per invoice yang dijawab API status palsu. */
    protected array $statuses = [];

    protected bool $cancelWorks = true;

    protected array $cancelled = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse(now()->addDays(3)->next(Carbon::WEDNESDAY)->format('Y-m-d').' 10:00:00'));

        \App\Services\Permission\Club61PermissionMatrix::syncAllPermissions('web');
        config([
            'services.doku.enabled' => true,
            'services.doku.client_id' => 'BRN-0001-TEST',
            'services.doku.secret_key' => 'SK-test-secret-doku',
            'services.doku.is_production' => false,
            'services.doku.notification_url' => null,
        ]);
        PaymentGatewaySetting::query()->update(['pos_qris_gateway' => 'DOKU']);
        app(OnlineGatewayRouter::class)->flush();
        $this->fakeDoku();

        $this->cashier = User::factory()->cashier()->create(['is_active' => true]);
        $this->cashier->givePermissionTo(['View:BookOfflineCourt', 'process_walkin_booking']);
        $this->court = PadelCourt::create(['name' => 'Court DOKU QR', 'type' => 'INDOOR', 'hourly_rate_regular' => 300000, 'hourly_rate_prime' => 450000, 'is_active' => true]);
        $this->ball = CourtEquipment::create(['name' => 'Bola', 'type' => 'BALL', 'rental_price' => 35000, 'stock_quantity' => 5]);
        $this->bookingDate = now()->format('Y-m-d');
        $this->shift = PosCashierShift::create([
            'shift_number' => 'SFT-DK-0001', 'counter' => 'PADEL_FRONTDESK', 'status' => 'OPEN',
            'opened_by_id' => $this->cashier->id, 'opened_at' => now(), 'starting_cash' => 0, 'expected_cash' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function fakeDoku(): void
    {
        Http::fake(function (HttpRequest $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);

            if ($path === DokuCheckoutService::CHECKOUT_PATH) {
                return Http::response(['message' => ['SUCCESS'], 'response' => ['payment' => [
                    'token_id' => 'TOKEN-'.Str::random(6), 'url' => 'https://sandbox.doku.com/checkout-link-v2/TESTPOS',
                    'expired_date' => now()->addMinutes(15)->timezone('Asia/Jakarta')->format('YmdHis'),
                ]]]);
            }
            if (str_starts_with($path, DokuCheckoutService::STATUS_PATH)) {
                $invoice = rawurldecode(substr($path, strlen(DokuCheckoutService::STATUS_PATH)));
                $bill = DokuSessionGuard::findBill($invoice);
                $status = $this->statuses[$invoice] ?? 'PENDING';

                return Http::response([
                    'order' => ['invoice_number' => $invoice, 'amount' => $bill ? (DokuSessionGuard::session($bill, $invoice)['amount'] ?? 0) : 0, 'status' => $status === 'EXPIRED' ? 'ORDER_EXPIRED' : 'ORDER_GENERATED'],
                    'transaction' => ['status' => $status, 'date' => '2026-10-19T03:00:00Z'],
                    'service' => ['id' => 'QRIS'], 'channel' => ['id' => 'QRIS'],
                ]);
            }
            if ($path === DokuCheckoutService::CANCEL_PATH) {
                $this->cancelled[] = $request->data()['order']['invoice_number'];
                if ($this->cancelWorks) {
                    $this->statuses[$request->data()['order']['invoice_number']] = 'EXPIRED';
                }

                return Http::response(['message' => ['SUCCESS']]);
            }

            return Http::response([], 404);
        });
    }

    private function checkoutQr(array $equipments = []): array
    {
        $service = app(PadelBookingService::class);

        return $service->processWalkInCheckout(
            customer: $service->findOrCreateWalkInCustomer(name: 'Budi DOKU', phone: '081234567891'),
            slots: [['court_id' => $this->court->id, 'start_time' => '14:00:00', 'end_time' => '15:00:00']],
            bookingDate: $this->bookingDate,
            equipments: $equipments,
            paymentMethod: 'QRIS_MIDTRANS',
            cashier: $this->cashier,
            paymentMeta: ['qris_provider' => DokuPosQrisService::PROVIDER],
        );
    }

    public function test_cashier_qr_opens_a_doku_popup_session_tied_to_the_shift(): void
    {
        $pending = $this->checkoutQr()['pending_qris'];

        $this->assertSame('DOKU', $pending['gateway']);
        $this->assertNull($pending['snap_token']);
        // Mode pop-up resmi DOKU Checkout (yang dilakukan jokul-checkout-1.0.0.js).
        $this->assertSame('https://sandbox.doku.com/checkout-link-v2/TESTPOS?view=iframe', $pending['frame_url']);
        $this->assertTrue($pending['sandbox']);

        $payment = Payment::findOrFail($pending['payment_id']);
        $this->assertSame('DOKU', $payment->payment_gateway);
        $this->assertSame('PENDING', $payment->status);
        $this->assertSame($this->shift->id, $payment->pos_shift_id);
        $this->assertSame([$payment->transaction_id], DokuSessionGuard::sessionsOf($payment), 'sesi tercatat format DOKU → webhook & rekonsiliasi online ikut menangani');
        $this->assertSame('DOKU_QRIS', $payment->payload_log['qris_details']['provider']);

        // Satu kanal QRIS, tanpa pengalihan otomatis (kartu kasir ditutup oleh layar kasir sendiri).
        Http::assertSent(fn (HttpRequest $r) => parse_url($r->url(), PHP_URL_PATH) === DokuCheckoutService::CHECKOUT_PATH
            && $r['payment']['payment_method_types'] === ['QRIS']
            && $r['order']['auto_redirect'] === false
            && $r['order']['amount'] === 300000
            && $r['order']['invoice_number'] === $payment->transaction_id);
        $this->assertSame('PENDING_PAYMENT', PadelBooking::where('order_id', $payment->order_id)->value('status'));
    }

    public function test_paid_doku_qr_completes_on_the_cashier_screen_and_stays_in_the_shift(): void
    {
        $this->actingAs($this->cashier);
        $component = Livewire::test(BookOfflineCourt::class)
            ->set('bookingDate', $this->bookingDate)
            ->set('selectedSlots', ["{$this->court->id}_14:00:00" => [
                'court_id' => $this->court->id, 'court_name' => $this->court->name, 'start_time' => '14:00:00',
                'end_time' => '15:00:00', 'time_label' => '14:00 - 15:00', 'price' => 300000.00,
            ]])
            ->set('customerMode', 'quick_create')
            ->set('walkInName', 'Budi DOKU')
            ->set('walkInPhone', '081299887700')
            ->set('paymentMethod', 'QRIS')
            ->call('submitWalkInBooking')
            ->assertSeeHtml('data-pos-autopay="doku"')
            ->assertSeeHtml('?view=iframe')
            // Iframe tanpa allow-top-navigation: halaman DOKU tidak bisa memindahkan layar kasir.
            ->assertSeeHtml('sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-popups-to-escape-sandbox allow-downloads"')
            ->assertDontSeeHtml('jokul-checkout');

        $pending = $component->get('pendingQris');
        $component->call('pollPendingQris')->assertSet('pendingQris.payment_id', $pending['payment_id']);

        // Customer membayar: API status DOKU menyatakan SUCCESS → layar kasir menutup kartu & mencetak struk.
        $payment = Payment::findOrFail($pending['payment_id']);
        $this->statuses[$payment->transaction_id] = 'SUCCESS';
        Carbon::setTestNow(now()->addSeconds(6)); // hasil cek status DOKU disimpan 5 detik (tidak menembak DOKU tiap poll)
        $component->call('pollPendingQris')
            ->assertSet('pendingQris', null)
            ->assertSet('posStep', 'receipt');

        $payment->refresh();
        $this->assertSame('SUCCESS', $payment->status);
        $this->assertSame($this->shift->id, $payment->pos_shift_id, 'uang tetap masuk shift kasir yang menerima');
        $this->assertSame('PAID', PadelBooking::where('order_id', $payment->order_id)->value('status'));
        $row = collect($this->shift->fresh()->settlementBreakdown())->firstWhere('key', 'QRIS_DOKU_QRIS');
        $this->assertSame(300000.0, $row['system_amount']);
        $this->assertSame('Dashboard pembayaran online', $row['source']);
    }

    public function test_cancel_closes_the_doku_session_first_then_releases_the_slot_and_ball_stock(): void
    {
        $pending = $this->checkoutQr([['equipment_id' => $this->ball->id, 'quantity' => 2]])['pending_qris'];
        $payment = Payment::findOrFail($pending['payment_id']);

        $this->assertSame(PosAutoPayment::CANCELLED, app(PosAutoPayment::class)->cancel($payment->id));

        $this->assertSame([$payment->transaction_id], $this->cancelled, 'sesi DOKU dibatalkan di DOKU');
        $this->assertSame('CANCELLED', Order::find($pending['order_id'])->payment_status);
        $this->assertSame('CANCELLED', PadelBooking::where('order_id', $pending['order_id'])->value('status'));
        $this->assertSame(5, (int) $this->ball->fresh()->stock_quantity);
    }

    public function test_cashier_cancel_is_refused_while_doku_cannot_prove_the_qr_is_closed(): void
    {
        $pending = $this->checkoutQr()['pending_qris'];
        $this->cancelWorks = false; // mis. fitur Order Cancellation belum aktif di dashboard DOKU

        $this->assertSame(PosAutoPayment::PENDING, app(PosAutoPayment::class)->cancel($pending['payment_id']));

        // QR yang tampil di layar masih bisa dibayar → order TIDAK dibatalkan & slot tetap ditahan.
        $this->assertSame('UNPAID', Order::find($pending['order_id'])->payment_status);
        $this->assertSame('PENDING', Payment::find($pending['payment_id'])->status);
        $this->assertSame('PENDING_PAYMENT', PadelBooking::where('order_id', $pending['order_id'])->value('status'));
    }

    public function test_cancel_after_the_customer_already_paid_completes_the_sale(): void
    {
        $pending = $this->checkoutQr()['pending_qris'];
        $payment = Payment::findOrFail($pending['payment_id']);
        $this->statuses[$payment->transaction_id] = 'SUCCESS';

        $this->assertSame(PosAutoPayment::PAID, app(PosAutoPayment::class)->cancel($payment->id));
        $this->assertSame('SUCCESS', $payment->fresh()->status);
        $this->assertSame([], $this->cancelled);
    }

    public function test_expired_doku_qr_is_cancelled_by_poll_and_by_the_sweeper(): void
    {
        $first = $this->checkoutQr()['pending_qris'];
        Carbon::setTestNow(now()->addMinutes(app(\App\Services\Padel\BookingTimeService::class)->paymentWindowMinutes() + 1));
        $this->assertSame(PosAutoPayment::EXPIRED, app(PosAutoPayment::class)->status($first['payment_id']));
        $this->assertSame('CANCELLED', Order::find($first['order_id'])->payment_status);

        // Layar kasir ditutup: pembersih berkala yang membatalkan.
        Carbon::setTestNow(now()->addHour());
        $service = app(PadelBookingService::class);
        $second = $service->processWalkInCheckout(
            customer: $service->findOrCreateWalkInCustomer(name: 'Sari', phone: '081234567892'),
            slots: [['court_id' => $this->court->id, 'start_time' => '16:00:00', 'end_time' => '17:00:00']],
            bookingDate: $this->bookingDate, equipments: [], paymentMethod: 'QRIS_MIDTRANS', cashier: $this->cashier,
            paymentMeta: [],
        )['pending_qris'];
        $this->assertSame('DOKU', $second['gateway']);
        Carbon::setTestNow(now()->addMinutes(app(\App\Services\Padel\BookingTimeService::class)->paymentWindowMinutes() + 5));
        $this->artisan('pos:sweep-auto-payments')->assertSuccessful();
        $this->assertSame('CANCELLED', Order::find($second['order_id'])->payment_status);
    }

    public function test_doku_only_serves_the_popup_from_a_doku_https_url(): void
    {
        $this->assertSame('https://sandbox.doku.com/x?a=1&view=iframe', DokuCheckoutService::popupUrl('https://sandbox.doku.com/x?a=1'));
        $this->assertNull(DokuCheckoutService::popupUrl('https://evil.example.com/doku.com'));
        $this->assertNull(DokuCheckoutService::popupUrl('http://sandbox.doku.com/x'));
        $this->assertNull(DokuCheckoutService::popupUrl('javascript:alert(1)//doku.com'));
    }
}
