<?php

namespace Tests\Feature\Payment;

use App\Filament\Pages\MetodePembayaranOnline;
use App\Models\Audit\ActivityLog;
use App\Models\Pos\OnlinePaymentMethod;
use App\Models\Pos\Order;
use App\Models\Pos\PaymentGatewaySetting;
use App\Models\Role;
use App\Models\User;
use App\Services\Payment\MidtransReconciliationService;
use App\Services\Payment\OnlineGatewayRouter;
use App\Services\Payment\OnlinePaymentMethodService;
use App\Services\Permission\Club61PermissionMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Modul 27 Tahap 1: pengarah gateway (Midtrans saja dulu, perilaku tidak berubah), pilihan gateway per kanal di admin,
 * dan metode pembayaran yang bisa ditambah admin dengan kode kanal resmi Midtrans / DOKU.
 */
class OnlineGatewayRouterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');
    }

    private function router(): OnlineGatewayRouter
    {
        return app(OnlineGatewayRouter::class);
    }

    private function methods(): OnlinePaymentMethodService
    {
        return app(OnlinePaymentMethodService::class);
    }

    private function admin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    private function vaData(array $overrides = []): array
    {
        return array_merge([
            'label' => 'Danamon', 'description' => 'Transfer ke VA Danamon.', 'badge' => 'dnm', 'kind' => 'VA',
            'midtrans_channels' => ['danamon_va'], 'doku_channels' => ['VIRTUAL_ACCOUNT_BANK_DANAMON'],
            'min_amount' => null, 'max_amount' => null, 'show_at_pos' => false,
        ], $overrides);
    }

    // ── Pengarah ─────────────────────────────────────────────────────────────────────────────────────────────────

    public function test_doku_is_used_for_new_bills_only_when_enabled_and_configured(): void
    {
        PaymentGatewaySetting::query()->update(['online_gateway' => 'DOKU', 'pos_qris_gateway' => 'DOKU']);
        $this->router()->flush();

        // Tersimpan DOKU tapi DOKU_ENABLED=false → tagihan baru tetap Midtrans.
        config(['services.doku.enabled' => false, 'services.doku.client_id' => 'BRN-test', 'services.doku.secret_key' => 'SK-test']);
        $this->assertSame('MIDTRANS', $this->router()->onlineGateway());
        $this->assertStringContainsString('DOKU_ENABLED=false', (string) $this->router()->unavailableReason('DOKU'));

        // Aktif tapi kunci kosong → tetap Midtrans.
        config(['services.doku.enabled' => true, 'services.doku.secret_key' => '']);
        $this->assertSame('MIDTRANS', $this->router()->onlineGateway());
        $this->assertStringContainsString('Kunci DOKU', (string) $this->router()->unavailableReason('DOKU'));

        // Aktif + kunci terisi → DOKU. QR kasir lewat DOKU belum ada (Tahap 3) → tetap Midtrans.
        config(['services.doku.secret_key' => 'SK-test']);
        $this->assertSame('DOKU', $this->router()->onlineGateway());
        $this->assertSame('MIDTRANS', $this->router()->posQrisGateway());
        $this->assertTrue($this->router()->isUsable('MIDTRANS'));
        $this->assertNotNull($this->router()->unavailableReason('PAYPAL'));
    }

    public function test_missing_settings_table_falls_back_to_midtrans(): void
    {
        \Illuminate\Support\Facades\Schema::drop('payment_gateway_settings');
        $this->router()->flush();

        $this->assertSame('MIDTRANS', $this->router()->onlineGateway());
    }

    public function test_results_from_several_gateways_are_combined_safely(): void
    {
        $this->assertSame('PAID', OnlineGatewayRouter::combine(['ERROR', 'PAID', 'NOT_PAID']));
        $this->assertSame('PENDING', OnlineGatewayRouter::combine(['NOT_PAID', 'PENDING', 'ERROR']));
        $this->assertSame('ERROR', OnlineGatewayRouter::combine(['NOT_PAID', 'ERROR']), 'belum pasti ≠ belum bayar');
        $this->assertSame('NOT_PAID', OnlineGatewayRouter::combine(['NOT_PAID']));
        $this->assertSame('NOT_PAID', OnlineGatewayRouter::combine([]));
    }

    public function test_router_forwards_to_the_midtrans_services_resolved_at_call_time(): void
    {
        $order = Order::create(['order_number' => 'ORD-ROUTE-1', 'order_type' => 'ONLINE_BOOKING', 'subtotal' => 100000, 'grand_total' => 100000, 'payment_status' => 'UNPAID']);

        $this->mock(MidtransReconciliationService::class, function ($mock) use ($order) {
            $mock->shouldReceive('reconcileOrder')->once()->withArgs(fn ($o, $cache) => $o->is($order) && $cache === 10)->andReturn('PENDING');
            $mock->shouldReceive('hasOnlinePaymentAttempt')->once()->andReturn(true);
            $mock->shouldReceive('settle')->once()->withArgs(fn ($o, $id, $status) => $o->is($order) && $id === 'ORD-ROUTE-1_1' && $status === ['transaction_status' => 'settlement']);
        });

        $this->assertSame('PENDING', $this->router()->reconcileOrder($order, cacheSeconds: 10));
        $this->assertTrue($this->router()->hasOnlinePaymentAttempt($order));
        $this->router()->settlePreparedSession($order, ['action' => 'PAID', 'gateway' => 'MIDTRANS', 'session_id' => 'ORD-ROUTE-1_1', 'status' => ['transaction_status' => 'settlement']]);
        $this->assertSame('Midtrans', $this->router()->gatewayLabel($order));
    }

    public function test_prepare_session_uses_the_snap_guard_and_tags_the_gateway(): void
    {
        $prepared = $this->router()->prepareSession(null, 'QRIS', 100000);

        $this->assertSame(['action' => 'CREATE', 'gateway' => 'MIDTRANS'], $prepared);
    }

    public function test_cancel_sessions_is_best_effort(): void
    {
        $this->mock(\App\Services\Payment\MidtransService::class, function ($mock) {
            $mock->shouldReceive('cancelTransaction')->with('ORD-A')->once()->andThrow(new \RuntimeException('down'));
            $mock->shouldReceive('cancelTransaction')->with('ORD-B')->once()->andReturn(true);
        });

        $this->router()->cancelSessions(['ORD-A', '', null, 'ORD-B']);
        $this->addToAssertionCount(1);
    }

    // ── Pilihan gateway di admin ─────────────────────────────────────────────────────────────────────────────────

    public function test_admin_can_save_midtrans_but_not_an_unavailable_gateway(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(MetodePembayaranOnline::class)
            ->call('saveGatewaySettings', ['online_gateway' => 'DOKU', 'pos_qris_gateway' => 'MIDTRANS'])
            ->assertNotified('Gateway Tidak Bisa Dipilih');
        $this->assertSame('MIDTRANS', PaymentGatewaySetting::query()->value('online_gateway'));

        // Tersimpan DOKU (mis. dipilih lalu DOKU dimatikan) → admin kembalikan ke Midtrans: perubahan tercatat.
        PaymentGatewaySetting::query()->update(['pos_qris_gateway' => 'DOKU']);
        Livewire::test(MetodePembayaranOnline::class)
            ->call('saveGatewaySettings', ['online_gateway' => 'midtrans', 'pos_qris_gateway' => 'MIDTRANS'])
            ->assertNotified('Gateway Tersimpan');
        $this->assertSame(1, PaymentGatewaySetting::query()->count());
        $this->assertTrue(ActivityLog::where('subject_type', 'PaymentGatewaySetting')->exists(), 'perubahan gateway tercatat di Log Aktivitas');
    }

    public function test_gateway_settings_need_their_own_permission(): void
    {
        $kitchen = User::factory()->kitchen()->create();
        Role::findByName('kitchen', 'web')->givePermissionTo(['View:MetodePembayaranOnline', 'manage_online_payment_methods']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($kitchen);

        Livewire::test(MetodePembayaranOnline::class)
            ->assertActionHidden('gatewaySettings')
            ->assertActionVisible('addMethod')
            ->call('saveGatewaySettings', ['online_gateway' => 'MIDTRANS', 'pos_qris_gateway' => 'MIDTRANS'])
            ->assertForbidden();
    }

    // ── Metode tambahan admin ────────────────────────────────────────────────────────────────────────────────────

    public function test_built_in_methods_are_mapped_to_both_gateways(): void
    {
        $bca = OnlinePaymentMethod::where('code', 'BCA_VA')->first();
        $this->assertSame('VA', $bca->kind);
        $this->assertSame(['bca_va'], $bca->midtrans_channels);
        $this->assertSame(['VIRTUAL_ACCOUNT_BCA'], $bca->doku_channels);
        $this->assertFalse($bca->is_custom);

        $this->assertSame(['VIRTUAL_ACCOUNT_BANK_PERMATA', 'VIRTUAL_ACCOUNT_BANK_SYARIAH_MANDIRI'], $this->methods()->channels('BSI_VA', 'DOKU'));
        $this->assertSame([], $this->methods()->channels('CREDIT_CARD', 'DOKU'), 'kartu tidak dipakai lewat DOKU');

        $this->expectException(\LogicException::class);
        $bca->update(['midtrans_channels' => ['danamon_va']]);
    }

    public function test_admin_adds_a_va_method_that_customers_can_pay_after_activation(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(MetodePembayaranOnline::class)->call('createMethod', $this->vaData())->assertNotified('Metode Ditambahkan');

        $method = OnlinePaymentMethod::where('code', 'DANAMON_VA')->firstOrFail();
        $this->assertTrue($method->is_custom);
        $this->assertFalse($method->is_active, 'dibuat nonaktif sampai kanalnya dicek di dashboard gateway');
        $this->assertSame('DNM', $method->badge);
        $this->assertNotContains('DANAMON_VA', array_column($this->methods()->available(), 'code'));

        Livewire::test(MetodePembayaranOnline::class)->callTableAction('toggle', $method);

        $this->assertContains('DANAMON_VA', array_column($this->methods()->forFrontend(), 'code'));
        $this->assertSame('DANAMON_VA', $this->methods()->assertSelectable('danamon_va', 150000));
        $this->assertSame('Danamon', $this->methods()->label('DANAMON_VA'));
        $this->assertSame(['danamon_va'], $this->methods()->midtransChannels('DANAMON_VA'));

        // Snap menerima kanal pilihan admin.
        $this->app['env'] = 'staging';
        config(['services.midtrans.server_key' => 'SB-Mid-server-CUSTOM']);
        Http::fake(['*' => Http::response(['token' => 'tok', 'redirect_url' => 'https://snap'], 201)]);
        (new \App\Services\Payment\MidtransService())->createSnapTransaction([
            'order_id' => 'ORD-DNM-1', 'gross_amount' => 150000, 'payment_method' => 'DANAMON_VA',
            'item_details' => [['id' => 'X', 'price' => 150000, 'quantity' => 1, 'name' => 'Item']], 'customer_details' => [],
        ]);
        Http::assertSent(fn ($r) => $r['transaction_details']['order_id'] === 'ORD-DNM-1' && $r['enabled_payments'] === ['danamon_va']);
    }

    public function test_admin_forms_open_and_submit_through_the_modals(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(MetodePembayaranOnline::class)
            ->assertSee('Gateway untuk tagihan baru')
            ->assertSee('DOKU belum bisa dipilih')
            ->mountAction('gatewaySettings')
            ->assertHasNoActionErrors();

        Livewire::test(MetodePembayaranOnline::class)
            ->callAction('addMethod', data: $this->vaData(['label' => 'SeaBank', 'badge' => 'SEA', 'midtrans_channels' => ['seabank_va'], 'doku_channels' => []]))
            ->assertHasNoActionErrors();
        $custom = OnlinePaymentMethod::where('code', 'SEABANK_VA')->firstOrFail();
        $this->assertSame(['seabank_va'], $custom->midtrans_channels);

        Livewire::test(MetodePembayaranOnline::class)
            ->assertSee('Ditambah admin')
            ->callTableAction('edit', $custom, ['label' => 'SeaBank VA', 'badge' => 'SEA', 'midtrans_channels' => ['seabank_va', 'saqu_va'], 'doku_channels' => [], 'min_amount' => null, 'max_amount' => null])
            ->assertHasNoTableActionErrors();
        $this->assertSame(['seabank_va', 'saqu_va'], $custom->fresh()->midtrans_channels);
        $this->assertSame('SeaBank VA', $custom->fresh()->label);
    }

    public function test_add_method_only_accepts_official_unused_qris_or_va_channels(): void
    {
        $this->actingAs($this->admin());
        $page = Livewire::test(MetodePembayaranOnline::class);

        $page->call('createMethod', $this->vaData(['midtrans_channels' => ['ovo'], 'doku_channels' => []]))->assertNotified('Kanal Tidak Dikenal');
        $page->call('createMethod', $this->vaData(['doku_channels' => ['VIRTUAL_ACCOUNT_FAKE']]))->assertNotified('Kanal Tidak Dikenal');
        // VA yang tidak bisa dibatalkan DOKU (BTN / BNC / BPD) tidak masuk daftar resmi.
        $page->call('createMethod', $this->vaData(['midtrans_channels' => [], 'doku_channels' => ['VIRTUAL_ACCOUNT_BTN']]))->assertNotified('Kanal Tidak Dikenal');
        $page->call('createMethod', $this->vaData(['kind' => 'QRIS', 'max_amount' => 5_000_000]))->assertNotified('Kanal Tidak Dikenal');
        $page->call('createMethod', $this->vaData(['kind' => 'CARD', 'midtrans_channels' => ['credit_card'], 'doku_channels' => []]))->assertNotified('Jenis Tidak Didukung');
        $page->call('createMethod', $this->vaData(['midtrans_channels' => ['bca_va'], 'doku_channels' => []]))->assertNotified('Kanal Sudah Dipakai');
        $page->call('createMethod', $this->vaData(['midtrans_channels' => [], 'doku_channels' => []]))->assertNotified('Kanal Belum Dipilih');
        $page->call('createMethod', $this->vaData(['kind' => 'QRIS', 'midtrans_channels' => [], 'doku_channels' => [], 'max_amount' => 20_000_000]))->assertNotified('Batas QRIS Tidak Valid');

        $this->assertSame(0, OnlinePaymentMethod::where('is_custom', true)->count());
    }

    public function test_method_without_a_channel_for_the_active_gateway_is_hidden(): void
    {
        $this->actingAs($this->admin());
        Livewire::test(MetodePembayaranOnline::class)
            ->call('createMethod', $this->vaData(['label' => 'BSS', 'badge' => 'BSS', 'midtrans_channels' => [], 'doku_channels' => ['VIRTUAL_ACCOUNT_BSS']]));
        OnlinePaymentMethod::where('code', 'BSS_VA')->firstOrFail()->update(['is_active' => true]);
        $this->methods()->flush();

        $this->assertNotContains('BSS_VA', array_column($this->methods()->available(), 'code'), 'tidak bisa dibayar lewat Midtrans');
        $this->assertContains('BSS_VA', array_column($this->methods()->available(null, 'DOKU'), 'code'));

        $this->expectException(HttpException::class);
        $this->methods()->assertSelectable('BSS_VA', 100000);
    }

    public function test_custom_method_channels_can_be_edited_but_built_in_channels_are_ignored(): void
    {
        $this->actingAs($this->admin());
        $page = Livewire::test(MetodePembayaranOnline::class);
        $page->call('createMethod', $this->vaData());
        $custom = OnlinePaymentMethod::where('code', 'DANAMON_VA')->firstOrFail();

        $page->call('saveSettings', $custom, $this->vaData(['label' => 'VA Danamon', 'midtrans_channels' => [], 'doku_channels' => ['VIRTUAL_ACCOUNT_BANK_DANAMON']]));
        $this->assertSame('VA Danamon', $custom->fresh()->label);
        $this->assertSame([], $custom->fresh()->midtrans_channels);
        $this->assertSame('VA', $custom->fresh()->kind, 'jenis tidak berubah lewat edit');

        $bca = OnlinePaymentMethod::where('code', 'BCA_VA')->firstOrFail();
        $page->call('saveSettings', $bca, ['label' => 'BCA VA', 'badge' => 'BCA', 'midtrans_channels' => ['seabank_va'], 'min_amount' => null, 'max_amount' => null]);
        $this->assertSame('BCA VA', $bca->fresh()->label);
        $this->assertSame(['bca_va'], $this->methods()->midtransChannels('BCA_VA'), 'kanal bawaan tetap dari katalog');
    }

    public function test_generated_codes_never_collide_with_built_in_or_cashier_codes(): void
    {
        $this->actingAs($this->admin());
        $page = Livewire::test(MetodePembayaranOnline::class);

        $page->call('createMethod', $this->vaData(['label' => 'Debit Card', 'badge' => 'DC', 'midtrans_channels' => ['seabank_va'], 'doku_channels' => []]));
        $page->call('createMethod', $this->vaData(['label' => 'QRIS', 'badge' => 'QR', 'kind' => 'QRIS', 'midtrans_channels' => [], 'doku_channels' => [], 'max_amount' => 1_000_000]));

        $this->assertTrue(OnlinePaymentMethod::where('code', 'DEBIT_CARD_VA')->exists());
        $this->assertSame(0, OnlinePaymentMethod::where('label', 'QRIS')->where('is_custom', true)->count(), 'QRIS tanpa kanal ditolak');

        $page->call('createMethod', $this->vaData(['label' => 'Seabank Lagi', 'midtrans_channels' => ['saqu_va'], 'doku_channels' => []]));
        $page->call('createMethod', $this->vaData(['label' => 'Saqu', 'midtrans_channels' => [], 'doku_channels' => ['VIRTUAL_ACCOUNT_Sinarmas']]));
        $page->call('createMethod', $this->vaData(['label' => 'Saqu', 'midtrans_channels' => [], 'doku_channels' => ['VIRTUAL_ACCOUNT_BSS']]));
        $this->assertTrue(OnlinePaymentMethod::where('code', 'SAQU_VA')->exists());
        $this->assertTrue(OnlinePaymentMethod::where('code', 'SAQU_VA_2')->exists());
    }

    public function test_admin_cannot_disable_the_last_method_payable_through_the_active_gateway(): void
    {
        $this->actingAs($this->admin());
        Livewire::test(MetodePembayaranOnline::class)
            ->call('createMethod', $this->vaData(['label' => 'BSS', 'badge' => 'BSS', 'midtrans_channels' => [], 'doku_channels' => ['VIRTUAL_ACCOUNT_BSS']]));
        OnlinePaymentMethod::where('code', 'BSS_VA')->update(['is_active' => true]);
        OnlinePaymentMethod::whereNotIn('code', ['QRIS', 'BSS_VA'])->update(['is_active' => false]);
        $this->methods()->flush();

        Livewire::test(MetodePembayaranOnline::class)
            ->callTableAction('toggle', OnlinePaymentMethod::where('code', 'QRIS')->first());

        $this->assertTrue(OnlinePaymentMethod::where('code', 'QRIS')->value('is_active'), 'BSS (DOKU saja) tidak bisa dibayar lewat Midtrans');
    }
}
