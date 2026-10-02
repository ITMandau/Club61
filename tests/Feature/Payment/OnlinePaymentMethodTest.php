<?php

namespace Tests\Feature\Payment;

use App\Filament\Pages\MetodePembayaranOnline;
use App\Models\Audit\ActivityLog;
use App\Models\Membership\MembershipPlan;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\ClubFinanceSetting;
use App\Models\Pos\OnlinePaymentMethod;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\Padel\PadelBookingService;
use App\Services\Payment\OnlinePaymentCatalog;
use App\Services\Payment\OnlinePaymentMethodService;
use App\Services\Permission\Club61PermissionMatrix;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Metode pembayaran online dinamis: satu sumber untuk halaman checkout, validasi server, pemetaan Midtrans,
 * label riwayat, dan endpoint mobile. Dulu daftar ditulis manual di 7 tempat dan isinya berbeda-beda.
 */
class OnlinePaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected PadelCourt $court;

    protected string $date;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');
        $this->customer = User::factory()->customer()->create();
        $this->court = PadelCourt::create([
            'name' => 'Court Pay', 'type' => 'INDOOR', 'hourly_rate_regular' => 200000, 'hourly_rate_prime' => 300000, 'is_active' => true,
        ]);
        $this->date = now()->next(Carbon::WEDNESDAY)->format('Y-m-d');
    }

    private function methods(): OnlinePaymentMethodService
    {
        return app(OnlinePaymentMethodService::class);
    }

    private function setActive(string $code, bool $active): void
    {
        OnlinePaymentMethod::where('code', $code)->firstOrFail()->update(['is_active' => $active]);
    }

    /** Hold slot + checkout padel lewat API, seperti halaman checkout customer. */
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

    public function test_catalog_is_seeded_with_official_limits(): void
    {
        $all = $this->methods()->all();

        $this->assertSame(array_keys(OnlinePaymentCatalog::all()), array_keys($all));
        $this->assertEquals(10_000_000, $all['QRIS']['max_amount'], 'Ketentuan BI: QRIS maks Rp10 juta per transaksi');
        $this->assertFalse($all['CREDIT_CARD']['is_active'], 'Kartu kredit nonaktif sampai diaktifkan di Midtrans');
        $this->assertSame('VA Bank Lain (Permata, BSI, dll.)', $all['BSI_VA']['label']);
        $this->assertSame(['permata_va', 'other_va'], $this->methods()->midtransChannels('BSI_VA'));
    }

    public function test_api_lists_only_active_methods_that_fit_the_amount(): void
    {
        $codes = fn ($res) => collect($res->json('data'))->pluck('code')->all();

        $all = $this->getJson('/api/v1/payment-methods')->assertOk();
        $this->assertContains('QRIS', $codes($all));
        $this->assertNotContains('CREDIT_CARD', $codes($all));

        $big = $this->getJson('/api/v1/payment-methods?amount=25000000')->assertOk();
        $this->assertNotContains('QRIS', $codes($big), 'paket Corporate Rp25 juta tidak boleh ditawari QRIS');
        $this->assertContains('BCA_VA', $codes($big));

        $this->setActive('BCA_VA', false);
        $this->assertNotContains('BCA_VA', $codes($this->getJson('/api/v1/payment-methods')));

        $this->getJson('/api/v1/payment-methods?amount=abc')->assertStatus(422);
    }

    public function test_padel_checkout_rejects_disabled_and_unknown_methods(): void
    {
        $this->padelCheckout('NGASAL_PAY')->assertStatus(422);

        $this->setActive('BRI_VA', false);
        $this->padelCheckout('BRI_VA', '11:00')->assertStatus(422)->assertJsonFragment(['message' => 'Metode pembayaran BRI Virtual Account sedang tidak tersedia. Silakan pilih metode lain.']);
        $this->assertSame(0, Order::count(), 'checkout yang ditolak tidak boleh meninggalkan order');
        $this->assertSame('LOCKED', PadelBooking::where('start_time', Carbon::parse("{$this->date} 11:00"))->value('status'), 'slot tetap ditahan, customer bisa pilih metode lain');

        $this->padelCheckout('BCA_VA', '12:00')->assertOk();
    }

    public function test_padel_checkout_enforces_the_qris_limit_on_the_server(): void
    {
        $this->court->update(['hourly_rate_regular' => 11_000_000]);

        $this->padelCheckout('QRIS')->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'maksimal Rp 10.000.000'));

        $this->padelCheckout('BCA_VA', '11:00')->assertOk();
    }

    public function test_retry_payment_rejects_a_method_disabled_after_checkout(): void
    {
        $order = Order::create(['order_number' => 'ORD-RETRY', 'user_id' => $this->customer->id, 'order_type' => 'ONLINE_BOOKING', 'subtotal' => 200000, 'grand_total' => 200000, 'payment_status' => 'UNPAID']);
        $start = Carbon::parse("{$this->date} 10:00");
        $booking = PadelBooking::create(['booking_code' => 'BK-RETRY', 'order_id' => $order->id, 'user_id' => $this->customer->id, 'court_id' => $this->court->id, 'booking_date' => $this->date, 'start_time' => $start, 'end_time' => $start->copy()->addHour(), 'court_fee' => 200000, 'total_amount' => 200000, 'status' => 'LOCKED']);
        Payment::create(['order_id' => $order->id, 'payment_gateway' => 'MIDTRANS', 'transaction_id' => 'ORD-RETRY', 'amount' => 200000, 'payment_method' => 'QRIS', 'status' => 'PENDING']);
        $this->setActive('CIMB_VA', false);

        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/padel/bookings/{$booking->id}/retry-payment", ['payment_method' => 'CIMB_VA'])
            ->assertStatus(422);

        $this->postJson("/api/v1/padel/bookings/{$booking->id}/retry-payment", ['payment_method' => 'MANDIRI_VA'])->assertOk();
    }

    private function plan(float $price): MembershipPlan
    {
        return MembershipPlan::create(['code' => 'MBR-'.Str::random(5), 'name' => 'Paket Uji', 'ownership_type' => 'INDIVIDUAL', 'duration_days' => 30, 'price' => $price, 'is_active' => true]);
    }

    public function test_membership_checkout_validates_the_method(): void
    {
        $plan = $this->plan(1_500_000);
        $this->actingAs($this->customer, 'sanctum');

        $this->postJson('/api/v1/membership/checkout', ['plan_id' => $plan->id, 'payment_method' => 'MIDTRANS_SNAP'])->assertStatus(422);

        $this->setActive('BNI_VA', false);
        $this->postJson('/api/v1/membership/checkout', ['plan_id' => $plan->id, 'payment_method' => 'BNI_VA'])->assertStatus(422);

        $corporate = $this->plan(25_000_000);
        $this->postJson('/api/v1/membership/checkout', ['plan_id' => $corporate->id, 'payment_method' => 'QRIS'])->assertStatus(422);

        $this->assertSame(0, Order::count());
    }

    public function test_membership_checkout_works_when_tax_and_fee_are_enabled(): void
    {
        // Dulu: item Midtrans hanya harga paket padahal total termasuk pajak → MidtransService menolak → error 500.
        ClubFinanceSetting::getSettings()->update([
            'is_tax_enabled' => true, 'tax_type' => 'PERCENTAGE', 'tax_rate' => 10, 'tax_channels' => 'ALL',
            'is_admin_fee_enabled' => true, 'admin_fee_type' => 'PERCENTAGE', 'admin_fee_amount' => 3, 'admin_fee_channels' => 'ALL',
        ]);
        Cache::forget(ClubFinanceSetting::CACHE_KEY);
        $plan = $this->plan(1_500_000);

        $res = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/membership/checkout', ['plan_id' => $plan->id, 'payment_method' => 'QRIS'])
            ->assertStatus(201);

        $order = Order::where('order_number', $res->json('data.order.order_number'))->firstOrFail();
        $this->assertEquals(1_500_000 + 150_000 + 45_000, (float) $order->grand_total);
        $this->assertSame('QRIS', Payment::where('order_id', $order->id)->value('payment_method'));
    }

    public function test_history_labels_follow_settings_but_counter_payments_keep_their_own_label(): void
    {
        $service = app(PadelBookingService::class);
        OnlinePaymentMethod::where('code', 'BCA_VA')->first()->update(['label' => 'VA BCA (Klik BCA)']);

        $this->assertSame('VA BCA (Klik BCA)', $service->formatPaymentMethodLabel('BCA_VA'));
        $this->assertSame('Kartu Kredit (EDC)', $service->formatPaymentMethodLabel('CREDIT_CARD', ['edc_details' => ['card_type' => 'CREDIT']]));
        $this->assertStringContainsString('Kartu Kredit', $service->formatPaymentMethodLabel('CREDIT_CARD'));
        $this->assertSame('BRI Virtual Account', $service->formatPaymentMethodLabel('BRI_VA', ['payment_type' => 'bank_transfer', 'va_numbers' => [['bank' => 'bri']]]));

        // Metode yang dinonaktifkan tetap punya label untuk riwayat lama.
        $this->setActive('CIMB_VA', false);
        $this->assertSame('CIMB Niaga Virtual Account', $service->formatPaymentMethodLabel('CIMB_VA'));
    }

    private function admin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    public function test_admin_cannot_disable_the_last_active_method(): void
    {
        OnlinePaymentMethod::where('code', '!=', 'QRIS')->update(['is_active' => false]);
        $this->methods()->flush();
        $this->actingAs($this->admin());

        Livewire::test(MetodePembayaranOnline::class)
            ->callTableAction('toggle', OnlinePaymentMethod::where('code', 'QRIS')->first());

        $this->assertTrue(OnlinePaymentMethod::where('code', 'QRIS')->value('is_active'));
    }

    public function test_admin_edits_are_validated_audited_and_applied_immediately(): void
    {
        $this->actingAs($this->admin());
        $qris = OnlinePaymentMethod::where('code', 'QRIS')->first();
        $bca = OnlinePaymentMethod::where('code', 'BCA_VA')->first();

        // QRIS tidak boleh dilonggarkan melebihi ketentuan BI.
        Livewire::test(MetodePembayaranOnline::class)
            ->callTableAction('edit', $qris, ['label' => 'QRIS', 'description' => '', 'badge' => 'QRIS', 'min_amount' => null, 'max_amount' => 50_000_000]);
        $this->assertEquals(10_000_000, (float) $qris->fresh()->max_amount);

        Livewire::test(MetodePembayaranOnline::class)
            ->callTableAction('edit', $bca, ['label' => 'Transfer BCA', 'description' => 'VA BCA', 'badge' => 'bca', 'min_amount' => 10000, 'max_amount' => 5000]);
        $this->assertSame('BCA Virtual Account', $bca->fresh()->label, 'min > max ditolak');

        Livewire::test(MetodePembayaranOnline::class)
            ->callTableAction('edit', $bca, ['label' => 'Transfer BCA', 'description' => 'VA BCA', 'badge' => 'bca', 'min_amount' => 10000, 'max_amount' => null]);
        $this->assertSame('Transfer BCA', $bca->fresh()->label);
        $this->assertSame('BCA', $bca->fresh()->badge);
        $this->assertSame('Transfer BCA', $this->methods()->label('BCA_VA'), 'cache langsung diperbarui');
        $this->assertSame(1, ActivityLog::where('subject_type', 'OnlinePaymentMethod')->where('event', 'online_payment_method.updated')->count());

        $this->expectException(HttpException::class);
        $this->methods()->assertSelectable('BCA_VA', 5000);
    }

    public function test_admin_can_reorder_methods(): void
    {
        $this->actingAs($this->admin());
        $bca = OnlinePaymentMethod::where('code', 'BCA_VA')->first();

        Livewire::test(MetodePembayaranOnline::class)->callTableAction('moveUp', $bca);

        $this->assertSame('BCA_VA', array_key_first($this->methods()->all()));
        $this->assertSame('BCA_VA', $this->getJson('/api/v1/payment-methods')->json('data.0.code'));
    }

    public function test_staff_without_permission_cannot_change_methods(): void
    {
        $kitchen = User::factory()->kitchen()->create();
        Role::findByName('kitchen', 'web')->givePermissionTo('View:MetodePembayaranOnline');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($kitchen);

        $page = Livewire::test(MetodePembayaranOnline::class)->assertSee('BCA Virtual Account');
        $page->call('toggle', OnlinePaymentMethod::where('code', 'BCA_VA')->first())->assertForbidden();
        $this->assertTrue(OnlinePaymentMethod::where('code', 'BCA_VA')->value('is_active'));
    }

    private function pendingRetryBooking(string $code): PadelBooking
    {
        $order = Order::create(['order_number' => "ORD-{$code}", 'user_id' => $this->customer->id, 'order_type' => 'ONLINE_BOOKING', 'subtotal' => 200000, 'grand_total' => 200000, 'payment_status' => 'UNPAID']);
        $start = Carbon::parse("{$this->date} 10:00");

        return PadelBooking::create(['booking_code' => "BK-{$code}", 'order_id' => $order->id, 'user_id' => $this->customer->id, 'court_id' => $this->court->id, 'booking_date' => $this->date, 'start_time' => $start, 'end_time' => $start->copy()->addHour(), 'court_fee' => 200000, 'total_amount' => 200000, 'status' => 'PENDING_PAYMENT']);
    }

    public function test_changing_method_on_the_invoice_updates_the_pending_bill(): void
    {
        $booking = $this->pendingRetryBooking('SWITCH');
        Payment::create(['order_id' => $booking->order_id, 'payment_gateway' => 'MIDTRANS', 'transaction_id' => 'ORD-SWITCH', 'amount' => 200000, 'payment_method' => 'QRIS', 'status' => 'PENDING']);

        $suffixed = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/padel/bookings/{$booking->id}/retry-payment", ['payment_method' => 'BNI_VA'])
            ->assertOk()->json('suffixed_order_id');

        $bill = Payment::where('transaction_id', 'ORD-SWITCH')->first();
        $this->assertSame('BNI_VA', $bill->payment_method);
        $this->assertSame($suffixed, $bill->payload_log['midtrans_order_id']);
        $this->assertSame(1, Payment::where('order_id', $booking->order_id)->count());
    }

    public function test_retry_after_the_old_bill_was_closed_is_still_tracked_for_reconciliation(): void
    {
        $booking = $this->pendingRetryBooking('CLOSED');
        Payment::create(['order_id' => $booking->order_id, 'payment_gateway' => 'MIDTRANS', 'transaction_id' => 'ORD-CLOSED', 'amount' => 200000, 'payment_method' => 'QRIS', 'status' => 'FAILED']);

        $suffixed = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/padel/bookings/{$booking->id}/retry-payment", ['payment_method' => 'BCA_VA'])
            ->assertOk()->json('suffixed_order_id');

        $bill = Payment::where('order_id', $booking->order_id)->where('status', 'PENDING')->first();
        $this->assertNotNull($bill, 'sesi Snap baru wajib punya tagihan PENDING');
        $this->assertSame($suffixed, $bill->transaction_id);
        $this->assertSame('BCA_VA', $bill->payment_method);
        $this->assertEquals(200000, (float) $bill->amount);
        $this->assertContains($suffixed, app(\App\Services\Payment\MidtransReconciliationService::class)->gatewayOrderIds(Order::find($booking->order_id)));
    }

    public function test_toggle_saved_inside_a_transaction_is_visible_to_the_next_reader(): void
    {
        $this->assertContains('BNI_VA', array_column($this->methods()->available(), 'code'));
        $this->actingAs($this->admin());

        Livewire::test(MetodePembayaranOnline::class)
            ->callTableAction('toggle', OnlinePaymentMethod::where('code', 'BNI_VA')->first());

        // Pembaca berikutnya (request lain = instance service baru) tidak boleh dapat data lama dari cache.
        $this->app->forgetInstance(OnlinePaymentMethodService::class);
        $this->assertNotContains('BNI_VA', array_column($this->methods()->available(), 'code'));
    }

    public function test_card_payments_always_require_3d_secure(): void
    {
        $this->app['env'] = 'staging';
        config(['services.midtrans.server_key' => 'SB-Mid-server-3DS']);
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['token' => 'tok', 'redirect_url' => 'https://snap'], 201)]);
        $params = fn (string $method) => [
            'order_id' => 'ORD-3DS-'.$method, 'gross_amount' => 100000, 'payment_method' => $method,
            'item_details' => [['id' => 'X', 'price' => 100000, 'quantity' => 1, 'name' => 'Item']], 'customer_details' => [],
        ];

        (new \App\Services\Payment\MidtransService())->createSnapTransaction($params('CREDIT_CARD'));
        (new \App\Services\Payment\MidtransService())->createSnapTransaction($params('QRIS'));

        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => $r['transaction_details']['order_id'] === 'ORD-3DS-CREDIT_CARD'
            && $r['enabled_payments'] === ['credit_card'] && ($r['credit_card']['secure'] ?? false) === true);
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => $r['transaction_details']['order_id'] === 'ORD-3DS-QRIS' && ! isset($r['credit_card']));
    }

    public function test_refund_or_chargeback_from_midtrans_is_alerted_without_touching_the_order(): void
    {
        $key = 'SB-Mid-server-CB';
        config(['services.midtrans.server_key' => $key]);
        $order = Order::create(['order_number' => 'ORD-CB', 'user_id' => $this->customer->id, 'order_type' => 'ONLINE_BOOKING', 'subtotal' => 200000, 'grand_total' => 200000, 'payment_status' => 'PAID']);
        Payment::create(['order_id' => $order->id, 'payment_gateway' => 'MIDTRANS', 'transaction_id' => 'ORD-CB', 'amount' => 200000, 'payment_method' => 'CREDIT_CARD', 'status' => 'SUCCESS']);
        \Illuminate\Support\Facades\Log::spy();

        $this->postJson('/api/v1/padel/webhook/midtrans', [
            'order_id' => 'ORD-CB', 'status_code' => '200', 'gross_amount' => '200000.00',
            'signature_key' => hash('sha512', 'ORD-CB'.'200'.'200000.00'.$key),
            'transaction_status' => 'chargeback', 'payment_type' => 'credit_card',
        ])->assertOk();

        \Illuminate\Support\Facades\Log::shouldHaveReceived('critical')->withArgs(fn ($message) => str_contains($message, 'chargeback'))->once();
        $this->assertSame('PAID', $order->fresh()->payment_status);
        $this->assertSame('SUCCESS', Payment::where('transaction_id', 'ORD-CB')->value('status'));
    }

    public function test_methods_cannot_be_deleted_or_recoded(): void
    {
        $this->expectException(\LogicException::class);
        OnlinePaymentMethod::where('code', 'QRIS')->first()->delete();
    }
}
