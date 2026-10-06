<?php

namespace Tests\Feature;

use App\Livewire\Pos\FnbCashierTerminal;
use App\Models\Fnb\FnbCategory;
use App\Models\Fnb\FnbMenu;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\Pos\PosCashierShift;
use App\Models\User;
use App\Services\Permission\Club61PermissionMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Checkout kasir F&B — sengaja mengikuti pola & skema pembayaran yang sama persis dengan
 * WalkInBookingTest.php (walk-in booking lapangan): shift kasir wajib aktif, kebijakan 100%
 * Cashless (CASH ditolak), dan pelunasan lewat PaymentOrchestratorService::markOrderAsPaid().
 */
class FnbPosCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;

    protected FnbCategory $category;

    protected FnbMenu $latte;

    protected FnbMenu $toast;

    protected function setUp(): void
    {
        parent::setUp();

        Club61PermissionMatrix::syncAllPermissions('web');

        $this->cashier = User::factory()->cashier()->create(['is_active' => true]);
        $this->cashier->givePermissionTo([
            'access_pos_terminal',
            'process_fnb_order',
            'open_pos_shift',
            'close_pos_shift',
        ]);

        $this->category = FnbCategory::create(['name' => 'Coffee & Drinks', 'sort_order' => 1]);
        $this->latte = FnbMenu::create([
            'category_id' => $this->category->id,
            'name' => 'Iced Spanish Latte',
            'base_price' => 38000,
            'station' => 'BAR',
            'is_available' => true,
        ]);
        $this->toast = FnbMenu::create([
            'category_id' => $this->category->id,
            'name' => 'Smashed Avocado Toast',
            'base_price' => 55000,
            'station' => 'KITCHEN',
            'is_available' => true,
        ]);
    }

    /**
     * Kasir yang izin process_fnb_order-nya dicabut dari role (misal lewat menu Roles & Hak
     * Akses). $this->cashier tetap punya izin itu karena diberikan langsung di setUp().
     */
    protected function cashierWithoutFnbPermission(): User
    {
        \App\Models\Role::findByName('cashier', 'web')->revokePermissionTo('process_fnb_order');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return User::factory()->cashier()->create();
    }

    protected function openFnbShift(): PosCashierShift
    {
        return PosCashierShift::create([
            'shift_number' => 'SFT-FNB-'.now()->format('Ymd').'-0001',
            'counter' => 'FNB_COUNTER',
            'status' => 'OPEN',
            'opened_by_id' => $this->cashier->id,
            'opened_at' => now(),
            'starting_cash' => 0,
            'expected_cash' => 0,
        ]);
    }

    public function test_cashier_can_add_items_to_cart_with_live_total(): void
    {
        $this->actingAs($this->cashier);

        $component = Livewire::test(FnbCashierTerminal::class)
            ->call('addToCart', $this->latte->id)
            ->call('addToCart', $this->latte->id)
            ->call('addToCart', $this->toast->id);

        $cart = $component->get('cart');
        $this->assertSame(2, $cart[$this->latte->id]['quantity']);
        $this->assertSame(1, $cart[$this->toast->id]['quantity']);
        $component->assertSet('cartSubtotal', 38000 * 2 + 55000);
    }

    public function test_menu_grid_is_paginated_and_resets_to_first_page_on_category_change(): void
    {
        $this->actingAs($this->cashier);

        // setUp sudah bikin 2 menu; tambah sampai lewat 1 halaman
        for ($i = 1; $i <= FnbCashierTerminal::MENUS_PER_PAGE; $i++) {
            FnbMenu::create([
                'category_id' => $this->category->id,
                'name' => sprintf('Menu Tambahan %02d', $i),
                'base_price' => 10000,
                'station' => 'BAR',
                'is_available' => true,
            ]);
        }

        $component = Livewire::test(FnbCashierTerminal::class);
        $this->assertCount(FnbCashierTerminal::MENUS_PER_PAGE, $component->instance()->menus);
        $this->assertTrue($component->instance()->menus->hasMorePages());

        $component->call('nextPage');
        $this->assertSame(2, $component->instance()->menus->currentPage());
        $this->assertCount(2, $component->instance()->menus);

        $component->call('setActiveCategory', $this->category->id);
        $this->assertSame(1, $component->instance()->menus->currentPage());
    }

    public function test_checkout_blocked_without_active_shift(): void
    {
        $this->actingAs($this->cashier);

        Livewire::test(FnbCashierTerminal::class)
            ->call('addToCart', $this->latte->id)
            ->call('proceedToPayment')
            ->assertSet('posStep', 'selection')
            ->assertSet('errorMessage', fn ($message) => str_contains($message, 'Shift kasir F&B belum dibuka'))
            ->assertSet('showOpenShiftModal', true);

        $this->assertSame(0, Order::count());
    }

    public function test_opening_shift_clears_error_and_allows_proceeding_to_payment(): void
    {
        $this->actingAs($this->cashier);

        Livewire::test(FnbCashierTerminal::class)
            ->call('addToCart', $this->latte->id)
            ->call('proceedToPayment')
            ->call('executeOpenShift')
            ->assertSet('errorMessage', null)
            ->call('proceedToPayment')
            ->assertSet('posStep', 'payment');
    }

    public function test_full_checkout_flow_creates_order_and_marks_paid_via_qris(): void
    {
        $this->openFnbShift();
        $this->actingAs($this->cashier);

        Livewire::test(FnbCashierTerminal::class)
            ->call('addToCart', $this->latte->id)
            ->call('addToCart', $this->toast->id)
            ->set('tableNumber', 'Meja 05')
            ->set('customerName', 'Budi Santoso')
            ->call('proceedToPayment')
            ->assertSet('posStep', 'payment')
            ->call('setPaymentMethod', 'QRIS')
            ->set('qrisMode', 'MANUAL')->set('qrisRrn', '123456789012')
            ->call('submitFnbCheckout')
            ->assertSet('posStep', 'selection')
            ->assertSet('showReceiptModal', true)
            ->assertSet('errorMessage', null);

        $order = Order::where('order_type', 'DINE_IN')->firstOrFail();
        $this->assertSame('Meja 05', $order->table_number);
        $this->assertSame('Budi Santoso', $order->customer_name);
        $this->assertSame('PAID', $order->payment_status);
        $this->assertSame($this->cashier->id, $order->cashier_id);
        $this->assertEqualsWithDelta(38000 + 55000, (float) $order->subtotal, 0.01);

        $itemTypes = $order->items->pluck('item_type')->unique()->all();
        $this->assertSame(['FNB'], $itemTypes);

        $payment = Payment::where('order_id', $order->id)->firstOrFail();
        $this->assertSame('SUCCESS', $payment->status);
        $this->assertSame('CASHIER_POS', $payment->payment_gateway);
        $this->assertSame('QRIS', $payment->payment_method);
        $this->assertNotNull($payment->pos_shift_id);
        $this->assertSame('FNB_COUNTER', PosCashierShift::find($payment->pos_shift_id)->counter);
    }

    public function test_cash_payment_is_rejected(): void
    {
        $this->openFnbShift();
        $this->actingAs($this->cashier);

        Livewire::test(FnbCashierTerminal::class)
            ->call('addToCart', $this->latte->id)
            ->call('proceedToPayment')
            ->call('setPaymentMethod', 'CASH')
            ->call('submitFnbCheckout')
            ->assertSet('posStep', 'payment')
            ->assertSet('errorMessage', fn ($message) => str_contains($message, '100% Cashless'));

        $this->assertSame(0, Order::count());
    }

    public function test_unrecognized_payment_method_is_rejected_not_silently_marked_paid(): void
    {
        // Simulasi request hasil rekayasa (bukan lewat UI <select>/tab pembayaran) yang mengisi
        // paymentMethod dengan nilai di luar daftar QRIS/EDC yang dikenali — harus tetap ditolak,
        // bukan lolos ke markOrderAsPaid() tanpa satu pun bukti bayar tersimpan.
        $this->openFnbShift();
        $this->actingAs($this->cashier);

        Livewire::test(FnbCashierTerminal::class)
            ->call('addToCart', $this->latte->id)
            ->call('proceedToPayment')
            ->set('paymentMethod', 'BITCOIN')
            ->call('submitFnbCheckout')
            ->assertSet('posStep', 'payment')
            ->assertSet('errorMessage', fn ($message) => str_contains($message, 'tidak dikenali'));

        $this->assertSame(0, Order::count());
    }

    public function test_edc_payment_requires_complete_slip_fields(): void
    {
        $this->openFnbShift();
        $this->actingAs($this->cashier);

        Livewire::test(FnbCashierTerminal::class)
            ->call('addToCart', $this->latte->id)
            ->call('proceedToPayment')
            ->call('setPaymentMethod', 'DEBIT_CARD')
            ->set('edcLast4', '12') // kurang dari 4 digit
            ->call('submitFnbCheckout')
            ->assertSet('errorMessage', fn ($message) => str_contains($message, '4 digit'));

        $this->assertSame(0, Order::count());
    }

    public function test_menu_disabled_after_added_to_cart_is_skipped_at_checkout(): void
    {
        // Race sederhana: item sudah di keranjang kasir, tapi staf lain menonaktifkannya
        // (misal kehabisan stok) tepat sebelum tombol bayar diklik — checkout tidak boleh
        // menjual item yang sudah tidak tersedia.
        $this->openFnbShift();
        $this->actingAs($this->cashier);

        $component = Livewire::test(FnbCashierTerminal::class)
            ->call('addToCart', $this->latte->id)
            ->call('addToCart', $this->toast->id)
            ->call('proceedToPayment')
            ->call('setPaymentMethod', 'QRIS')
            ->set('qrisMode', 'MANUAL')->set('qrisRrn', '123456789012');

        $this->latte->update(['is_available' => false]);

        $component->call('submitFnbCheckout')->assertSet('showReceiptModal', true);

        $order = Order::firstOrFail();
        $this->assertCount(1, $order->items);
        $this->assertSame('Smashed Avocado Toast', $order->items->first()->item_name);
    }

    public function test_checkout_rejected_when_all_cart_items_became_unavailable(): void
    {
        $this->openFnbShift();
        $this->actingAs($this->cashier);

        $component = Livewire::test(FnbCashierTerminal::class)
            ->call('addToCart', $this->latte->id)
            ->call('proceedToPayment')
            ->call('setPaymentMethod', 'QRIS')
            ->set('qrisMode', 'MANUAL')->set('qrisRrn', '123456789012');

        $this->latte->update(['is_available' => false]);

        $component->call('submitFnbCheckout')
            ->assertSet('posStep', 'payment')
            ->assertSet('errorMessage', fn ($message) => str_contains($message, 'tidak tersedia'));

        $this->assertSame(0, Order::count());
    }

    public function test_staff_without_process_fnb_order_permission_cannot_checkout(): void
    {
        $this->openFnbShift();

        $unauthorized = $this->cashierWithoutFnbPermission();
        $this->actingAs($unauthorized);

        Livewire::test(FnbCashierTerminal::class)
            ->call('addToCart', $this->latte->id)
            ->call('proceedToPayment')
            ->call('setPaymentMethod', 'QRIS')
            ->set('qrisMode', 'MANUAL')->set('qrisRrn', '123456789012')
            ->call('submitFnbCheckout');

        // Livewire menyerap HttpException 403 dari abort_unless() di level request, jadi yang
        // diverifikasi adalah AKIBATnya: tidak ada Order yang benar-benar tersimpan.
        $this->assertSame(0, Order::count());
    }

    public function test_staff_without_process_fnb_order_permission_cannot_view_history_or_reopen_receipts(): void
    {
        // Riwayat transaksi & buka-ulang struk HARUS pakai izin yang sama dengan yang boleh
        // memproses transaksinya — kalau tidak, staf kasir yang izin process_fnb_order-nya sudah
        // dicabut tetap bisa mengintip seluruh riwayat penjualan F&B (nama pelanggan, meja, detail
        // slip EDC/QRIS) meski tidak boleh transaksi sama sekali.
        $this->openFnbShift();
        $this->actingAs($this->cashier);

        Livewire::test(FnbCashierTerminal::class)
            ->set('customerName', 'Rahasia Pelanggan')
            ->call('addToCart', $this->latte->id)
            ->call('proceedToPayment')
            ->call('setPaymentMethod', 'QRIS')
            ->set('qrisMode', 'MANUAL')->set('qrisRrn', '123456789012')
            ->call('submitFnbCheckout');

        $order = Order::firstOrFail();

        $unauthorized = $this->cashierWithoutFnbPermission();
        $this->actingAs($unauthorized);

        $component = Livewire::test(FnbCashierTerminal::class)
            ->set('posStep', 'history');

        $this->assertCount(0, $component->get('orderHistory'));
        $component->assertDontSee('Rahasia Pelanggan')
            ->assertDontSee($order->order_number);

        // Livewire menyerap HttpException 403 di level request (pola sama seperti submitFnbCheckout
        // di atas) — verifikasi AKIBATnya: struk orang lain tidak pernah benar-benar terbuka.
        $component->call('viewOrderReceipt', $order->id)
            ->assertSet('showReceiptModal', false);
        $this->assertNull($component->get('completedOrderData'));
    }

    public function test_cashier_can_open_and_close_fnb_shift(): void
    {
        $this->actingAs($this->cashier);

        Livewire::test(FnbCashierTerminal::class)
            ->call('openShiftModal')
            ->call('executeOpenShift')
            ->assertSet('showOpenShiftModal', false);

        $this->assertDatabaseHas('pos_cashier_shifts', [
            'counter' => 'FNB_COUNTER',
            'status' => 'OPEN',
        ]);

        Livewire::test(FnbCashierTerminal::class)
            ->call('prepareCloseShift')
            ->call('executeCloseShift')
            ->assertSet('showShiftReportModal', true);

        $this->assertDatabaseHas('pos_cashier_shifts', [
            'counter' => 'FNB_COUNTER',
            'status' => 'CLOSED',
        ]);
    }

    public function test_transaction_history_tab_lists_orders_with_status_and_can_reopen_receipt(): void
    {
        $this->openFnbShift();
        $this->actingAs($this->cashier);

        Livewire::test(FnbCashierTerminal::class)
            ->set('customerName', 'Siti Aminah')
            ->set('tableNumber', 'Meja 10')
            ->call('addToCart', $this->latte->id)
            ->call('proceedToPayment')
            ->call('setPaymentMethod', 'QRIS')
            ->set('qrisMode', 'MANUAL')->set('qrisRrn', '123456789012')
            ->call('submitFnbCheckout');

        $order = Order::where('customer_name', 'Siti Aminah')->firstOrFail();
        $this->assertNotNull($order->queue_number);

        $component = Livewire::test(FnbCashierTerminal::class)
            ->set('posStep', 'history');

        $history = $component->get('orderHistory');
        $this->assertCount(1, $history);
        $this->assertSame($order->id, $history->first()->id);

        $component->assertSee('Riwayat Transaksi F&B')
            ->assertSee('Siti Aminah')
            ->assertSee('Meja 10')
            ->assertSee('PAID')
            ->assertSee($order->order_number);

        $component->call('viewOrderReceipt', $order->id)
            ->assertSet('showReceiptModal', true)
            ->assertSee('Siti Aminah')
            ->assertSee('Meja 10')
            ->assertSee($order->order_number)
            ->assertSee(str_pad((string) $order->queue_number, 3, '0', STR_PAD_LEFT));
    }

    public function test_payment_step_uses_same_payment_form_as_walk_in_booking(): void
    {
        $this->openFnbShift();
        $this->actingAs($this->cashier);

        Livewire::test(FnbCashierTerminal::class)
            ->call('addToCart', $this->latte->id)
            ->call('proceedToPayment')
            ->assertSee('KARTU DEBIT')
            ->assertSee('KARTU KREDIT')
            // Bayar Otomatis = pilihan utama; QRIS manual (RRN) tetap ada sebagai cadangan.
            ->assertSee('Bayar Otomatis')
            ->set('qrisMode', 'MANUAL')
            ->assertSee('Retrieval Reference Number')
            ->call('setPaymentMethod', 'DEBIT_CARD')
            ->assertSee('Pembayaran Kartu Debit (Debit Card)')
            ->assertSee('Mesin EDC Mandiri')
            ->assertSee('Keamanan PCI-DSS');
    }

    public function test_receipt_popup_shows_resolved_payment_method_and_edc_slip_details(): void
    {
        $this->openFnbShift();
        $this->actingAs($this->cashier);

        Livewire::test(FnbCashierTerminal::class)
            ->call('addToCart', $this->latte->id)
            ->call('proceedToPayment')
            ->call('setPaymentMethod', 'DEBIT_CARD')
            ->set('edcTerminal', 'EDC_BCA')
            ->set('edcLast4', '4242')
            ->set('edcApprovalCode', 'APR999')
            ->set('edcTraceNumber', 'TRC999')
            ->call('submitFnbCheckout')
            ->assertSet('showReceiptModal', true)
            ->assertSet('posStep', 'selection')
            ->assertSee('Mesin EDC BCA')
            ->assertSee('4242')
            ->assertSee('APR999')
            ->assertSee('TRC999')
            ->assertSee('Kartu Debit (EDC)');
    }

    public function test_receipt_popup_shows_qris_slip_details(): void
    {
        $this->openFnbShift();
        $this->actingAs($this->cashier);

        Livewire::test(FnbCashierTerminal::class)
            ->call('addToCart', $this->latte->id)
            ->call('proceedToPayment')
            ->call('setPaymentMethod', 'QRIS')
            ->set('qrisProvider', 'GOPAY')
            ->set('qrisMode', 'MANUAL')->set('qrisRrn', '123456789012')
            ->set('qrisSenderName', 'Budi Santoso')
            ->call('submitFnbCheckout')
            ->assertSet('showReceiptModal', true)
            ->assertSee('GoPay / Midtrans QRIS')
            ->assertSee('123456789012')
            ->assertSee('Budi Santoso')
            ->assertSee('QRIS Kasir Frontdesk');
    }

    /** Checkout 1 latte lewat metode tertentu (helper untuk skenario closing). */
    protected function checkoutLatte(string $method, array $fields): void
    {
        $component = Livewire::test(FnbCashierTerminal::class)
            ->call('addToCart', $this->latte->id)
            ->call('proceedToPayment')
            ->call('setPaymentMethod', $method)
            ->set('qrisMode', 'MANUAL'); // QRIS di skenario closing = QRIS manual (RRN)

        foreach ($fields as $name => $value) {
            $component->set($name, $value);
        }

        $component->call('submitFnbCheckout')->assertSet('showReceiptModal', true);
    }

    public function test_closing_breaks_down_sales_per_payment_category_and_saves_matching_reconciliation(): void
    {
        $shift = $this->openFnbShift();
        $this->actingAs($this->cashier);

        $this->checkoutLatte('QRIS', ['qrisProvider' => 'BCA_QRIS', 'qrisRrn' => '123456789012']);
        $this->checkoutLatte('DEBIT_CARD', ['edcTerminal' => 'EDC_BCA', 'edcLast4' => '1234', 'edcApprovalCode' => 'APR001', 'edcTraceNumber' => 'TRC001']);
        $this->checkoutLatte('CREDIT_CARD', ['edcTerminal' => 'EDC_MANDIRI', 'edcLast4' => '5678', 'edcApprovalCode' => 'APR002', 'edcTraceNumber' => 'TRC002']);

        $component = Livewire::test(FnbCashierTerminal::class)->call('prepareCloseShift');
        $breakdown = collect($component->get('closingBreakdown'))->keyBy('key');

        $this->assertEqualsCanonicalizing(['QRIS_BCA_QRIS', 'EDC_BCA_DEBIT', 'EDC_MANDIRI_CREDIT'], $breakdown->keys()->all());
        $this->assertSame(1, $breakdown['EDC_BCA_DEBIT']['count']);

        foreach ($breakdown as $key => $row) {
            $component->set("settlementInputs.{$key}", number_format($row['system_amount'], 0, ',', '.'));
        }

        $component->call('executeCloseShift')->assertSet('showShiftReportModal', true);

        $shift->refresh();
        $this->assertSame('CLOSED', $shift->status);
        $this->assertEquals(0, (float) $shift->settlement_difference);
        $this->assertCount(3, $shift->settlement_reconciliation);
    }

    public function test_closing_with_settlement_difference_requires_notes(): void
    {
        $shift = $this->openFnbShift();
        $this->actingAs($this->cashier);

        $this->checkoutLatte('QRIS', ['qrisRrn' => '123456789012']);

        $component = Livewire::test(FnbCashierTerminal::class)->call('prepareCloseShift');
        $key = $component->get('closingBreakdown')[0]['key'];
        $systemAmount = $component->get('closingBreakdown')[0]['system_amount'];

        $component->set("settlementInputs.{$key}", (string) ($systemAmount - 5000))
            ->call('executeCloseShift')
            ->assertSet('errorMessage', fn ($m) => str_contains($m, 'Wajib isi catatan'));
        $this->assertSame('OPEN', $shift->fresh()->status);

        $component->set('closingNotes', 'Settlement QRIS kurang 5rb, menunggu konfirmasi bank.')
            ->call('executeCloseShift')
            ->assertSet('showShiftReportModal', true);

        $shift->refresh();
        $this->assertSame('CLOSED', $shift->status);
        $this->assertEquals(-5000, (float) $shift->settlement_difference);
    }

    public function test_closing_rejects_empty_settlement_input(): void
    {
        $shift = $this->openFnbShift();
        $this->actingAs($this->cashier);

        $this->checkoutLatte('QRIS', ['qrisRrn' => '123456789012']);

        Livewire::test(FnbCashierTerminal::class)
            ->call('prepareCloseShift')
            ->call('executeCloseShift')
            ->assertSet('errorMessage', fn ($m) => str_contains($m, 'Isi nominal settlement'));

        $this->assertSame('OPEN', $shift->fresh()->status);
    }

    public function test_new_transaction_during_closing_forces_recheck(): void
    {
        $shift = $this->openFnbShift();
        $this->actingAs($this->cashier);

        $this->checkoutLatte('QRIS', ['qrisRrn' => '123456789012']);

        $component = Livewire::test(FnbCashierTerminal::class)->call('prepareCloseShift');
        $row = $component->get('closingBreakdown')[0];
        $component->set("settlementInputs.{$row['key']}", (string) $row['system_amount']);

        // Kasir lain menyelesaikan transaksi saat modal closing masih terbuka
        $this->checkoutLatte('QRIS', ['qrisRrn' => '999999999999']);

        $component->call('executeCloseShift')
            ->assertSet('errorMessage', fn ($m) => str_contains($m, 'transaksi baru'));

        $this->assertSame('OPEN', $shift->fresh()->status);
        $this->assertSame(2, $component->get('closingBreakdown')[0]['count']);
    }

    public function test_tax_amount_matches_fnb_module_override_from_club_finance_setting(): void
    {
        $this->openFnbShift();
        $this->actingAs($this->cashier);

        Livewire::test(FnbCashierTerminal::class)
            ->call('addToCart', $this->latte->id)
            ->call('proceedToPayment')
            ->call('setPaymentMethod', 'QRIS')
            ->set('qrisMode', 'MANUAL')->set('qrisRrn', '123456789012')
            ->call('submitFnbCheckout');

        $order = Order::firstOrFail();
        $settings = \App\Models\Pos\ClubFinanceSetting::getSettings();

        if ($settings->isTaxApplicable('POS_WALKIN', 'FNB')) {
            $this->assertGreaterThan(0, (float) $order->tax_amount);
        } else {
            $this->assertEquals(0, (float) $order->tax_amount);
        }

        $this->assertEqualsWithDelta(
            (float) $order->subtotal + (float) $order->tax_amount + (float) $order->service_charge,
            (float) $order->grand_total,
            0.01
        );
    }
}
