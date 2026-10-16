<?php

namespace Tests\Feature\Payment;

use App\Models\Membership\MembershipPlan;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Payment\DokuNotification;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\Pos\PaymentGatewaySetting;
use App\Models\Pos\Refund;
use App\Models\User;
use App\Services\Padel\PadelBookingService;
use App\Services\Payment\Doku\DokuCheckoutService;
use App\Services\Payment\Doku\DokuSessionGuard;
use App\Services\Payment\Doku\DokuSignature;
use App\Services\Payment\GatewayCustomer;
use App\Services\Payment\OnlineGatewayRouter;
use App\Services\Permission\Club61PermissionMatrix;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Modul 27 Tahap 2: DOKU Checkout untuk pembayaran online customer. Semua HTTP ke DOKU di-fake (tanpa kunci asli);
 * notifikasi ditandatangani dengan rumus yang sama dengan yang diverifikasi server.
 */
class DokuGatewayTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_ID = 'BRN-0001-TEST';

    private const SECRET = 'SK-test-secret-doku';

    private const NOTIFY_PATH = '/api/v1/payments/doku/notification';

    protected User $customer;

    protected PadelCourt $court;

    protected string $date;

    /** Status per invoice yang dijawab API status palsu: PENDING / SUCCESS / EXPIRED / FAILED. */
    protected array $statuses = [];

    /** Nominal yang dilaporkan API status palsu per invoice (default: nominal sesi). */
    protected array $paidAmounts = [];

    /** Request batal yang diterima DOKU palsu. */
    protected array $cancelled = [];

    /** Batal dikabulkan (status jadi EXPIRED) atau diabaikan (status tetap PENDING). */
    protected bool $cancelWorks = true;

    /** URL halaman bayar yang dijawab DOKU palsu. */
    protected string $checkoutUrl = 'https://sandbox.doku.com/checkout/link/TESTLINK';

    protected int $checkoutHttp = 200;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');
        config([
            'services.doku.enabled' => true,
            'services.doku.client_id' => self::CLIENT_ID,
            'services.doku.secret_key' => self::SECRET,
            'services.doku.is_production' => false,
            'services.doku.notification_url' => null,
        ]);
        PaymentGatewaySetting::query()->update(['online_gateway' => 'DOKU']);
        app(OnlineGatewayRouter::class)->flush();

        $this->customer = User::factory()->customer()->create(['name' => 'Budi', 'email' => 'budi@example.test']);
        $this->court = PadelCourt::create([
            'name' => 'Court DOKU', 'type' => 'INDOOR', 'hourly_rate_regular' => 200000, 'hourly_rate_prime' => 300000, 'is_active' => true,
        ]);
        $this->date = now()->next(Carbon::WEDNESDAY)->format('Y-m-d');
        $this->fakeDoku();
    }

    private function fakeDoku(): void
    {
        Http::fake(function (HttpRequest $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);

            if ($path === DokuCheckoutService::CHECKOUT_PATH) {
                return Http::response(['message' => ['SUCCESS'], 'response' => ['payment' => [
                    'token_id' => 'TOKEN-'.Str::random(6), 'url' => $this->checkoutUrl, 'expired_date' => now()->addMinutes(15)->timezone('Asia/Jakarta')->format('YmdHis'),
                ]]], $this->checkoutHttp);
            }

            if (str_starts_with($path, DokuCheckoutService::STATUS_PATH)) {
                $invoice = rawurldecode(substr($path, strlen(DokuCheckoutService::STATUS_PATH)));

                return Http::response($this->statusJson($invoice, $this->statuses[$invoice] ?? 'PENDING'));
            }

            if ($path === DokuCheckoutService::CANCEL_PATH) {
                $this->cancelled[] = $request->data();
                if ($this->cancelWorks) {
                    $this->statuses[$request->data()['order']['invoice_number']] = 'EXPIRED';
                }

                return Http::response(['message' => ['SUCCESS']]);
            }

            return Http::response([], 404);
        });
    }

    private function statusJson(string $invoice, string $status): array
    {
        $bill = DokuSessionGuard::findBill($invoice);
        $amount = $this->paidAmounts[$invoice] ?? ($bill ? (DokuSessionGuard::session($bill, $invoice)['amount'] ?? 0) : 0);

        return [
            'order' => ['invoice_number' => $invoice, 'amount' => $amount, 'status' => $status === 'EXPIRED' ? 'ORDER_EXPIRED' : 'ORDER_GENERATED'],
            'transaction' => ['status' => $status, 'date' => '2026-10-15T03:00:00Z', 'original_request_id' => 'REQ'],
            'service' => ['id' => 'VIRTUAL_ACCOUNT'],
            'acquirer' => ['id' => 'BCA'],
            'channel' => ['id' => 'VIRTUAL_ACCOUNT_BCA'],
            'virtual_account_info' => ['virtual_account_number' => '1900800000012345'],
            'customer' => ['name' => 'Budi', 'email' => 'budi@example.test'],
        ];
    }

    /** Hold slot + checkout padel lewat API, seperti halaman checkout customer. */
    private function padelCheckout(string $method = 'BCA_VA', string $start = '10:00'): \Illuminate\Testing\TestResponse
    {
        $this->actingAs($this->customer, 'sanctum');
        $hold = $this->postJson('/api/v1/padel/hold-slot', [
            'booking_date' => $this->date,
            'slots' => [['court_id' => $this->court->id, 'start_time' => $start, 'end_time' => Carbon::parse($start)->addHour()->format('H:i')]],
        ])->assertStatus(201);

        return $this->withHeader('X-Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/padel/checkout', [
            'booking_ids' => collect($hold->json('data.bookings'))->pluck('id')->all(),
            'payment_method' => $method,
        ]);
    }

    private function bill(string $orderNumber): Payment
    {
        return Payment::where('order_id', Order::where('order_number', $orderNumber)->value('id'))->latest()->firstOrFail();
    }

    private function notify(array $body, ?string $requestId = null, ?string $secret = null): \Illuminate\Testing\TestResponse
    {
        $raw = json_encode($body);
        $requestId ??= (string) Str::uuid();
        $timestamp = now()->utc()->format('Y-m-d\TH:i:s\Z');

        return $this->call('POST', self::NOTIFY_PATH, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_CLIENT_ID' => self::CLIENT_ID,
            'HTTP_REQUEST_ID' => $requestId,
            'HTTP_REQUEST_TIMESTAMP' => $timestamp,
            'HTTP_SIGNATURE' => DokuSignature::make(self::CLIENT_ID, $requestId, $timestamp, self::NOTIFY_PATH, $raw, $secret ?? self::SECRET),
        ], $raw);
    }

    private function successBody(string $invoice, float $amount, string $status = 'SUCCESS'): array
    {
        return [
            'service' => ['id' => 'VIRTUAL_ACCOUNT'],
            'acquirer' => ['id' => 'BCA'],
            'channel' => ['id' => 'VIRTUAL_ACCOUNT_BCA'],
            'order' => ['invoice_number' => $invoice, 'amount' => $amount],
            'transaction' => ['status' => $status, 'date' => '2026-10-15T03:00:00Z', 'original_request_id' => 'REQ'],
            'virtual_account_info' => ['virtual_account_number' => '1900800000012345'],
            'customer' => ['name' => 'Budi', 'email' => 'budi@example.test'],
        ];
    }

    // ── Tanda tangan ─────────────────────────────────────────────────────────────────────────────────────────────

    public function test_signature_follows_the_documented_component_format(): void
    {
        $body = '{"order":{"amount":10000}}';
        $expectedComponents = "Client-Id:C1\nRequest-Id:R1\nRequest-Timestamp:2020-08-11T08:45:42Z\nRequest-Target:/checkout/v1/payment\nDigest:"
            .base64_encode(hash('sha256', $body, true));

        $this->assertSame($expectedComponents, DokuSignature::components('C1', 'R1', '2020-08-11T08:45:42Z', '/checkout/v1/payment', $body));
        $this->assertSame('HMACSHA256='.base64_encode(hash_hmac('sha256', $expectedComponents, 'S', true)), DokuSignature::make('C1', 'R1', '2020-08-11T08:45:42Z', '/checkout/v1/payment', $body, 'S'));
        // GET tanpa body: tanpa baris Digest, tanpa baris baru di akhir.
        $this->assertSame("Client-Id:C1\nRequest-Id:R1\nRequest-Timestamp:T\nRequest-Target:/orders/v1/status/X", DokuSignature::components('C1', 'R1', 'T', '/orders/v1/status/X', null));

        $signature = DokuSignature::make('C1', 'R1', 'T', '/p', $body, 'S');
        $this->assertTrue(DokuSignature::verify($signature, 'C1', 'R1', 'T', '/p', $body, 'S'));
        $this->assertFalse(DokuSignature::verify($signature, 'C1', 'R1', 'T', '/p', $body.' ', 'S'), 'satu karakter body berubah');
        $this->assertFalse(DokuSignature::verify($signature, 'C1', 'R1', 'T', '/lain', $body, 'S'), 'path lain');
        $this->assertFalse(DokuSignature::verify($signature, 'C1', 'R1', 'T', '/p', $body, ''), 'kunci kosong = gagal');
    }

    // ── Checkout ─────────────────────────────────────────────────────────────────────────────────────────────────

    public function test_padel_checkout_creates_a_signed_doku_session_for_one_method(): void
    {
        $response = $this->padelCheckout('BCA_VA')->assertOk();

        $this->assertSame('doku', $response->json('data.driver'));
        $this->assertSame($this->checkoutUrl, $response->json('data.payment_url'));
        $this->assertNull($response->json('data.snap_token'));
        $this->assertFalse($response->json('data.is_mock'));
        $this->assertSame('PENDING_PAYMENT', $response->json('data.payment_status'));
        $this->assertStringNotContainsString('request_id', $response->getContent(), 'Request-Id pembatalan tidak dikirim ke browser');

        $bill = $this->bill($response->json('data.order_id'));
        $invoice = $bill->transaction_id;
        $this->assertSame('DOKU', $bill->payment_gateway);
        $this->assertSame('BCA_VA', $bill->payment_method);
        $this->assertNull($bill->snap_token);
        $this->assertStringStartsWith($response->json('data.order_id').'-DK-', $invoice);
        $this->assertLessThanOrEqual(64, strlen($invoice));
        $session = DokuSessionGuard::session($bill, $invoice);
        $this->assertSame('ACTIVE', $session['status']);
        $this->assertNotEmpty($session['request_id']);

        Http::assertSent(function (HttpRequest $r) use ($invoice, $bill, $session) {
            if (parse_url($r->url(), PHP_URL_PATH) !== DokuCheckoutService::CHECKOUT_PATH) {
                return false;
            }
            $data = $r->data();
            $sum = array_sum(array_map(fn ($i) => $i['price'] * $i['quantity'], $data['order']['line_items']));

            return str_starts_with($r->url(), 'https://api-sandbox.doku.com/')
                && $r->header('Client-Id')[0] === self::CLIENT_ID
                && $r->header('Request-Id')[0] === $session['request_id']
                && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $r->header('Request-Timestamp')[0])
                // Tanda tangan dihitung dari body yang PERSIS dikirim.
                && DokuSignature::verify($r->header('Signature')[0], self::CLIENT_ID, $r->header('Request-Id')[0], $r->header('Request-Timestamp')[0], DokuCheckoutService::CHECKOUT_PATH, $r->body(), self::SECRET)
                && $data['order']['invoice_number'] === $invoice
                && $data['order']['amount'] === (int) $bill->amount
                && $sum === (int) $bill->amount
                && $data['payment']['payment_method_types'] === ['VIRTUAL_ACCOUNT_BCA']
                && $data['payment']['payment_due_date'] === app(\App\Services\Padel\BookingTimeService::class)->paymentWindowMinutes()
                && str_contains($data['order']['callback_url'], '/invoice')
                && ! isset($data['additional_info']);
        });
    }

    public function test_customer_data_is_sent_in_the_format_doku_accepts(): void
    {
        // Kasus nyata sandbox 15 Okt: "Andi Wijaya (Customer)" → DOKU gagal membuat VA ("Invalid customerName Format").
        $this->customer->update(['name' => 'Andi Wijaya (Customer)', 'phone' => '0812-3456-7890']);

        $this->padelCheckout('BCA_VA')->assertOk();

        Http::assertSent(fn (HttpRequest $r) => parse_url($r->url(), PHP_URL_PATH) === DokuCheckoutService::CHECKOUT_PATH
            && $r->data()['customer'] === ['name' => 'Andi Wijaya Customer', 'email' => 'budi@example.test', 'phone' => '6281234567890']);
        $this->assertSame('Andi Wijaya (Customer)', $this->customer->fresh()->name, 'Nama di database tidak diubah');

        $this->assertSame('Muh Irham', GatewayCustomer::name('Muh. Irham'));
        $this->assertSame('Siti Nurhaliza', GatewayCustomer::name("  Siti  😀 Nurhaliza 123 "));
        $this->assertSame('Pelanggan Club', GatewayCustomer::name('(123)'));
        $this->assertSame('6281122334455', GatewayCustomer::phone('+62 811-2233-4455'));
        $this->assertSame('6281122334455', GatewayCustomer::phone('81122334455'));
        $this->assertNull(GatewayCustomer::phone('12345'), 'Nomor tidak valid tidak dikirim');
        $this->assertNull(GatewayCustomer::email('bukan-email'));
    }

    public function test_checkout_is_rolled_back_when_doku_fails_or_returns_a_foreign_url(): void
    {
        $this->checkoutHttp = 500;
        $this->padelCheckout('BCA_VA')->assertStatus(503);

        $this->checkoutHttp = 200;
        $this->checkoutUrl = 'https://evil.example.com/pay';
        $this->padelCheckout('BCA_VA', '12:00')->assertStatus(503);

        $this->assertSame(0, Payment::count(), 'Tidak ada tagihan tanpa sesi DOKU yang sah');
        $this->assertSame(0, PadelBooking::where('status', 'PENDING_PAYMENT')->count());
        $this->assertSame(0, PadelBooking::where('status', 'PAID')->count());
    }

    public function test_method_without_a_doku_channel_is_rejected_when_doku_is_active(): void
    {
        \App\Models\Pos\OnlinePaymentMethod::where('code', 'CREDIT_CARD')->update(['is_active' => true]);
        app(\App\Services\Payment\OnlinePaymentMethodService::class)->flush();

        $this->padelCheckout('CREDIT_CARD')->assertStatus(422);
        $this->assertNotContains('CREDIT_CARD', array_column($this->getJson('/api/v1/payment-methods')->json('data'), 'code'));
    }

    public function test_override_notification_url_is_sent_outside_production_only(): void
    {
        config(['services.doku.notification_url' => 'https://dev.localhost.run'.self::NOTIFY_PATH]);
        $this->padelCheckout('BCA_VA')->assertOk();
        Http::assertSent(fn (HttpRequest $r) => ($r->data()['additional_info']['override_notification_url'] ?? null) === 'https://dev.localhost.run'.self::NOTIFY_PATH);

        config(['services.doku.is_production' => true]);
        $this->checkoutUrl = 'https://jokul.doku.com/checkout/link/PROD';
        $this->padelCheckout('BCA_VA', '12:00')->assertOk();
        Http::assertSent(fn (HttpRequest $r) => str_starts_with($r->url(), 'https://api.doku.com/') && ! isset($r->data()['additional_info']));
    }

    // ── Notifikasi ───────────────────────────────────────────────────────────────────────────────────────────────

    public function test_signed_success_notification_confirmed_by_status_api_pays_the_booking_once(): void
    {
        $orderNumber = $this->padelCheckout('BCA_VA')->assertOk()->json('data.order_id');
        $bill = $this->bill($orderNumber);
        $invoice = $bill->transaction_id;
        $this->statuses[$invoice] = 'SUCCESS';

        $requestId = (string) Str::uuid();
        $this->notify($this->successBody($invoice, (float) $bill->amount), $requestId)->assertOk();

        $bill->refresh();
        $this->assertSame('SUCCESS', $bill->status);
        $this->assertSame('DOKU', $bill->payment_gateway);
        $this->assertSame('BCA_VA', $bill->payment_method, 'Kode metode kita → label riwayat dari pengaturan');
        $this->assertSame('1900800000012345', $bill->payload_log['doku_payment']['va_number']);
        $this->assertArrayNotHasKey('customer', $bill->payload_log, 'Data pribadi customer dari DOKU tidak disimpan');
        $this->assertSame('PAID', PadelBooking::where('order_id', $bill->order_id)->value('status'));
        $notification = DokuNotification::where('request_id', $requestId)->firstOrFail();
        $this->assertSame(DokuNotification::PROCESSED, $notification->result);
        $this->assertArrayNotHasKey('customer', $notification->payload);

        // Notifikasi dobel (Request-Id sama) & notifikasi ulang (Request-Id baru) tidak melunasi dua kali.
        $this->notify($this->successBody($invoice, (float) $bill->amount), $requestId)->assertOk()->assertJsonPath('message', 'Already processed.');
        $this->notify($this->successBody($invoice, (float) $bill->amount))->assertOk();
        $this->assertSame(1, Payment::where('order_id', $bill->order_id)->where('status', 'SUCCESS')->count());
        $this->assertSame(0, Refund::count());
        $this->assertSame('VA 1900800000012345', \App\Models\Finance\LedgerEntry::where('payment_id', $bill->id)->value('payment_reference'));
    }

    public function test_notification_with_a_bad_signature_is_rejected_and_not_stored(): void
    {
        $orderNumber = $this->padelCheckout('BCA_VA')->assertOk()->json('data.order_id');
        $bill = $this->bill($orderNumber);
        $this->statuses[$bill->transaction_id] = 'SUCCESS';

        $this->notify($this->successBody($bill->transaction_id, (float) $bill->amount), secret: 'tebakan-penyerang')->assertStatus(401);

        $this->assertSame(0, DokuNotification::count());
        $this->assertSame('PENDING', $bill->fresh()->status);
        $this->assertSame('PENDING_PAYMENT', PadelBooking::where('order_id', $bill->order_id)->value('status'));
    }

    public function test_success_notification_is_not_trusted_without_status_api_confirmation(): void
    {
        $orderNumber = $this->padelCheckout('BCA_VA')->assertOk()->json('data.order_id');
        $bill = $this->bill($orderNumber);
        $invoice = $bill->transaction_id;

        // Status API belum sinkron → dicoba lagi, belum lunas.
        $this->notify($this->successBody($invoice, (float) $bill->amount))->assertOk();
        $this->assertSame(DokuNotification::RETRY, DokuNotification::first()->result);
        $this->assertSame('PENDING', $bill->fresh()->status);

        // Status API menyatakan kedaluwarsa (notifikasi diputar ulang / palsu) → diabaikan.
        $this->statuses[$invoice] = 'EXPIRED';
        $this->travel(2)->minutes();
        $this->artisan('payment:reconcile-doku')->assertSuccessful();
        $this->assertSame(DokuNotification::IGNORED, DokuNotification::first()->result);
        // Tidak pernah lunas; tagihan ditutup karena DOKU sendiri menyatakan kedaluwarsa.
        $this->assertSame('FAILED', $bill->fresh()->status);
        $this->assertSame(0, Payment::where('order_id', $bill->order_id)->where('status', 'SUCCESS')->count());
        $this->assertNotSame('PAID', PadelBooking::where('order_id', $bill->order_id)->value('status'));
    }

    public function test_retry_notification_is_settled_by_the_scheduled_reconciliation(): void
    {
        $orderNumber = $this->padelCheckout('BCA_VA')->assertOk()->json('data.order_id');
        $bill = $this->bill($orderNumber);
        $this->notify($this->successBody($bill->transaction_id, (float) $bill->amount))->assertOk();
        $this->assertSame(DokuNotification::RETRY, DokuNotification::first()->result);

        $this->statuses[$bill->transaction_id] = 'SUCCESS';
        $this->travel(2)->minutes();
        $this->artisan('payment:reconcile-doku')->assertSuccessful();

        $this->assertSame(DokuNotification::PROCESSED, DokuNotification::first()->result);
        $this->assertSame('SUCCESS', $bill->fresh()->status);
    }

    public function test_amount_mismatch_is_alerted_and_never_settled_automatically(): void
    {
        $orderNumber = $this->padelCheckout('BCA_VA')->assertOk()->json('data.order_id');
        $bill = $this->bill($orderNumber);
        $invoice = $bill->transaction_id;
        $this->statuses[$invoice] = 'SUCCESS';

        $this->notify($this->successBody($invoice, (float) $bill->amount - 50000))->assertOk();
        $this->assertSame(DokuNotification::REJECTED, DokuNotification::first()->result);

        // API status juga melaporkan nominal lain → rekonsiliasi tidak melunasi, dan melapor ERROR (slot tidak dilepas).
        $this->paidAmounts[$invoice] = (float) $bill->amount - 50000;
        $this->travel(3)->minutes();
        $this->assertSame('ERROR', app(OnlineGatewayRouter::class)->reconcileOrder($bill->order));
        $this->assertSame('PENDING', $bill->fresh()->status);
    }

    public function test_expired_notification_never_downgrades_a_paid_session(): void
    {
        $orderNumber = $this->padelCheckout('BCA_VA')->assertOk()->json('data.order_id');
        $bill = $this->bill($orderNumber);
        $invoice = $bill->transaction_id;
        $this->statuses[$invoice] = 'SUCCESS';
        $this->notify($this->successBody($invoice, (float) $bill->amount))->assertOk();

        $this->notify($this->successBody($invoice, (float) $bill->amount, 'EXPIRED'))->assertOk();

        $bill->refresh();
        $this->assertSame('SUCCESS', $bill->status);
        $this->assertSame('ACTIVE', DokuSessionGuard::session($bill, $invoice)['status']);
        $this->assertSame('PAID', PadelBooking::where('order_id', $bill->order_id)->value('status'));
    }

    // ── Anti-dobel ───────────────────────────────────────────────────────────────────────────────────────────────

    private function retry(string $bookingId, string $method): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->customer, 'sanctum')->postJson("/api/v1/padel/bookings/{$bookingId}/retry-payment", ['payment_method' => $method]);
    }

    public function test_same_method_reuses_the_session_and_changing_method_cancels_the_old_one_first(): void
    {
        $checkout = $this->padelCheckout('BCA_VA')->assertOk();
        $bookingId = $checkout->json('data.booking_id');
        $first = $this->bill($checkout->json('data.order_id'))->transaction_id;

        $reuse = $this->retry($bookingId, 'BCA_VA')->assertOk();
        $this->assertTrue($reuse->json('reused_session'));
        $this->assertSame('doku', $reuse->json('driver'));
        $this->assertSame($first, $reuse->json('suffixed_order_id'));
        $this->assertSame($this->checkoutUrl, $reuse->json('payment_url'));
        $this->assertCount(1, Http::recorded(fn (HttpRequest $r) => parse_url($r->url(), PHP_URL_PATH) === DokuCheckoutService::CHECKOUT_PATH), 'Metode sama = tidak ada sesi DOKU baru');

        $switch = $this->retry($bookingId, 'MANDIRI_VA')->assertOk();
        $second = $switch->json('suffixed_order_id');
        $this->assertNotSame($first, $second);
        $this->assertSame('doku', $switch->json('driver'));
        $this->assertSame($first, $this->cancelled[0]['order']['invoice_number']);
        $bill = $this->bill($checkout->json('data.order_id'));
        $this->assertSame(DokuSessionGuard::session($bill, $first)['request_id'], $this->cancelled[0]['payment']['original_request_id']);
        $this->assertSame('CLOSED', DokuSessionGuard::session($bill, $first)['status']);
        $this->assertSame('ACTIVE', DokuSessionGuard::session($bill, $second)['status']);
        $this->assertSame('MANDIRI_VA', $bill->payment_method);
        Http::assertSent(fn (HttpRequest $r) => parse_url($r->url(), PHP_URL_PATH) === DokuCheckoutService::CHECKOUT_PATH
            && $r->data()['payment']['payment_method_types'] === ['VIRTUAL_ACCOUNT_BANK_MANDIRI']);
    }

    public function test_old_session_that_cannot_be_proven_closed_blocks_a_new_session(): void
    {
        $checkout = $this->padelCheckout('BCA_VA')->assertOk();
        $this->cancelWorks = false;

        $this->retry($checkout->json('data.booking_id'), 'MANDIRI_VA')->assertStatus(503);

        $bill = $this->bill($checkout->json('data.order_id'));
        $this->assertCount(1, DokuSessionGuard::sessionsOf($bill), 'Tidak ada sesi kedua selama sesi lama masih bisa dibayar');
    }

    public function test_old_session_already_paid_settles_instead_of_opening_a_new_one(): void
    {
        $checkout = $this->padelCheckout('BCA_VA')->assertOk();
        $bill = $this->bill($checkout->json('data.order_id'));
        $this->statuses[$bill->transaction_id] = 'SUCCESS';

        $this->retry($checkout->json('data.booking_id'), 'MANDIRI_VA')->assertOk()->assertJsonPath('is_paid', true);

        $this->assertSame('SUCCESS', $bill->fresh()->status);
        $this->assertCount(1, DokuSessionGuard::sessionsOf($bill->fresh()));
        $this->assertSame('PAID', PadelBooking::where('order_id', $bill->order_id)->value('status'));
    }

    public function test_paying_an_old_session_after_the_bill_was_paid_is_recorded_as_duplicate_with_refund(): void
    {
        $checkout = $this->padelCheckout('BCA_VA')->assertOk();
        $orderNumber = $checkout->json('data.order_id');
        $bill = $this->bill($orderNumber);
        $first = $bill->transaction_id;
        // Sesi lama tercatat sudah ditutup, tapi uangnya tetap masuk (kejadian di luar kendali, mis. bank) → payung refund.
        $log = $bill->payload_log;
        $log['doku_sessions'][$first]['status'] = 'CLOSED';
        $bill->update(['payload_log' => $log]);
        $second = $this->retry($checkout->json('data.booking_id'), 'MANDIRI_VA')->assertOk()->json('suffixed_order_id');

        $this->statuses[$second] = 'SUCCESS';
        $this->notify($this->successBody($second, (float) $bill->amount))->assertOk();
        $this->statuses[$first] = 'SUCCESS';
        $this->notify($this->successBody($first, (float) $bill->amount))->assertOk();

        $this->assertSame(1, Payment::where('order_id', $bill->order_id)->where('status', 'SUCCESS')->count());
        $duplicate = Payment::where('order_id', $bill->order_id)->where('status', 'DUPLICATE')->firstOrFail();
        $this->assertSame($first, $duplicate->transaction_id);
        $this->assertEquals((float) $bill->amount, (float) Refund::where('payment_id', $duplicate->id)->value('refund_amount'));
    }

    // ── Pengarah, pembersih slot, kasir ──────────────────────────────────────────────────────────────────────────

    public function test_paid_doku_bill_is_not_released_by_the_slot_cleaner_when_the_webhook_is_lost(): void
    {
        $orderNumber = $this->padelCheckout('BCA_VA')->assertOk()->json('data.order_id');
        $bill = $this->bill($orderNumber);
        $this->statuses[$bill->transaction_id] = 'SUCCESS';

        $this->travel(app(\App\Services\Padel\BookingTimeService::class)->paymentWindowMinutes() + 5)->minutes();
        app(PadelBookingService::class)->releaseExpiredLocks();

        $this->assertSame('PAID', PadelBooking::where('order_id', $bill->order_id)->value('status'));
        $this->assertSame('SUCCESS', $bill->fresh()->status);
        $this->assertSame(['DOKU'], app(OnlineGatewayRouter::class)->gatewaysOf($bill->order), 'Order DOKU tidak ditanyakan ke Midtrans');
    }

    public function test_unpaid_doku_bill_is_closed_and_slot_released_after_doku_confirms_expiry(): void
    {
        $orderNumber = $this->padelCheckout('BCA_VA')->assertOk()->json('data.order_id');
        $bill = $this->bill($orderNumber);
        $this->statuses[$bill->transaction_id] = 'EXPIRED';

        $this->travel(app(\App\Services\Padel\BookingTimeService::class)->paymentWindowMinutes() + 5)->minutes();
        app(PadelBookingService::class)->releaseExpiredLocks();

        $this->assertSame('EXPIRED', PadelBooking::where('order_id', $bill->order_id)->value('status'));
        $this->assertSame('FAILED', $bill->fresh()->status);
    }

    public function test_reconciliation_settles_lost_webhooks_and_alerts_about_the_missing_notification_url(): void
    {
        $orderNumber = $this->padelCheckout('BCA_VA')->assertOk()->json('data.order_id');
        $bill = $this->bill($orderNumber);
        $this->statuses[$bill->transaction_id] = 'SUCCESS';

        $this->travel(3)->minutes();
        $this->artisan('payment:reconcile-doku')->assertSuccessful();
        $this->assertSame('SUCCESS', $bill->fresh()->status);
        $this->assertSame('DOKU_STATUS_API', $bill->fresh()->payload_log['reconciled_via']);

        // Belum 15 menit: belum dianggap hilang.
        $this->assertFalse(\Illuminate\Support\Facades\Cache::has("doku_missing_notification:{$bill->transaction_id}"));

        $this->travel(20)->minutes();
        $this->artisan('payment:reconcile-doku')->assertSuccessful();
        $this->assertTrue(\Illuminate\Support\Facades\Cache::has("doku_missing_notification:{$bill->transaction_id}"), '[ALERT] URL notifikasi dikirim (sekali per invoice)');
    }

    public function test_doku_bill_keeps_being_reconciled_after_doku_is_switched_off(): void
    {
        $orderNumber = $this->padelCheckout('BCA_VA')->assertOk()->json('data.order_id');
        $bill = $this->bill($orderNumber);
        config(['services.doku.enabled' => false]);
        $this->assertSame('MIDTRANS', app(OnlineGatewayRouter::class)->onlineGateway(), 'Tagihan baru kembali ke Midtrans');

        $this->statuses[$bill->transaction_id] = 'SUCCESS';
        $this->assertSame('PAID', app(OnlineGatewayRouter::class)->reconcileOrder($bill->order));
        $this->assertSame('SUCCESS', $bill->fresh()->status);
    }

    public function test_switching_off_doku_moves_a_retry_to_midtrans_after_closing_the_doku_session(): void
    {
        $checkout = $this->padelCheckout('BCA_VA')->assertOk();
        $first = $this->bill($checkout->json('data.order_id'))->transaction_id;
        config(['services.doku.enabled' => false]);

        $retry = $this->retry($checkout->json('data.booking_id'), 'BCA_VA')->assertOk();

        $bill = $this->bill($checkout->json('data.order_id'));
        $this->assertSame('midtrans', $retry->json('driver'));
        $this->assertSame('MIDTRANS', $bill->payment_gateway);
        $this->assertNotNull($bill->snap_token);
        $this->assertSame('CLOSED', DokuSessionGuard::session($bill, $first)['status']);
        $this->assertSame($first, $this->cancelled[0]['order']['invoice_number']);
    }

    public function test_cashier_cannot_settle_a_reschedule_difference_while_the_doku_session_is_open(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $order = Order::create(['order_number' => 'ORD-DK-RS1', 'user_id' => $this->customer->id, 'order_type' => 'ONLINE_BOOKING', 'subtotal' => 200000, 'grand_total' => 200000, 'payment_status' => 'PAID']);
        Payment::create(['order_id' => $order->id, 'payment_gateway' => 'MIDTRANS', 'transaction_id' => 'ORD-DK-RS1', 'amount' => 200000, 'payment_method' => 'BCA_VA', 'status' => 'SUCCESS']);
        $start = Carbon::parse("{$this->date} 10:00");
        $booking = PadelBooking::create(['booking_code' => 'BK-DK-RS1', 'order_id' => $order->id, 'user_id' => $this->customer->id, 'court_id' => $this->court->id, 'booking_date' => $this->date, 'start_time' => $start, 'end_time' => $start->copy()->addHour(), 'court_fee' => 200000, 'total_amount' => 200000, 'status' => 'PAID', 'qr_code_hash' => 'QR-DK-RS1']);
        app(PadelBookingService::class)->adminRescheduleBooking(bookingId: $booking->id, newCourtId: $this->court->id, newDate: $this->date, newStartTimeStr: '18:00', reason: 'Permintaan customer', adminUser: $admin, paymentMethod: 'QRIS', isDeltaPaid: false, paymentProof: []);

        $retry = $this->retry($booking->id, 'QRIS')->assertOk();
        $this->assertSame('doku', $retry->json('driver'));
        \App\Models\Pos\PosCashierShift::create(['shift_number' => 'SFT-DK-1', 'counter' => 'PADEL_FRONTDESK', 'status' => 'OPEN', 'opened_by_id' => $admin->id, 'opened_at' => now(), 'starting_cash' => 0, 'expected_cash' => 0]);

        try {
            app(PadelBookingService::class)->adminSettleSupplementalPayment($booking->id, 'QRIS', $admin, ['qris_rrn' => 'RRN-DK-1']);
            $this->fail('Kasir harus menolak selama sesi DOKU customer masih hidup');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
            $this->assertStringContainsString('DOKU', $e->getMessage());
        }

        // Customer ternyata sudah membayar di DOKU → kasir diberi tahu, tidak menerima uang lagi.
        $this->statuses[$retry->json('suffixed_order_id')] = 'SUCCESS';
        $result = app(PadelBookingService::class)->adminSettleSupplementalPayment($booking->id, 'QRIS', $admin, ['qris_rrn' => 'RRN-DK-2']);
        $this->assertSame('DOKU', $result['settled_via']);
        $this->assertSame(0, Payment::where('order_id', $order->id)->where('payment_gateway', 'CASHIER_POS')->where('status', 'SUCCESS')->count());
        $this->assertSame('PAID', $booking->fresh()->status);
    }

    public function test_membership_checkout_through_doku(): void
    {
        $plan = MembershipPlan::create(['code' => 'MBR-DOKU', 'name' => 'Gold DOKU', 'ownership_type' => 'INDIVIDUAL', 'price' => 500000, 'duration_days' => 30, 'is_active' => true]);

        $response = $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/membership/checkout', ['plan_id' => $plan->id, 'payment_method' => 'QRIS'])->assertStatus(201);

        $this->assertSame('doku', $response->json('data.payment.driver'));
        $this->assertSame($this->checkoutUrl, $response->json('data.payment.redirect_url'));
        $this->assertArrayNotHasKey('request_id', $response->json('data.payment'));
        $bill = Payment::where('order_id', $response->json('data.order.id'))->firstOrFail();
        $this->assertSame('DOKU', $bill->payment_gateway);
        Http::assertSent(fn (HttpRequest $r) => parse_url($r->url(), PHP_URL_PATH) === DokuCheckoutService::CHECKOUT_PATH
            && $r->data()['payment']['payment_method_types'] === ['QRIS']
            && str_contains($r->data()['order']['callback_url'], 'membership_id='));
    }

    public function test_cashier_qr_gateway_cannot_be_doku_yet(): void
    {
        PaymentGatewaySetting::query()->update(['pos_qris_gateway' => 'DOKU']);
        app(OnlineGatewayRouter::class)->flush();

        $this->assertSame('MIDTRANS', app(OnlineGatewayRouter::class)->posQrisGateway());
        $this->assertSame('DOKU', app(OnlineGatewayRouter::class)->onlineGateway());
        $this->assertStringContainsString('belum terpasang', (string) app(OnlineGatewayRouter::class)->unavailableReason('DOKU', forPos: true));
    }
}
