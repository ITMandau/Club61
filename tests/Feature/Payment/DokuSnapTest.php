<?php

namespace Tests\Feature\Payment;

use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Payment\DokuNotification;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\Pos\PaymentGatewaySetting;
use App\Models\Pos\PosCashierShift;
use App\Models\User;
use App\Services\Padel\PadelBookingService;
use App\Services\Payment\Doku\DokuCheckoutService;
use App\Services\Payment\Doku\DokuSessionGuard;
use App\Services\Payment\Doku\Snap\DokuSnapClient;
use App\Services\Payment\Doku\Snap\DokuSnapService;
use App\Services\Payment\Doku\Snap\DokuSnapVaAccounts;
use App\Services\Payment\OnlineGatewayRouter;
use App\Services\Permission\Club61PermissionMatrix;
use App\Services\Pos\PosAutoPayment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Modul 27b — QRIS & VA BCA lewat DOKU SNAP dengan halaman bayar sendiri. Semua HTTP ke DOKU di-fake; kunci RSA kita
 * & "kunci DOKU" dibuat khusus untuk test (tidak ada kunci asli).
 */
class DokuSnapTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_ID = 'BRN-SNAP-TEST-0001';

    private const SECRET = 'snap-client-secret-test';

    private const TOKEN = 'SNAP-ACCESS-TOKEN-1';

    protected User $customer;

    protected PadelCourt $court;

    protected string $date;

    /** Kunci privat "DOKU" (untuk menandatangani permintaan token masuk). */
    protected \OpenSSLAsymmetricKey $dokuPrivateKey;

    protected \OpenSSLAsymmetricKey $ourPrivateKey;

    /** Status QRIS per invoice (kode SNAP: 03 pending, 00 sukses, 05 batal). */
    protected array $qrisStatus = [];

    /** Status VA per invoice ('01' belum, '00' lunas, 'DELETED'). */
    protected array $vaStatus = [];

    /** Nomor VA → invoice (dari create-va). */
    protected array $vaInvoices = [];

    /** Nominal yang dilaporkan lunas (default nominal sesi). */
    protected array $paidAmounts = [];

    /** Jawaban mentah cek status per invoice: [body, http status] — menimpa jawaban normal di atas. */
    protected array $vaRaw = [];

    protected array $qrisRaw = [];

    /** Jawaban cek status untuk VA yang sudah dihapus. */
    protected array $deletedVaAnswer = [['responseCode' => '4042601', 'responseMessage' => 'Transaction Not Found'], 404];

    /** false = DOKU menolak delete-va. */
    protected bool $vaDeleteWorks = true;

    /** Kanal SNAP yang create-va-nya ditolak DOKU (403 "BIN not configured"). */
    protected ?string $vaCreateRejectedChannel = null;

    /** Folder kunci buatan test (dihapus di tearDown). */
    protected string $keyDir;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');

        $dir = $this->keyDir = storage_path('framework/testing/doku-snap-'.Str::random(8));
        @mkdir($dir, 0700, true);
        $cnf = $dir.'/openssl.cnf';
        file_put_contents($cnf, "[ req ]\ndistinguished_name = req_dn\n[ req_dn ]\n");
        [$this->ourPrivateKey, $ourPem] = $this->makeKey($cnf);
        [$this->dokuPrivateKey] = $this->makeKey($cnf);
        file_put_contents($dir.'/private.key', $ourPem);
        file_put_contents($dir.'/doku-public.pem', openssl_pkey_get_details($this->dokuPrivateKey)['key']);

        config([
            'services.doku.enabled' => true,
            'services.doku.client_id' => 'BRN-CHECKOUT-TEST',
            'services.doku.secret_key' => 'SK-checkout-test',
            'services.doku.is_production' => false,
            'services.doku.notification_url' => null,
            'services.doku.snap' => [
                'enabled' => true,
                'client_id' => self::CLIENT_ID,
                'client_secret' => self::SECRET,
                'private_key_path' => $dir.'/private.key',
                'private_key_passphrase' => null,
                'doku_public_key_path' => $dir.'/doku-public.pem',
                'qris_merchant_id' => 'MID-TEST',
                'qris_terminal_id' => 'TID001',
                'qris_postal_code' => '12345',
                'bca_partner_service_id' => '19008',
                'bca_customer_no' => '0',
            ],
        ]);
        PaymentGatewaySetting::query()->update(['online_gateway' => 'DOKU', 'pos_qris_gateway' => 'DOKU']);
        app(OnlineGatewayRouter::class)->flush();
        $this->fakeDoku();

        $this->customer = User::factory()->customer()->create(['name' => 'Budi', 'email' => 'budi@example.test', 'phone' => '081234567890']);
        $this->court = PadelCourt::create(['name' => 'Court SNAP', 'type' => 'INDOOR', 'hourly_rate_regular' => 200000, 'hourly_rate_prime' => 300000, 'is_active' => true]);
        $this->date = now()->next(Carbon::WEDNESDAY)->format('Y-m-d');
    }

    protected function tearDown(): void
    {
        if (isset($this->keyDir)) {
            \Illuminate\Support\Facades\File::deleteDirectory($this->keyDir);
        }
        parent::tearDown();
    }

    private function makeKey(string $cnf): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'config' => $cnf]);
        openssl_pkey_export($key, $pem, null, ['config' => $cnf]);

        return [$key, $pem];
    }

    private function fakeDoku(): void
    {
        Http::fake(function (HttpRequest $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            $data = $request->data();

            return match ($path) {
                DokuSnapClient::TOKEN_PATH => Http::response(['responseCode' => '2007300', 'responseMessage' => 'Successful', 'accessToken' => self::TOKEN, 'tokenType' => 'Bearer', 'expiresIn' => 900]),
                DokuSnapService::QRIS_GENERATE_PATH => Http::response([
                    'responseCode' => '2004700', 'responseMessage' => 'Successful', 'referenceNo' => 'REF-'.$data['partnerReferenceNo'],
                    'partnerReferenceNo' => $data['partnerReferenceNo'], 'qrContent' => '00020101021226QRIS-'.$data['partnerReferenceNo'],
                ]),
                DokuSnapService::QRIS_QUERY_PATH => isset($this->qrisRaw[$data['originalPartnerReferenceNo']])
                    ? Http::response(...$this->qrisRaw[$data['originalPartnerReferenceNo']])
                    : Http::response([
                        'responseCode' => '2005100', 'originalPartnerReferenceNo' => $data['originalPartnerReferenceNo'],
                        'latestTransactionStatus' => $this->qrisStatus[$data['originalPartnerReferenceNo']] ?? '03',
                        'amount' => ['value' => (string) $this->amountOf($data['originalPartnerReferenceNo']), 'currency' => 'IDR'],
                        'paidTime' => '2026-10-20T10:00:00+07:00',
                    ]),
                DokuSnapService::QRIS_EXPIRE_PATH => (function () use ($data) {
                    $this->qrisStatus[$data['partnerReferenceNo']] = '05';

                    return Http::response(['responseCode' => '2005300', 'responseMessage' => 'Successful']);
                })(),
                DokuSnapService::VA_CREATE_PATH => (function () use ($data) {
                    if ($this->vaCreateRejectedChannel !== null && ($data['additionalInfo']['channel'] ?? null) === $this->vaCreateRejectedChannel) {
                        // Jawaban asli DOKU production 22 Okt 2026 untuk BIN yang belum dikonfigurasi.
                        return Http::response(['responseCode' => '4032701', 'responseMessage' => "Feature Not Allowed [Identifier for BIN = '98829172' is not configured properly.]"], 403);
                    }
                    // DGPC: DOKU melengkapi BIN + prefix sub-BIN jadi nomor VA 16 digit.
                    $customerNo = $data['customerNo'].str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
                    $vaNumber = $data['partnerServiceId'].$customerNo;
                    $this->vaInvoices[$vaNumber] = $data['trxId'];

                    return Http::response(['responseCode' => '2002700', 'responseMessage' => 'Successful', 'virtualAccountData' => array_merge($data, [
                        'customerNo' => $customerNo, 'virtualAccountNo' => $vaNumber, 'virtualAccountEmail' => null,
                    ])]);
                })(),
                DokuSnapService::VA_STATUS_PATH => (function () use ($data) {
                    $invoice = $this->vaInvoices[$data['virtualAccountNo']] ?? null;
                    if (isset($this->vaRaw[$invoice])) {
                        return Http::response(...$this->vaRaw[$invoice]);
                    }
                    $status = $this->vaStatus[$invoice] ?? '01';
                    if ($status === 'DELETED') {
                        // Jawaban DOKU untuk VA yang sudah dihapus tidak didokumentasikan; default kode SNAP standar
                        // "Transaction Not Found" (kasus 01).
                        return Http::response(...$this->deletedVaAnswer);
                    }

                    // Bentuk contoh resmi DOKU: VA yang BELUM dibayar tetap mengisi paidAmount = nominal tagihan.
                    return Http::response(['responseCode' => '2002600', 'responseMessage' => 'Successful', 'virtualAccountData' => array_filter([
                        'paymentFlagReason' => $status === '00' ? ['english' => 'Success', 'indonesia' => 'Sukses'] : ['english' => 'Pending', 'indonesia' => 'Belum Terbayar'],
                        'partnerServiceId' => $data['partnerServiceId'], 'customerNo' => $data['customerNo'], 'virtualAccountNo' => $data['virtualAccountNo'],
                        'paymentRequestId' => $status === '00' ? 'PRQ-'.$invoice : null,
                        'paidAmount' => ['value' => number_format($this->amountOf($invoice), 2, '.', ''), 'currency' => 'IDR'],
                    ], fn ($v) => $v !== null), 'additionalInfo' => ['acquirer' => ['id' => 'BCA'], 'trxId' => $invoice]]);
                })(),
                DokuSnapService::VA_DELETE_PATH => (function () use ($data) {
                    if (! $this->vaDeleteWorks) {
                        return Http::response(['responseCode' => '5003100', 'responseMessage' => 'General Error'], 500);
                    }
                    $this->vaStatus[$data['trxId']] = 'DELETED';

                    return Http::response(['responseCode' => '2003100', 'responseMessage' => 'Successful']);
                })(),
                DokuCheckoutService::CHECKOUT_PATH => Http::response(['message' => ['SUCCESS'], 'response' => ['payment' => [
                    'token_id' => 'T', 'url' => 'https://sandbox.doku.com/checkout/link/BNI', 'expired_date' => now()->addMinutes(15)->timezone('Asia/Jakarta')->format('YmdHis'),
                ]]]),
                default => Http::response([], 404),
            };
        });
    }

    private function amountOf(?string $invoice): float
    {
        if ($invoice && isset($this->paidAmounts[$invoice])) {
            return $this->paidAmounts[$invoice];
        }
        $bill = $invoice ? DokuSessionGuard::findBill($invoice) : null;

        return $bill ? (float) (DokuSessionGuard::session($bill, $invoice)['amount'] ?? 0) : 0;
    }

    private function padelCheckout(string $method, string $start = '10:00'): \Illuminate\Testing\TestResponse
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

    /** Rumus dokumentasi DOKU / SDK resmi, ditulis ulang di sini (bukan memanggil kode yang diuji). */
    private function expectedHmac(string $method, string $path, string $token, string $body, string $timestamp, string $secret): string
    {
        $minified = json_encode(json_decode($body), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return base64_encode(hash_hmac('sha512', $method.':'.$path.':'.$token.':'.strtolower(hash('sha256', $minified)).':'.$timestamp, $secret, true));
    }

    private function bill(string $orderNumber): Payment
    {
        return Payment::where('order_id', Order::where('order_number', $orderNumber)->value('id'))->latest()->firstOrFail();
    }

    private function sentTo(string $path): array
    {
        return Http::recorded(fn (HttpRequest $r) => parse_url($r->url(), PHP_URL_PATH) === $path)->map(fn ($pair) => $pair[0])->values()->all();
    }

    // ── Keluar: token & tanda tangan ────────────────────────────────────────────────────────────────────────────

    public function test_qris_checkout_uses_snap_with_signed_requests_and_shows_our_own_qr(): void
    {
        $checkout = $this->padelCheckout('QRIS')->assertOk();
        $this->assertSame('doku', $checkout->json('data.driver'));
        $this->assertSame('SNAP', $checkout->json('data.checkout_mode'));
        $this->assertNull($checkout->json('data.payment_url'), 'tidak ada halaman DOKU');
        $this->assertSame([], $this->sentTo(DokuCheckoutService::CHECKOUT_PATH));

        // Token B2B: tanda tangan RSA kita atas clientId|X-TIMESTAMP.
        $tokenRequest = $this->sentTo(DokuSnapClient::TOKEN_PATH)[0];
        $publicKey = openssl_pkey_get_details($this->ourPrivateKey)['key'];
        $this->assertSame(self::CLIENT_ID, $tokenRequest->header('X-CLIENT-KEY')[0]);
        $this->assertSame(1, openssl_verify(self::CLIENT_ID.'|'.$tokenRequest->header('X-TIMESTAMP')[0], base64_decode($tokenRequest->header('X-SIGNATURE')[0]), $publicKey, OPENSSL_ALGO_SHA256));
        $this->assertSame('client_credentials', $tokenRequest->data()['grantType']);

        // Generate QRIS: HMAC-SHA512 atas body yang PERSIS dikirim + token.
        $bill = $this->bill($checkout->json('data.order_id'));
        $invoice = $bill->transaction_id;
        $generate = $this->sentTo(DokuSnapService::QRIS_GENERATE_PATH)[0];
        $this->assertSame($this->expectedHmac('POST', DokuSnapService::QRIS_GENERATE_PATH, self::TOKEN, $generate->body(), $generate->header('X-TIMESTAMP')[0], self::SECRET), $generate->header('X-SIGNATURE')[0]);
        $this->assertSame('Bearer '.self::TOKEN, $generate->header('Authorization')[0]);
        $this->assertSame(self::CLIENT_ID, $generate->header('X-PARTNER-ID')[0]);
        $this->assertSame('H2H', $generate->header('CHANNEL-ID')[0]);
        $this->assertMatchesRegularExpression('/^\d+$/', $generate->header('X-EXTERNAL-ID')[0]);
        $this->assertSame($invoice, $generate->data()['partnerReferenceNo']);
        $this->assertSame(number_format((float) $bill->amount, 2, '.', ''), $generate->data()['amount']['value']);
        $this->assertSame(['MID-TEST', 'TID001'], [$generate->data()['merchantId'], $generate->data()['terminalId']]);

        // Token dipakai ulang (cache), tidak diminta tiap request.
        $this->assertCount(1, $this->sentTo(DokuSnapClient::TOKEN_PATH));

        // Invoice menampilkan QR dari teks QR DOKU (halaman bayar kita sendiri).
        $session = DokuSessionGuard::session($bill, $invoice);
        $this->assertSame('SNAP', $session['api']);
        $ticket = $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/padel/bookings/'.$checkout->json('data.booking_id').'/ticket')->assertOk();
        $this->assertSame('QRIS', $ticket->json('data.pay_display.type'));
        $this->assertSame('00020101021226QRIS-'.$invoice, $ticket->json('data.pay_display.qr_content'));
        $this->assertTrue($ticket->json('data.pay_display.sandbox'));

        // Halaman invoice memuat panel bayar sendiri + library QR lokal.
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->customer, 'web')->get(route('customer.invoice'))->assertOk()
            ->assertSee('data-doku-snap-panel', false)
            ->assertSee('js/qrcode.min.js', false);
    }

    public function test_paid_qris_is_settled_by_the_status_check_and_a_wrong_amount_is_not(): void
    {
        $checkout = $this->padelCheckout('QRIS')->assertOk();
        $bill = $this->bill($checkout->json('data.order_id'));
        $bookingId = $checkout->json('data.booking_id');

        // Nominal dibayar beda → [ALERT], tidak dilunasi otomatis.
        $this->qrisStatus[$bill->transaction_id] = '00';
        $this->paidAmounts[$bill->transaction_id] = 1000;
        $this->getJson("/api/v1/padel/bookings/{$bookingId}/ticket?verify_payment=1")->assertOk();
        $this->assertSame('PENDING', $bill->fresh()->status);

        unset($this->paidAmounts[$bill->transaction_id]);
        \Illuminate\Support\Facades\Cache::flush();
        $this->getJson("/api/v1/padel/bookings/{$bookingId}/ticket?verify_payment=1")->assertOk()->assertJsonPath('data.status', 'PAID');
        $this->assertSame('SUCCESS', $bill->fresh()->status);
        $this->assertSame('QRIS', $bill->fresh()->payment_method);
    }

    public function test_bca_va_is_created_the_dgpc_way_and_shows_the_number_made_by_doku(): void
    {
        $checkout = $this->padelCheckout('BCA_VA')->assertOk();
        $this->assertSame('SNAP', $checkout->json('data.checkout_mode'));

        $create = $this->sentTo(DokuSnapService::VA_CREATE_PATH)[0];
        $body = $create->data();
        // Spesifikasi resmi "SNAP v1.1 - BCA VA - DGPC": BIN rata kanan 8 karakter + prefix sub-BIN, CHANNEL-ID H2H.
        $this->assertSame('   19008', $body['partnerServiceId']);
        $this->assertSame('0', $body['customerNo']);
        $this->assertSame('   190080', $body['virtualAccountNo']);
        $this->assertSame('C', $body['virtualAccountTrxType']);
        $this->assertSame('VIRTUAL_ACCOUNT_BCA', $body['additionalInfo']['channel']);
        $this->assertFalse($body['additionalInfo']['virtualAccountConfig']['reusableStatus']);
        $this->assertSame('Budi', $body['virtualAccountName']);
        $this->assertSame('H2H', $create->header('CHANNEL-ID')[0]);
        $this->assertSame($this->expectedHmac('POST', DokuSnapService::VA_CREATE_PATH, self::TOKEN, $create->body(), $create->header('X-TIMESTAMP')[0], self::SECRET), $create->header('X-SIGNATURE')[0]);

        // Yang tampil = nomor lengkap buatan DOKU (16 digit), bukan yang kita kirim.
        $ticket = $this->getJson('/api/v1/padel/bookings/'.$checkout->json('data.booking_id').'/ticket')->assertOk();
        $this->assertSame('VA', $ticket->json('data.pay_display.type'));
        $this->assertSame('BCA', $ticket->json('data.pay_display.bank'));
        $this->assertMatchesRegularExpression('/^190080\d{10}$/', $ticket->json('data.pay_display.va_number'));
    }

    public function test_unpaid_va_is_not_paid_even_though_doku_reports_the_bill_amount_as_paid_amount(): void
    {
        $checkout = $this->padelCheckout('BCA_VA')->assertOk();
        $bill = $this->bill($checkout->json('data.order_id'));
        $bookingId = $checkout->json('data.booking_id');

        // Contoh resmi cek status: "Pending / Belum Terbayar" tapi paidAmount = nominal tagihan, tanpa paymentRequestId.
        $this->getJson("/api/v1/padel/bookings/{$bookingId}/ticket?verify_payment=1")->assertOk();
        $this->assertNotEmpty($this->sentTo(DokuSnapService::VA_STATUS_PATH), 'status VA benar-benar dicek');
        $this->assertSame('PENDING', $bill->fresh()->status);
        $this->assertSame('PENDING_PAYMENT', PadelBooking::where('order_id', $bill->order_id)->value('status'));

        // Sudah dibayar (paymentRequestId ada, alasan "Success") → lunas.
        $this->vaStatus[$bill->transaction_id] = '00';
        \Illuminate\Support\Facades\Cache::flush();
        $this->getJson("/api/v1/padel/bookings/{$bookingId}/ticket?verify_payment=1")->assertOk()->assertJsonPath('data.status', 'PAID');
        $this->assertSame('SUCCESS', $bill->fresh()->status);
    }

    public function test_snap_with_empty_or_malformed_account_data_falls_back_to_doku_checkout_and_says_why(): void
    {
        $log = new \Monolog\Handler\TestHandler;
        config(['logging.channels.doku' => ['driver' => 'monolog', 'handler' => \Monolog\Handler\NullHandler::class]]);
        \Illuminate\Support\Facades\Log::channel('doku')->getLogger()->setHandlers([$log]);
        config(['services.doku.snap.qris_terminal_id' => '...', 'services.doku.snap.qris_postal_code' => '']);

        $this->padelCheckout('QRIS')->assertOk()->assertJsonPath('data.checkout_mode', 'REDIRECT');

        $this->assertSame([], $this->sentTo(DokuSnapService::QRIS_GENERATE_PATH), 'isian ngawur tidak dikirim ke DOKU');
        $warning = collect($log->getRecords())->first(fn ($r) => str_contains($r->message, 'DOKU SNAP QRIS tidak dipakai'));
        $this->assertNotNull($warning, 'alasan SNAP tidak dipakai tercatat di log DOKU');
        $this->assertSame(['DOKU_SNAP_QRIS_TERMINAL_ID', 'DOKU_SNAP_QRIS_POSTAL_CODE'], $warning->context['isian']);

        config(['services.doku.snap.bca_partner_service_id' => '19008A']);
        $this->assertSame(['DOKU_SNAP_BCA_PARTNER_SERVICE_ID'], DokuSnapService::invalidAccountData(DokuSnapService::VA));
    }

    public function test_changing_method_expires_the_old_qr_before_creating_the_va(): void
    {
        $checkout = $this->padelCheckout('QRIS')->assertOk();
        $bookingId = $checkout->json('data.booking_id');
        $first = $this->bill($checkout->json('data.order_id'))->transaction_id;

        // Metode sama → QR yang sama (tidak membuat QR baru).
        $this->postJson("/api/v1/padel/bookings/{$bookingId}/retry-payment", ['payment_method' => 'QRIS'])->assertOk()->assertJsonPath('checkout_mode', 'SNAP')->assertJsonPath('reused_session', true);
        $this->assertCount(1, $this->sentTo(DokuSnapService::QRIS_GENERATE_PATH));

        $switch = $this->postJson("/api/v1/padel/bookings/{$bookingId}/retry-payment", ['payment_method' => 'BCA_VA'])->assertOk()->assertJsonPath('checkout_mode', 'SNAP');
        $this->assertSame($first, $this->sentTo(DokuSnapService::QRIS_EXPIRE_PATH)[0]->data()['partnerReferenceNo'], 'QR lama di-expire dulu');
        $bill = $this->bill($checkout->json('data.order_id'));
        $this->assertSame('CLOSED', DokuSessionGuard::session($bill, $first)['status']);
        $this->assertSame('ACTIVE', DokuSessionGuard::session($bill, $switch->json('suffixed_order_id'))['status']);
        $this->getJson("/api/v1/padel/bookings/{$bookingId}/ticket")->assertJsonPath('data.pay_display.type', 'VA');
    }

    public function test_methods_without_snap_still_use_doku_checkout(): void
    {
        $this->padelCheckout('BNI_VA')->assertOk()->assertJsonPath('data.checkout_mode', 'REDIRECT');
        $this->assertCount(1, $this->sentTo(DokuCheckoutService::CHECKOUT_PATH));
        $this->assertSame([], $this->sentTo(DokuSnapService::VA_CREATE_PATH));

        // SNAP dimatikan → QRIS kembali lewat DOKU Checkout.
        config(['services.doku.snap.enabled' => false]);
        $this->padelCheckout('QRIS', '12:00')->assertOk()->assertJsonPath('data.checkout_mode', 'REDIRECT');
    }

    // ── Kode 404 SNAP (G2): dibaca per kode kasus, bukan semua "tidak ditemukan" ───────────────────────────────

    private function captureDokuLog(): \Monolog\Handler\TestHandler
    {
        $log = new \Monolog\Handler\TestHandler;
        config(['logging.channels.doku' => ['driver' => 'monolog', 'handler' => \Monolog\Handler\NullHandler::class]]);
        \Illuminate\Support\Facades\Log::channel('doku')->getLogger()->setHandlers([$log]);

        return $log;
    }

    /** @return array{0: string, 1: array} invoice & sesi SNAP dari checkout */
    private function snapSession(string $method, string $start = '10:00'): array
    {
        $bill = $this->bill($this->padelCheckout($method, $start)->assertOk()->json('data.order_id'));

        return [$bill->transaction_id, DokuSessionGuard::session($bill, $bill->transaction_id)];
    }

    public function test_va_status_404_is_read_by_case_code_and_paid_bill_is_never_taken_as_not_found(): void
    {
        $log = $this->captureDokuLog();
        [$invoice, $session] = $this->snapSession('BCA_VA');
        $snap = app(DokuSnapService::class);
        $state = function (array|string $body, int $http = 404) use ($snap, $invoice, $session) {
            $this->vaRaw[$invoice] = [$body, $http];

            return $snap->state($invoice, $session)['state'];
        };

        // Kode kasus SNAP: 14 = Paid Bill → tagihan SUDAH dibayar, tidak boleh dianggap hilang/tertutup.
        $this->assertSame(DokuCheckoutService::ERROR, $state(['responseCode' => '4042614', 'responseMessage' => 'Paid Bill']));
        $this->assertSame(DokuCheckoutService::ERROR, $state(['responseCode' => '4042614', 'responseMessage' => 'Paid Bill']));
        $alerts = collect($log->getRecords())->filter(fn ($r) => str_contains($r->message, '[ALERT]') && str_contains($r->message, $invoice));
        $this->assertCount(1, $alerts, 'Paid Bill memicu satu [ALERT], tidak berulang');

        // 404 lain / 404 tanpa kode SNAP (mis. halaman nginx) = belum pasti → ERROR (dicoba lagi).
        $this->assertSame(DokuCheckoutService::ERROR, $state(['responseCode' => '4042618', 'responseMessage' => 'Inconsistent Request']));
        $this->assertSame(DokuCheckoutService::ERROR, $state('<html>404 Not Found</html>'));
        $this->assertSame(DokuCheckoutService::ERROR, $state(['responseCode' => '4042608', 'responseMessage' => 'Invalid Merchant']));

        // Yang memang berarti tidak ada / tidak bisa dibayar lagi.
        $this->assertSame(DokuCheckoutService::NOT_FOUND, $state(['responseCode' => '4042601', 'responseMessage' => 'Transaction Not Found']));
        $this->assertSame(DokuCheckoutService::NOT_FOUND, $state(['responseCode' => '4042612', 'responseMessage' => 'Invalid Bill/Virtual Account']));
        $this->assertSame(DokuCheckoutService::CLOSED, $state(['responseCode' => '4042619', 'responseMessage' => 'Invalid Bill/Virtual Account']));
    }

    public function test_qris_query_404_is_not_found_only_for_transaction_not_found(): void
    {
        [$invoice, $session] = $this->snapSession('QRIS');
        $snap = app(DokuSnapService::class);
        $state = function (array|string $body, int $http = 404) use ($snap, $invoice, $session) {
            $this->qrisRaw[$invoice] = [$body, $http];

            return $snap->state($invoice, $session)['state'];
        };

        $this->assertSame(DokuCheckoutService::ERROR, $state(['responseCode' => '4045118', 'responseMessage' => 'Inconsistent Request']));
        $this->assertSame(DokuCheckoutService::ERROR, $state('<html>404 Not Found</html>'));
        $this->assertSame(DokuCheckoutService::NOT_FOUND, $state(['responseCode' => '4045101', 'responseMessage' => 'Transaction Not Found']));
        $this->assertSame(DokuCheckoutService::NOT_FOUND, $state(['responseCode' => '2005100', 'originalPartnerReferenceNo' => $invoice, 'latestTransactionStatus' => '07'], 200));
    }

    public function test_closing_a_va_that_doku_reports_not_found_still_deletes_it(): void
    {
        [$invoice, $session] = $this->snapSession('BCA_VA');
        $this->vaRaw[$invoice] = [['responseCode' => '4042601', 'responseMessage' => 'Transaction Not Found'], 404];

        $closed = app(DokuSnapService::class)->close($invoice, $session);

        $this->assertSame(DokuCheckoutService::NOT_FOUND, $closed['result']);
        $this->assertCount(1, $this->sentTo(DokuSnapService::VA_DELETE_PATH), 'VA tetap diminta dihapus di DOKU');
        $this->assertSame($invoice, $this->sentTo(DokuSnapService::VA_DELETE_PATH)[0]->data()['trxId']);
    }

    public function test_va_deleted_by_doku_counts_as_closed_even_if_the_status_answer_after_it_is_unreadable(): void
    {
        $this->captureDokuLog();
        [$invoice, $session] = $this->snapSession('BCA_VA');
        $snap = app(DokuSnapService::class);
        $this->deletedVaAnswer = [['responseCode' => '4042600', 'responseMessage' => 'Invalid Transaction Status'], 404];

        // DOKU menerima delete-va → tidak bisa dibayar lagi → ganti metode tetap jalan.
        $this->assertSame(DokuCheckoutService::CLOSED, $snap->close($invoice, $session)['result']);

        // DOKU menolak delete-va dan statusnya tidak terbaca → BELUM pasti tertutup.
        [$other, $otherSession] = $this->snapSession('BCA_VA', '12:00');
        $this->vaDeleteWorks = false;
        $this->vaRaw[$other] = [['responseCode' => '4042600', 'responseMessage' => 'Invalid Transaction Status'], 404];
        $this->assertSame('FAILED', $snap->close($other, $otherSession)['result']);
    }

    // ── Rekonsiliasi (G3) & notifikasi yang tidak pernah tuntas (G4) ────────────────────────────────────────────

    public function test_reconcile_runs_when_only_snap_keys_are_set(): void
    {
        [$invoice] = $this->snapSession('BCA_VA');
        // Kunci DOKU Checkout dikosongkan (rencana mematikan non-SNAP); SNAP tetap terisi.
        config(['services.doku.client_id' => '', 'services.doku.secret_key' => '']);
        $this->app->forgetInstance(\App\Services\Payment\Doku\DokuClient::class);
        $this->app->forgetInstance(DokuCheckoutService::class);

        $notification = DokuNotification::create([
            'request_id' => 'SNAP-VA-RETRY-1', 'invoice_number' => $invoice, 'status' => 'SUCCESS',
            'amount' => $this->amountOf($invoice), 'result' => DokuNotification::RETRY, 'attempts' => 1, 'received_at' => now()->subMinutes(10),
        ]);
        $this->vaStatus[$invoice] = '00';

        $this->artisan('payment:reconcile-doku')->doesntExpectOutputToContain('dilewati')->assertSuccessful();

        $this->assertSame(DokuNotification::PROCESSED, $notification->fresh()->result);
        $this->assertSame('SUCCESS', Payment::where('transaction_id', $invoice)->value('status'));
    }

    public function test_success_notification_that_runs_out_of_attempts_is_alerted_once_and_leaves_the_queue(): void
    {
        $log = $this->captureDokuLog();
        $notification = DokuNotification::create([
            'request_id' => 'SNAP-VA-LOST-1', 'invoice_number' => 'INV-TIDAK-DIKENAL-1', 'status' => 'SUCCESS', 'amount' => 250000,
            'result' => DokuNotification::UNMATCHED, 'attempts' => DokuNotification::MAX_ATTEMPTS - 1, 'received_at' => now()->subHours(3),
        ]);

        app(\App\Services\Payment\Doku\DokuNotificationProcessor::class)->process($notification->id);
        app(\App\Services\Payment\Doku\DokuNotificationProcessor::class)->process($notification->id);
        $this->artisan('payment:reconcile-doku')->assertSuccessful();

        $this->assertSame(DokuNotification::EXHAUSTED, $notification->fresh()->result);
        $this->assertSame(DokuNotification::MAX_ATTEMPTS, $notification->fresh()->attempts, 'tidak diproses lagi setelah habis');
        $alerts = collect($log->getRecords())->filter(fn ($r) => str_contains($r->message, '[ALERT]') && str_contains($r->message, 'INV-TIDAK-DIKENAL-1'));
        $this->assertCount(1, $alerts);
        $this->assertStringContainsString('cek manual', $alerts->first()->message);

        $activity = \App\Models\Audit\ActivityLog::where('event', 'payment.doku_notification_unresolved')->sole();
        $this->assertSame('CRITICAL', $activity->severity);
        $this->assertSame('INV-TIDAK-DIKENAL-1', $activity->meta['invoice']);

        // DOKU mengirim ulang notifikasi yang sama → tidak diproses & tidak di-alert lagi.
        $this->assertTrue(DokuNotification::isFinal(DokuNotification::EXHAUSTED));
    }

    public function test_unresolved_notification_older_than_the_retry_window_is_alerted_once_by_the_reconciler(): void
    {
        $log = $this->captureDokuLog();
        $stale = DokuNotification::create([
            'request_id' => 'SNAP-VA-STALE-1', 'invoice_number' => 'INV-BASI-1', 'status' => 'SUCCESS', 'amount' => 150000,
            'result' => DokuNotification::RETRY, 'attempts' => 4, 'received_at' => now()->subHours(DokuNotification::RETRY_HOURS + 1),
        ]);
        // Notifikasi non-lunas yang basi juga dikeluarkan dari antrean, tapi bukan peringatan KRITIS.
        $expired = DokuNotification::create([
            'request_id' => 'SNAP-QR-STALE-1', 'invoice_number' => 'INV-BASI-2', 'status' => 'EXPIRED',
            'result' => DokuNotification::RECEIVED, 'attempts' => 0, 'received_at' => now()->subHours(DokuNotification::RETRY_HOURS + 1),
        ]);

        $this->artisan('payment:reconcile-doku')->assertSuccessful();
        $this->artisan('payment:reconcile-doku')->assertSuccessful();

        $this->assertSame(DokuNotification::EXHAUSTED, $stale->fresh()->result);
        $this->assertSame(DokuNotification::EXHAUSTED, $expired->fresh()->result);
        $this->assertCount(1, collect($log->getRecords())->filter(fn ($r) => str_contains($r->message, 'INV-BASI-1')));
        $this->assertSame('CRITICAL', \App\Models\Audit\ActivityLog::where('event', 'payment.doku_notification_unresolved')->where('meta->invoice', 'INV-BASI-1')->value('severity'));
        $this->assertSame('WARNING', \App\Models\Audit\ActivityLog::where('event', 'payment.doku_notification_unresolved')->where('meta->invoice', 'INV-BASI-2')->value('severity'));
    }

    // ── Masuk: token untuk DOKU & notifikasi ────────────────────────────────────────────────────────────────────

    private function dokuTokenRequest(?string $timestamp = null, ?\OpenSSLAsymmetricKey $key = null): \Illuminate\Testing\TestResponse
    {
        $timestamp ??= DokuSnapClient::timestamp();
        openssl_sign(self::CLIENT_ID.'|'.$timestamp, $signature, $key ?? $this->dokuPrivateKey, OPENSSL_ALGO_SHA256);

        return $this->withHeaders(['X-CLIENT-KEY' => self::CLIENT_ID, 'X-TIMESTAMP' => $timestamp, 'X-SIGNATURE' => base64_encode($signature)])
            ->postJson('/api/v1/payments/doku-snap/v1.0/access-token/b2b', ['grantType' => 'client_credentials']);
    }

    public function test_doku_gets_a_token_only_with_its_own_signature(): void
    {
        $this->dokuTokenRequest()->assertOk()->assertJsonPath('responseCode', '2007300')->assertJsonPath('tokenType', 'Bearer');

        $this->dokuTokenRequest(key: $this->ourPrivateKey)->assertStatus(401)->assertJsonPath('responseCode', '4017300');
        $this->dokuTokenRequest(timestamp: now()->subMinutes(20)->timezone('Asia/Jakarta')->format('c'))->assertStatus(401);
    }

    public function test_inbound_debug_log_shows_the_request_shape_without_secrets_and_does_not_change_verification(): void
    {
        $log = $this->captureDokuLog();
        $diagnoses = fn () => collect($log->getRecords())->filter(fn ($r) => str_contains($r->message, '(diagnosa)'))->values();

        // Bawaan mati: tidak ada yang dicatat.
        $token = $this->dokuTokenRequest()->assertOk()->json('accessToken');
        $this->assertCount(0, $diagnoses());

        config(['services.doku.snap.debug_inbound' => true]);
        $tokenResponse = $this->dokuTokenRequest()->assertOk();
        $this->flushHeaders();
        $body = ['originalPartnerReferenceNo' => 'INV-TIDAK-ADA', 'latestTransactionStatus' => '00', 'customerName' => 'Budi Rahasia',
            'amount' => ['value' => '10000.00', 'currency' => 'IDR'], 'additionalInfo' => ['channel' => 'QRIS']];
        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-PARTNER-ID' => self::CLIENT_ID, 'X-EXTERNAL-ID' => '901', 'CHANNEL-ID' => '95221', 'X-SIGNATURE' => base64_encode(random_bytes(64))])
            ->postJson('/api/v1/payments/doku-snap/v1.0/qr/qr-mpm-notify', $body)->assertOk();
        $this->flushHeaders();
        // Diagnosa tidak melonggarkan apa pun: token palsu tetap ditolak; token tanpa skema tidak ikut tertulis.
        $this->withHeader('Authorization', 'tokenpalsu.tanpa.skema')->postJson('/api/v1/payments/doku-snap/v1.1/transfer-va/payment', [])->assertStatus(401);

        [$tokenLog, $qrisLog, $vaLog] = $diagnoses()->all();
        $this->assertStringContainsString('[token]', $tokenLog->message);
        $this->assertTrue($tokenLog->context['x_client_key_matches']);
        $this->assertSame(['decoded_bytes' => 256, 'kind' => 'asimetris (RSA)'], \Illuminate\Support\Arr::only($tokenLog->context['x_signature'], ['decoded_bytes', 'kind']));

        $this->assertStringContainsString('[qris]', $qrisLog->message);
        $this->assertSame(['scheme' => 'Bearer', 'length' => strlen($token), 'jwt_alg' => 'RS256'], $qrisLog->context['authorization']);
        $this->assertSame('simetris (HMAC-SHA512)', $qrisLog->context['x_signature']['kind']);
        $this->assertTrue($qrisLog->context['x_partner_id_matches']);
        $this->assertSame(['901', '95221'], [$qrisLog->context['x_external_id'], $qrisLog->context['channel_id']]);
        $this->assertSame(['originalPartnerReferenceNo', 'latestTransactionStatus', 'customerName', 'amount', 'additionalInfo'], $qrisLog->context['body_fields']);
        $this->assertSame(['channel'], $qrisLog->context['additional_info_fields']);
        $this->assertContains('authorization', $qrisLog->context['headers']);

        $this->assertStringContainsString('[va]', $vaLog->message);
        $this->assertNull($vaLog->context['authorization']['scheme']);

        $written = json_encode($diagnoses()->map(fn ($r) => [$r->message, $r->context])->all());
        foreach ([$token, $tokenResponse->json('accessToken'), self::CLIENT_ID, 'Budi Rahasia', 'INV-TIDAK-ADA', 'tokenpalsu'] as $secret) {
            $this->assertStringNotContainsString($secret, $written);
        }
    }

    public function test_va_notification_with_our_token_is_confirmed_by_the_status_api_before_paying(): void
    {
        $checkout = $this->padelCheckout('BCA_VA')->assertOk();
        $bill = $this->bill($checkout->json('data.order_id'));
        $invoice = $bill->transaction_id;
        $session = DokuSessionGuard::session($bill, $invoice);
        $body = [
            'partnerServiceId' => $session['partner_service_id'], 'customerNo' => $session['customer_no'], 'virtualAccountNo' => $session['va_number'],
            'virtualAccountName' => 'Budi', 'trxId' => $invoice, 'paymentRequestId' => 'PRQ1',
            'paidAmount' => ['value' => number_format((float) $bill->amount, 2, '.', ''), 'currency' => 'IDR'],
        ];
        $this->flushHeaders();

        // Tanpa token / token palsu → ditolak, tidak disimpan.
        $this->postJson('/api/v1/payments/doku-snap/v1.1/transfer-va/payment', $body)->assertStatus(401);
        $this->withHeader('Authorization', 'Bearer abc.def.ghi')->postJson('/api/v1/payments/doku-snap/v1.1/transfer-va/payment', $body)->assertStatus(401);
        $this->assertSame(0, DokuNotification::count());

        $token = $this->dokuTokenRequest()->json('accessToken');

        // Notifikasi sah tapi DOKU belum menyatakan lunas → tidak dilunasi (retry).
        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-EXTERNAL-ID' => '111'])
            ->postJson('/api/v1/payments/doku-snap/v1.1/transfer-va/payment', $body)->assertOk()->assertJsonPath('responseCode', '2002500');
        $this->assertSame('PENDING', $bill->fresh()->status);
        $this->assertSame(DokuNotification::RETRY, DokuNotification::where('request_id', 'SNAP-VA-111')->value('result'));
        $this->assertArrayNotHasKey('virtualAccountName', DokuNotification::where('request_id', 'SNAP-VA-111')->first()->payload, 'nama customer tidak disimpan');

        $this->vaStatus[$invoice] = '00';
        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-EXTERNAL-ID' => '112'])
            ->postJson('/api/v1/payments/doku-snap/v1.1/transfer-va/payment', $body)->assertOk();
        $this->assertSame('SUCCESS', $bill->fresh()->status);
        $this->assertSame('PAID', PadelBooking::where('order_id', $bill->order_id)->value('status'));
        $this->assertSame(DokuNotification::PROCESSED, DokuNotification::where('request_id', 'SNAP-VA-112')->value('result'));
    }

    private function vaNotificationWithoutTrxId(array $session, string $externalId): \Illuminate\Testing\TestResponse
    {
        $token = $this->dokuTokenRequest()->json('accessToken');

        return $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-EXTERNAL-ID' => $externalId])->postJson('/api/v1/payments/doku-snap/v1.1/transfer-va/payment', [
            'partnerServiceId' => $session['partner_service_id'], 'customerNo' => $session['customer_no'], 'virtualAccountNo' => $session['va_number'],
            'paymentRequestId' => 'PRQ-'.$externalId, 'paidAmount' => ['value' => number_format((float) $session['amount'], 2, '.', ''), 'currency' => 'IDR'],
        ]);
    }

    public function test_va_notification_without_trx_id_finds_its_bill_from_the_recorded_va_number(): void
    {
        [$invoice, $session] = $this->snapSession('BCA_VA');
        $digits = preg_replace('/\D/', '', $session['va_number']);
        $this->assertSame($invoice, \App\Models\Payment\DokuSnapVaNumber::where('va_number', $digits)->value('invoice_number'), 'nomor VA dicatat saat dibuat');
        $this->flushHeaders();

        // 8 hari kemudian: cara lama (tagihan 7 hari terakhir) sudah tidak menjangkau → yang menemukan adalah catatan nomor VA.
        $this->travel(8)->days();
        $this->vaNotificationWithoutTrxId($session, '331')->assertOk();

        $this->assertSame($invoice, DokuNotification::where('request_id', 'SNAP-VA-331')->value('invoice_number'));
    }

    public function test_va_payment_and_lookup_still_work_before_the_va_number_table_is_migrated(): void
    {
        \Illuminate\Support\Facades\Schema::drop('doku_snap_va_numbers');

        [$invoice, $session] = $this->snapSession('BCA_VA');
        $this->assertNotEmpty($session['va_number'], 'VA tetap dibuat walau pencatatan nomor VA gagal');
        $this->flushHeaders();

        $this->vaNotificationWithoutTrxId($session, '341')->assertOk();
        $this->assertSame($invoice, DokuNotification::where('request_id', 'SNAP-VA-341')->value('invoice_number'), 'cara lama tetap menemukan tagihannya');
    }

    public function test_qris_notification_settles_the_paid_qr(): void
    {
        $checkout = $this->padelCheckout('QRIS')->assertOk();
        $bill = $this->bill($checkout->json('data.order_id'));
        $this->qrisStatus[$bill->transaction_id] = '00';
        $token = $this->dokuTokenRequest()->json('accessToken');

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-EXTERNAL-ID' => '221'])->postJson('/api/v1/payments/doku-snap/v1.0/qr/qr-mpm-notify', [
            'originalReferenceNo' => 'REF-'.$bill->transaction_id, 'originalPartnerReferenceNo' => $bill->transaction_id,
            'latestTransactionStatus' => '00', 'amount' => ['value' => number_format((float) $bill->amount, 2, '.', ''), 'currency' => 'IDR'],
        ])->assertOk()->assertJsonPath('responseCode', '2005200');

        $this->assertSame('SUCCESS', $bill->fresh()->status);
    }

    public function test_invoice_email_after_a_notification_is_sent_once_after_the_response(): void
    {
        $checkout = $this->padelCheckout('QRIS')->assertOk();
        $bill = $this->bill($checkout->json('data.order_id'));
        $bookingId = $checkout->json('data.booking_id');
        $this->qrisStatus[$bill->transaction_id] = '00';
        $token = $this->dokuTokenRequest()->json('accessToken');
        \Illuminate\Support\Facades\Mail::fake();

        // Notifikasi diproses setelah respons (job) → pelunasan → email (defer) di akhir job itu: tetap terkirim.
        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-EXTERNAL-ID' => '231'])->postJson('/api/v1/payments/doku-snap/v1.0/qr/qr-mpm-notify', [
            'originalReferenceNo' => 'REF-'.$bill->transaction_id, 'originalPartnerReferenceNo' => $bill->transaction_id,
            'latestTransactionStatus' => '00', 'amount' => ['value' => number_format((float) $bill->amount, 2, '.', ''), 'currency' => 'IDR'],
        ])->assertOk();
        $this->assertSame('SUCCESS', $bill->fresh()->status);
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\OrderInvoiceMail::class, 1);

        // Polling halaman invoice sesudahnya tidak mengirim ulang.
        $this->flushHeaders();
        $this->actingAs($this->customer, 'sanctum')->getJson("/api/v1/padel/bookings/{$bookingId}/ticket?verify_payment=1")->assertOk();
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\OrderInvoiceMail::class, 1);
    }

    // ── Notifikasi QRIS format non-SNAP (yang benar-benar dikirim DOKU) ───────────────────────────────────────────

    private const QRIS_NOTIFY_PATH = '/api/v1/payments/doku-snap/v1.0/qr/qr-mpm-notify';

    /** Persis bentuk kiriman DOKU: Client-Id/Request-Id/Request-Timestamp/Signature HMACSHA256 (Secret Key DOKU), tanpa token. */
    private function nonSnapQrisNotification(Payment $bill, string $requestId, ?string $target = null, ?string $secret = null): \Illuminate\Testing\TestResponse
    {
        $raw = json_encode([
            'service' => ['id' => 'QRIS', 'name' => 'QRIS'],
            'acquirer' => ['id' => 'DOKU', 'name' => 'DOKU'],
            'channel' => ['id' => 'QRIS_DOKU', 'name' => 'QRIS-DOKU'],
            'customer' => ['doku_id' => '1673637808', 'name' => 'Budi Rahasia', 'email' => 'budi.rahasia@example.test', 'phone' => '6281747637808'],
            'order' => ['invoice_number' => $bill->transaction_id, 'amount' => (float) $bill->amount],
            'emoney_payment' => ['account_id' => '1673637808', 'approval_code' => '234604941'],
            'transaction' => ['status' => 'SUCCESS', 'date' => now()->utc()->format('Y-m-d\TH:i:s\Z'), 'original_request_id' => 'ORQ-1'],
            'additional_info' => ['origin' => ['product' => 'QRIS', 'system' => 'snap-adapter', 'apiFormat' => 'SNAP', 'source' => 'DIRECT']],
        ]);
        $timestamp = now()->utc()->format('Y-m-d\TH:i:s\Z');
        $this->flushHeaders();

        return $this->call('POST', self::QRIS_NOTIFY_PATH, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_CLIENT_ID' => 'BRN-CHECKOUT-TEST',
            'HTTP_REQUEST_ID' => $requestId,
            'HTTP_REQUEST_TIMESTAMP' => $timestamp,
            'HTTP_SIGNATURE' => \App\Services\Payment\Doku\DokuSignature::make('BRN-CHECKOUT-TEST', $requestId, $timestamp, $target ?? self::QRIS_NOTIFY_PATH, $raw, $secret ?? 'SK-checkout-test'),
        ], $raw);
    }

    public function test_qris_notification_in_doku_non_snap_format_settles_after_the_status_check(): void
    {
        $bill = $this->bill($this->padelCheckout('QRIS')->assertOk()->json('data.order_id'));
        $this->qrisStatus[$bill->transaction_id] = '00';

        $this->nonSnapQrisNotification($bill, 'rid-qris-1')->assertOk()->assertJsonPath('success', true);

        $this->assertSame('SUCCESS', $bill->fresh()->status);
        $this->assertSame(DokuNotification::PROCESSED, DokuNotification::where('request_id', 'rid-qris-1')->value('result'));
        $this->assertNotEmpty($this->sentTo(DokuSnapService::QRIS_QUERY_PATH), 'status dicek ulang lewat qr-mpm-query sebelum lunas');
        $this->assertArrayNotHasKey('customer', DokuNotification::where('request_id', 'rid-qris-1')->first()->payload, 'data customer tidak disimpan');
    }

    public function test_non_snap_qris_notification_with_a_wrong_signature_or_another_path_is_rejected(): void
    {
        $bill = $this->bill($this->padelCheckout('QRIS')->assertOk()->json('data.order_id'));
        $this->qrisStatus[$bill->transaction_id] = '00';

        $this->nonSnapQrisNotification($bill, 'rid-qris-2', secret: 'SK-palsu')->assertStatus(401);
        // Tanda tangan sah, tapi dibuat untuk alamat notifikasi Checkout → Request-Target tidak cocok.
        $this->nonSnapQrisNotification($bill, 'rid-qris-3', target: '/api/v1/payments/doku/notification')->assertStatus(401);

        $this->assertSame(0, DokuNotification::count());
        $this->assertSame('PENDING', $bill->fresh()->status);
    }

    public function test_same_non_snap_qris_notification_sent_twice_is_processed_once(): void
    {
        $bill = $this->bill($this->padelCheckout('QRIS')->assertOk()->json('data.order_id'));
        $this->qrisStatus[$bill->transaction_id] = '00';
        \Illuminate\Support\Facades\Mail::fake();

        $this->nonSnapQrisNotification($bill, 'rid-qris-4')->assertOk()->assertJsonPath('message', 'Received.');
        $this->app->terminate();
        $this->nonSnapQrisNotification($bill, 'rid-qris-4')->assertOk()->assertJsonPath('message', 'Already processed.');
        $this->app->terminate();

        $this->assertSame(1, DokuNotification::where('request_id', 'rid-qris-4')->count());
        $this->assertSame(1, Payment::where('order_id', $bill->order_id)->where('status', 'SUCCESS')->count());
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\OrderInvoiceMail::class, 1);
    }

    public function test_non_snap_qris_notification_is_not_paid_until_doku_confirms(): void
    {
        $bill = $this->bill($this->padelCheckout('QRIS')->assertOk()->json('data.order_id'));

        $this->nonSnapQrisNotification($bill, 'rid-qris-5')->assertOk();

        $this->assertSame('PENDING', $bill->fresh()->status, 'status DOKU masih pending → belum dilunasi');
        $this->assertSame(DokuNotification::RETRY, DokuNotification::where('request_id', 'rid-qris-5')->value('result'));
    }

    public function test_notification_after_polling_already_paid_adds_no_second_payment_or_email(): void
    {
        $checkout = $this->padelCheckout('QRIS')->assertOk();
        $bill = $this->bill($checkout->json('data.order_id'));
        $this->qrisStatus[$bill->transaction_id] = '00';
        \Illuminate\Support\Facades\Mail::fake();
        $log = $this->captureDokuLog();

        $this->getJson("/api/v1/padel/bookings/{$checkout->json('data.booking_id')}/ticket?verify_payment=1")->assertJsonPath('data.status', 'PAID');
        $this->app->terminate();
        $this->nonSnapQrisNotification($bill, 'rid-qris-6')->assertOk();
        $this->app->terminate();

        $this->assertSame(DokuNotification::PROCESSED, DokuNotification::where('request_id', 'rid-qris-6')->value('result'));
        $this->assertSame(1, Payment::where('order_id', $bill->order_id)->where('status', 'SUCCESS')->count(), 'satu catatan pembayaran');
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\OrderInvoiceMail::class, 1);
        $messages = collect($log->getRecords())->pluck('message');
        $this->assertTrue($messages->contains(fn ($m) => str_contains($m, 'sudah LUNAS lewat') && str_contains($m, 'tidak mengubah apa pun')), 'log tidak terbaca seperti pelunasan kedua');
    }

    // ── Notifikasi VA: X-SIGNATURE HMAC-SHA512 di samping token ──────────────────────────────────────────────────

    public function test_va_notification_signature_is_watched_by_default_and_rejected_when_enforced(): void
    {
        $bill = $this->bill($this->padelCheckout('BCA_VA')->assertOk()->json('data.order_id'));
        $session = DokuSessionGuard::session($bill, $bill->transaction_id);
        $body = ['partnerServiceId' => $session['partner_service_id'], 'customerNo' => $session['customer_no'], 'virtualAccountNo' => $session['va_number'],
            'trxId' => $bill->transaction_id, 'paymentRequestId' => 'PRQ-SIG', 'paidAmount' => ['value' => number_format((float) $bill->amount, 2, '.', ''), 'currency' => 'IDR']];
        $path = '/api/v1/payments/doku-snap/v1.1/transfer-va/payment';
        $token = $this->dokuTokenRequest()->json('accessToken');
        $timestamp = DokuSnapClient::timestamp();
        $good = DokuSnapClient::symmetricSignature('POST', $path, $token, DokuSnapClient::minify($body), $timestamp, self::SECRET);
        $send = function (string $signature, string $externalId) use ($body, $path, $token, $timestamp) {
            $this->flushHeaders();

            return $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-TIMESTAMP' => $timestamp, 'X-SIGNATURE' => $signature, 'X-EXTERNAL-ID' => $externalId])->postJson($path, $body);
        };
        $log = $this->captureDokuLog();

        // Mode pantau (bawaan): cocok / tidak dicatat, notifikasi tetap diterima karena token sah.
        $send($good, '501')->assertOk();
        $send(base64_encode(random_bytes(64)), '502')->assertOk();
        $messages = collect($log->getRecords())->pluck('message');
        $this->assertTrue($messages->contains('Notifikasi VA DOKU SNAP: X-SIGNATURE cocok'));
        $this->assertTrue($messages->contains(fn ($m) => str_contains($m, 'X-SIGNATURE TIDAK cocok (mode pantau')));

        // Ditegakkan: tidak cocok → 401 & tidak disimpan; cocok → diterima.
        config(['services.doku.snap.verify_va_signature' => true]);
        $send(base64_encode(random_bytes(64)), '503')->assertStatus(401)->assertJsonPath('responseCode', '4012500');
        $this->assertFalse(DokuNotification::where('request_id', 'SNAP-VA-503')->exists());
        $send($good, '504')->assertOk()->assertJsonPath('responseCode', '2002500');
    }

    // ── Token B2B: format persis kiriman DOKU & pengaman kunci ketuker ───────────────────────────────────────────

    public function test_token_request_exactly_like_doku_sends_it_is_accepted(): void
    {
        $this->travelTo(Carbon::parse('2026-10-27T14:48:29+07:00'));

        $this->dokuTokenRequest('2026-10-27T14:48:29+07:00')->assertOk()->assertJsonPath('responseCode', '2007300');

        $check = app(DokuSnapClient::class)->dokuPublicKeyCheck();
        $this->assertSame([true, 2048, false], [$check['parsed'], $check['bits'], $check['same_as_our_key']]);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $check['fingerprint']);
    }

    public function test_token_rejection_says_so_when_the_doku_public_key_file_holds_our_own_key(): void
    {
        file_put_contents($this->keyDir.'/doku-public.pem', openssl_pkey_get_details($this->ourPrivateKey)['key']);
        $log = $this->captureDokuLog();

        $this->dokuTokenRequest()->assertStatus(401);

        $alert = collect($log->getRecords())->first(fn ($r) => str_contains($r->message, 'permintaan token DITOLAK'));
        $this->assertStringContainsString('berisi public key KITA sendiri', $alert->message);
        $this->assertTrue($alert->context['doku_public_key']['same_as_our_key']);
        $this->assertStringNotContainsString('BEGIN PUBLIC KEY', json_encode($alert->context), 'isi kunci tidak ditulis');

        file_put_contents($this->keyDir.'/doku-public.pem', 'bukan kunci');
        $this->assertSame(['parsed' => false], app(DokuSnapClient::class)->dokuPublicKeyCheck());
    }

    public function test_polling_shows_paid_even_when_the_invoice_email_fails(): void
    {
        $checkout = $this->padelCheckout('QRIS')->assertOk();
        $bill = $this->bill($checkout->json('data.order_id'));
        $bookingId = $checkout->json('data.booking_id');
        $this->qrisStatus[$bill->transaction_id] = '00';
        // SMTP mati: email gagal SETELAH respons — halaman invoice tetap langsung "Lunas".
        \Illuminate\Support\Facades\Mail::shouldReceive('mailer')->with('billing')->andThrow(new \RuntimeException('SMTP timeout'));

        $this->getJson("/api/v1/padel/bookings/{$bookingId}/ticket?verify_payment=1")->assertOk()->assertJsonPath('data.status', 'PAID');

        $this->assertSame('SUCCESS', $bill->fresh()->status);
        $this->assertNull(\App\Models\Pos\Order::where('order_number', $checkout->json('data.order_id'))->value('invoice_emailed_at'), 'klaim dilepas → bisa dikirim ulang');
    }

    // ── Kasir ───────────────────────────────────────────────────────────────────────────────────────────────────

    public function test_cashier_qris_shows_our_own_qr_and_cancel_expires_it(): void
    {
        $cashier = User::factory()->cashier()->create(['is_active' => true]);
        PosCashierShift::create([
            'shift_number' => 'SFT-SNAP-0001', 'counter' => 'PADEL_FRONTDESK', 'status' => 'OPEN',
            'opened_by_id' => $cashier->id, 'opened_at' => now(), 'starting_cash' => 0, 'expected_cash' => 0,
        ]);
        $service = app(PadelBookingService::class);
        $pending = $service->processWalkInCheckout(
            customer: $service->findOrCreateWalkInCustomer(name: 'Sari', phone: '081234567899'),
            slots: [['court_id' => $this->court->id, 'start_time' => '14:00:00', 'end_time' => '15:00:00']],
            bookingDate: $this->date, equipments: [], paymentMethod: 'QRIS_MIDTRANS', cashier: $cashier, paymentMeta: [],
        )['pending_qris'];

        $this->assertSame('DOKU', $pending['gateway']);
        $this->assertNull($pending['frame_url']);
        $this->assertSame('QRIS', $pending['snap']['type']);
        $this->assertStringStartsWith('00020101021226QRIS-', $pending['snap']['qr_content']);

        // Kartu kasir menggambar QR sendiri (tanpa iframe / halaman DOKU).
        $html = view('pos.partials.auto-pay-modal', ['pendingQris' => $pending, 'pollAction' => 'poll', 'cancelAction' => 'cancel', 'simulateAction' => 'sim'])->render();
        $this->assertStringContainsString('data-doku-snap-pos', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        // Library QR dimuat lewat @assets Livewire — dibuktikan di tests/Feature/Pos/AutoPayModalAssetsTest.php.

        $this->assertSame(PosAutoPayment::CANCELLED, app(PosAutoPayment::class)->cancel($pending['payment_id']));
        $this->assertCount(1, $this->sentTo(DokuSnapService::QRIS_EXPIRE_PATH));
        $this->assertSame('CANCELLED', Order::find($pending['order_id'])->payment_status);
    }

    // ── VA bank lain lewat SNAP (Partner Service ID diisi admin) ───────────────────────────────────────────────

    private function setSnapVa(array $accounts): void
    {
        PaymentGatewaySetting::query()->firstOrFail()->update(['doku_snap_va' => $accounts]);
        app(DokuSnapVaAccounts::class)->flush();
    }

    public function test_other_bank_va_uses_snap_only_after_its_partner_service_id_is_set(): void
    {
        // Belum diisi → tetap DOKU Checkout, tanpa [ALERT] (memang belum dipakai, bukan salah isi).
        $log = $this->captureDokuLog();
        $this->padelCheckout('BNI_VA')->assertOk()->assertJsonPath('data.checkout_mode', 'REDIRECT');
        $this->assertSame([], $this->sentTo(DokuSnapService::VA_CREATE_PATH));
        $this->assertFalse(collect($log->getRecords())->contains(fn ($r) => str_contains($r->message, 'tidak dipakai')));

        $this->setSnapVa(['VIRTUAL_ACCOUNT_BNI' => ['partner_service_id' => '98829172', 'customer_no' => '7']]);
        $checkout = $this->padelCheckout('BNI_VA', '12:00')->assertOk()->assertJsonPath('data.checkout_mode', 'SNAP');

        $body = $this->sentTo(DokuSnapService::VA_CREATE_PATH)[0]->data();
        $this->assertSame('98829172', $body['partnerServiceId']);
        $this->assertSame('7', $body['customerNo']);
        $this->assertSame('988291727', $body['virtualAccountNo']);
        $this->assertSame('VIRTUAL_ACCOUNT_BNI', $body['additionalInfo']['channel']);
        $this->assertSame('C', $body['virtualAccountTrxType']);

        $bookingId = $checkout->json('data.booking_id');
        $ticket = $this->getJson("/api/v1/padel/bookings/{$bookingId}/ticket")->assertOk();
        $this->assertSame('BNI', $ticket->json('data.pay_display.bank'));
        $this->assertMatchesRegularExpression('/^988291727\d{10}$/', $ticket->json('data.pay_display.va_number'));

        // Lunas lewat cek status SNAP (bukan paidAmount saja).
        $bill = $this->bill($checkout->json('data.order_id'));
        $this->vaStatus[$bill->transaction_id] = '00';
        $this->getJson("/api/v1/padel/bookings/{$bookingId}/ticket?verify_payment=1")->assertOk()->assertJsonPath('data.status', 'PAID');
        $this->assertSame('SUCCESS', $bill->fresh()->status);
        $this->assertSame('BNI_VA', $bill->fresh()->payment_method);
    }

    public function test_bsi_va_has_its_own_method_and_uses_the_bsi_snap_channel_name(): void
    {
        // "VA Bank Lain" di DOKU sekarang Permata saja; BSI punya metode sendiri (nonaktif sampai diaktifkan admin).
        $methods = app(\App\Services\Payment\OnlinePaymentMethodService::class);
        $this->assertSame(['VIRTUAL_ACCOUNT_BANK_PERMATA'], $methods->channels('BSI_VA', OnlineGatewayRouter::DOKU));
        $this->assertSame(['VIRTUAL_ACCOUNT_BANK_SYARIAH_MANDIRI'], $methods->channels('BSI_SYARIAH_VA', OnlineGatewayRouter::DOKU));
        $this->assertFalse($methods->all()['BSI_SYARIAH_VA']['is_active']);
        $this->padelCheckout('BSI_SYARIAH_VA')->assertStatus(422);

        \App\Models\Pos\OnlinePaymentMethod::where('code', 'BSI_SYARIAH_VA')->firstOrFail()->update(['is_active' => true]);
        $this->setSnapVa(['VIRTUAL_ACCOUNT_BANK_SYARIAH_MANDIRI' => ['partner_service_id' => '6059', 'customer_no' => '']]);
        $checkout = $this->padelCheckout('BSI_SYARIAH_VA', '12:00')->assertOk()->assertJsonPath('data.checkout_mode', 'SNAP');

        $body = $this->sentTo(DokuSnapService::VA_CREATE_PATH)[0]->data();
        // Dokumentasi resmi SNAP BSI VA: channel "VIRTUAL_ACCOUNT_BSI" (beda dari kode DOKU Checkout).
        $this->assertSame('VIRTUAL_ACCOUNT_BSI', $body['additionalInfo']['channel']);
        $this->assertSame('    6059', $body['partnerServiceId']);
        $this->getJson('/api/v1/padel/bookings/'.$checkout->json('data.booking_id').'/ticket')->assertJsonPath('data.pay_display.bank', 'BSI');

        // Hapus VA (ganti metode / batal) memakai nama kanal SNAP yang sama.
        $bill = $this->bill($checkout->json('data.order_id'));
        $session = DokuSessionGuard::session($bill, $bill->transaction_id);
        $this->assertTrue(app(DokuSnapService::class)->cancel($bill->transaction_id, $session));
        $this->assertSame('VIRTUAL_ACCOUNT_BSI', $this->sentTo(DokuSnapService::VA_DELETE_PATH)[0]->data()['additionalInfo']['channel']);
    }

    public function test_va_deleted_before_a_failed_switch_stays_closed_and_is_never_shown_or_reused(): void
    {
        // Kasus production 22 Okt 2026: VA Mandiri sudah dibuat, customer ganti ke BNI, VA Mandiri dihapus di DOKU,
        // lalu VA BNI ditolak DOKU (BIN belum dikonfigurasi) → transaksi di-rollback.
        $this->setSnapVa([
            'VIRTUAL_ACCOUNT_BANK_MANDIRI' => ['partner_service_id' => '70002', 'customer_no' => '0'],
            'VIRTUAL_ACCOUNT_BNI' => ['partner_service_id' => '98829172', 'customer_no' => ''],
        ]);
        $this->vaCreateRejectedChannel = 'VIRTUAL_ACCOUNT_BNI';
        $checkout = $this->padelCheckout('MANDIRI_VA')->assertOk()->assertJsonPath('data.checkout_mode', 'SNAP');
        $bookingId = $checkout->json('data.booking_id');
        $mandiri = $this->bill($checkout->json('data.order_id'))->transaction_id;

        $this->postJson("/api/v1/padel/bookings/{$bookingId}/retry-payment", ['payment_method' => 'BNI_VA'])->assertStatus(422);
        $this->assertCount(1, $this->sentTo(DokuSnapService::VA_DELETE_PATH), 'VA Mandiri benar-benar dihapus di DOKU');

        // Sesi Mandiri tetap tercatat CLOSED walau transaksinya di-rollback → nomor VA mati tidak tampil.
        $bill = $this->bill($checkout->json('data.order_id'));
        $this->assertSame('CLOSED', DokuSessionGuard::session($bill, $mandiri)['status']);
        $this->assertNull($this->getJson("/api/v1/padel/bookings/{$bookingId}/ticket")->assertOk()->json('data.pay_display'));

        // Pilih Mandiri lagi → VA BARU dibuat (yang terhapus tidak dipakai ulang).
        $again = $this->postJson("/api/v1/padel/bookings/{$bookingId}/retry-payment", ['payment_method' => 'MANDIRI_VA'])->assertOk()->assertJsonPath('checkout_mode', 'SNAP');
        $this->assertNotSame($mandiri, $again->json('suffixed_order_id'));
        $this->assertSame('MANDIRI_VA', $this->getJson("/api/v1/padel/bookings/{$bookingId}/ticket")->json('data.pay_display.method'));
    }

    public function test_cashier_va_uses_snap_and_shows_the_number_on_the_cashier_card(): void
    {
        $this->setSnapVa(['VIRTUAL_ACCOUNT_BANK_MANDIRI' => ['partner_service_id' => '70002', 'customer_no' => '0']]);
        \App\Models\Pos\OnlinePaymentMethod::where('code', 'MANDIRI_VA')->firstOrFail()->update(['show_at_pos' => true]);
        $cashier = User::factory()->cashier()->create(['is_active' => true]);
        PosCashierShift::create([
            'shift_number' => 'SFT-SNAP-0002', 'counter' => 'PADEL_FRONTDESK', 'status' => 'OPEN',
            'opened_by_id' => $cashier->id, 'opened_at' => now(), 'starting_cash' => 0, 'expected_cash' => 0,
        ]);
        $order = Order::create(['order_number' => 'ORD-SNAP-VA-1', 'order_type' => 'WALK_IN', 'subtotal' => 200000, 'grand_total' => 200000, 'payment_status' => 'UNPAID']);

        $pending = app(\App\Services\Payment\Doku\DokuPosQrisService::class)->open($order, 'PADEL_FRONTDESK', $cashier, ['source' => 'WALK_IN_OFFLINE'], 'MANDIRI_VA');

        $this->assertNull($pending['frame_url'], 'tanpa halaman DOKU');
        $this->assertSame('VA', $pending['snap']['type']);
        $this->assertSame('Mandiri', $pending['snap']['bank']);
        $this->assertMatchesRegularExpression('/^700020\d{10}$/', $pending['snap']['va_number']);
        $this->assertSame('VIRTUAL_ACCOUNT_BANK_MANDIRI', $this->sentTo(DokuSnapService::VA_CREATE_PATH)[0]->data()['additionalInfo']['channel']);

        $html = view('pos.partials.auto-pay-modal', ['pendingQris' => $pending, 'pollAction' => 'poll', 'cancelAction' => 'cancel', 'simulateAction' => 'sim'])->render();
        $this->assertStringContainsString('Nomor Virtual Account Mandiri', $html);
        $this->assertStringNotContainsString('<iframe', $html);
    }

    /** Isian form Edit metode bawaan (nama, badge, nominal) + DOKU SNAP. */
    private function editData(\App\Models\Pos\OnlinePaymentMethod $method, array $snap = []): array
    {
        return ['label' => $method->label, 'description' => $method->description, 'badge' => $method->badge, 'min_amount' => null, 'max_amount' => null] + $snap;
    }

    public function test_snap_va_data_is_edited_inside_the_method_form_only_while_doku_is_used(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $bri = \App\Models\Pos\OnlinePaymentMethod::where('code', 'BRI_VA')->firstOrFail();
        $page = \Livewire\Livewire::test(\App\Filament\Pages\MetodePembayaranOnline::class)->assertTableColumnVisible('doku_route');
        \Livewire\Livewire::test(\App\Filament\Pages\MetodePembayaranOnline::class)->mountTableAction('edit', $bri)
            ->assertMountedActionModalSee(['DOKU SNAP', 'Partner Service ID (BIN)', 'Configure di bank BRI']);

        $page->callTableAction('edit', $bri, $this->editData($bri, ['doku_snap_partner_service_id' => '12362', 'doku_snap_customer_no' => '1']))
            ->assertHasNoTableActionErrors();
        $this->assertSame(['VIRTUAL_ACCOUNT_BRI' => ['partner_service_id' => '12362', 'customer_no' => '1']], app(DokuSnapVaAccounts::class)->saved());
        $this->assertSame('SNAP', app(DokuSnapService::class)->routeFor(['VIRTUAL_ACCOUNT_BRI']));
        $this->assertSame('CHECKOUT', app(DokuSnapService::class)->routeFor(['VIRTUAL_ACCOUNT_BNI']));
        $this->assertTrue(\App\Models\Audit\ActivityLog::where('subject_type', 'PaymentGatewaySetting')->where('severity', 'CRITICAL')->exists(), 'tercatat di Log Aktivitas');

        // Isian ngawur dikirim langsung (melewati form) ditolak server — metodenya juga tidak ikut tersimpan.
        $page->call('saveSettings', $bri, $this->editData($bri, ['doku_snap_partner_service_id' => '12AB']) + ['label' => 'BRI Ganti']);
        $page->call('saveSettings', $bri, $this->editData($bri, ['doku_snap_partner_service_id' => '', 'doku_snap_customer_no' => '5']));
        $cimb = \App\Models\Pos\OnlinePaymentMethod::where('code', 'CIMB_VA')->firstOrFail();
        $page->call('saveSettings', $cimb, $this->editData($cimb, ['doku_snap_partner_service_id' => '55555']) + ['label' => 'CIMB Ganti']);
        $this->assertSame('12362', app(DokuSnapVaAccounts::class)->saved()['VIRTUAL_ACCOUNT_BRI']['partner_service_id']);
        $this->assertArrayNotHasKey('VIRTUAL_ACCOUNT_BANK_CIMB', app(DokuSnapVaAccounts::class)->saved());
        $this->assertSame($bri->label, $bri->fresh()->label);
        $this->assertSame($cimb->label, $cimb->fresh()->label);

        // Dikosongkan → bank itu kembali lewat DOKU Checkout.
        $page->call('saveSettings', $bri, $this->editData($bri, ['doku_snap_partner_service_id' => '', 'doku_snap_customer_no' => '']));
        $this->assertSame([], app(DokuSnapVaAccounts::class)->saved());
        $this->assertSame('CHECKOUT', app(DokuSnapService::class)->routeFor(['VIRTUAL_ACCOUNT_BRI']));

        // Gateway Midtrans di kedua tempat → bagian & kolom DOKU disembunyikan; data SNAP tetap tersimpan.
        $page->call('saveSettings', $bri, $this->editData($bri, ['doku_snap_partner_service_id' => '12362', 'doku_snap_customer_no' => '1']));
        PaymentGatewaySetting::query()->firstOrFail()->update(['online_gateway' => 'MIDTRANS', 'pos_qris_gateway' => 'MIDTRANS']);
        app(OnlineGatewayRouter::class)->flush();
        $midtransPage = \Livewire\Livewire::test(\App\Filament\Pages\MetodePembayaranOnline::class)->assertTableColumnHidden('doku_route');
        $this->assertFalse($midtransPage->instance()->dokuInUse());
        $midtransPage->mountTableAction('edit', $bri)->assertMountedActionModalDontSee('Partner Service ID (BIN)');
        $this->assertSame('12362', app(DokuSnapVaAccounts::class)->saved()['VIRTUAL_ACCOUNT_BRI']['partner_service_id']);
    }

    public function test_snap_va_data_needs_the_gateway_permission_even_for_method_editors(): void
    {
        $editor = User::factory()->create();
        $editor->givePermissionTo('manage_online_payment_methods');
        $this->actingAs($editor);
        $bri = \App\Models\Pos\OnlinePaymentMethod::where('code', 'BRI_VA')->firstOrFail();
        $page = new \App\Filament\Pages\MetodePembayaranOnline;

        // Nama / nominal tetap bisa diubah (isian SNAP terkunci & tidak dikirim).
        $page->saveSettings($bri, ['label' => 'BRIVA', 'badge' => 'BRI']);
        $this->assertSame('BRIVA', $bri->fresh()->label);

        // Isian SNAP dikirim paksa tanpa izin gateway → 403, metode tidak berubah.
        try {
            $page->saveSettings($bri, ['label' => 'BRI Paksa', 'badge' => 'BRI', 'doku_snap_partner_service_id' => '99999']);
            $this->fail('tanpa izin gateway harus ditolak');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertSame('BRIVA', $bri->fresh()->label);
        $this->assertSame([], app(DokuSnapVaAccounts::class)->saved());
    }

    public function test_snap_is_not_used_without_complete_keys(): void
    {
        config(['services.doku.snap.doku_public_key_path' => storage_path('framework/testing/missing.pem')]);
        $this->assertNull(app(DokuSnapService::class)->productFor(['QRIS']));

        config(['services.doku.snap.doku_public_key_path' => null, 'services.doku.snap.enabled' => true]);
        $this->assertFalse(app(DokuSnapClient::class)->isConfigured());
    }
}
