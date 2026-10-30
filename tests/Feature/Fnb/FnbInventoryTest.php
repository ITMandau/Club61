<?php

namespace Tests\Feature\Fnb;

use App\Filament\Pages\BukuKasFnb;
use App\Filament\Pages\KelolaMenuFnb;
use App\Filament\Pages\LaporanFnb;
use App\Filament\Pages\StokFnb;
use App\Livewire\Pos\FnbCashierTerminal;
use App\Models\Fnb\FnbCashCategory;
use App\Models\Fnb\FnbCashEntry;
use App\Models\Fnb\FnbCategory;
use App\Models\Fnb\FnbMenu;
use App\Models\Fnb\FnbStockMovement;
use App\Models\Fnb\FnbStockReceipt;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\Pos\PosCashierShift;
use App\Models\User;
use App\Services\Fnb\FnbCashbookService;
use App\Services\Fnb\FnbReceiptImport;
use App\Services\Fnb\FnbReportService;
use App\Services\Fnb\FnbStockService;
use App\Services\Payment\PaymentOrchestratorService;
use App\Services\Permission\Club61PermissionMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use LogicException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Modul 29 — Mini Inventory & Keuangan F&B: stok per menu (tanpa bahan baku), HPP di data menu + snapshot per
 * penjualan, stok boleh minus, Penerimaan Barang (banyak menu per nota, manual / impor Excel) = pengeluaran Pembelian
 * Persediaan, Penyesuaian Stok / Opname, Buku Kas, Laporan Laba & Arus Kas.
 */
class FnbInventoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private FnbCategory $category;

    private FnbMenu $aqua;

    private FnbMenu $pocari;

    private FnbMenu $nasiGoreng;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');
        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin);

        $this->category = FnbCategory::create(['name' => 'Minuman', 'sort_order' => 1]);
        $menu = fn (string $name, int $price) => FnbMenu::create(['category_id' => $this->category->id, 'name' => $name, 'base_price' => $price, 'is_available' => true]);
        $this->aqua = $menu('Aqua 600ml', 8000);
        $this->pocari = $menu('Pocari', 10000);
        $this->nasiGoreng = $menu('Nasi Goreng', 45000);

        $stock = app(FnbStockService::class);
        $stock->updateSettings($this->aqua, 3000, true, 10);
        $stock->updateSettings($this->pocari, 5000, true, 10);
        $stock->updateSettings($this->nasiGoreng, 20000, false, null);
    }

    private function receive(array $lines, ?string $supplier = null): FnbStockReceipt
    {
        return app(FnbStockService::class)->receive(
            array_map(fn ($l) => ['menu_id' => $l[0]->id, 'qty' => $l[1], 'total' => $l[2] ?? 0], $lines),
            $supplier, null, null, $this->admin,
        );
    }

    private function paidOrder(array $lines, string $code): Order
    {
        $subtotal = collect($lines)->sum(fn ($l) => $l[0]->base_price * $l[1]);
        $order = Order::create(['order_number' => $code, 'order_type' => 'TAKE_AWAY', 'subtotal' => $subtotal, 'grand_total' => $subtotal, 'payment_status' => 'UNPAID']);
        foreach ($lines as [$menu, $qty]) {
            $order->items()->create(['item_type' => 'FNB', 'reference_id' => $menu->id, 'item_name' => $menu->name, 'quantity' => $qty,
                'unit_price' => $menu->base_price, 'unit_cost' => $menu->hpp, 'subtotal' => $menu->base_price * $qty]);
        }
        Payment::create(['order_id' => $order->id, 'payment_gateway' => 'MIDTRANS', 'transaction_id' => $code, 'amount' => $subtotal, 'payment_method' => 'QRIS', 'status' => 'PENDING']);
        app(PaymentOrchestratorService::class)->markOrderAsPaid($order, ['payment_gateway' => 'MIDTRANS', 'transaction_id' => $code, 'payment_method' => 'QRIS', 'amount' => $subtotal]);

        return $order;
    }

    /** @param  list<array<int, mixed>>  $rows */
    private function xlsx(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pb-test').'.xlsx';
        $writer = new Writer();
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName(FnbReceiptImport::SHEET);
        $writer->addRow(Row::fromValues(FnbReceiptImport::HEADERS));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return UploadedFile::fake()->createWithContent('penerimaan.xlsx', file_get_contents($path));
    }

    private function expectRejected(callable $callback, string $message): void
    {
        try {
            $callback();
            $this->fail("Seharusnya ditolak: {$message}");
        } catch (HttpException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }

    public function test_cashier_sale_deducts_tracked_stock_once_snapshots_hpp_and_may_go_negative(): void
    {
        $cashier = User::factory()->cashier()->create(['is_active' => true]);
        $cashier->givePermissionTo(['access_pos_terminal', 'process_fnb_order', 'open_pos_shift']);
        PosCashierShift::create(['shift_number' => 'SFT-FNB-1', 'counter' => 'FNB_COUNTER', 'status' => 'OPEN', 'opened_by_id' => $cashier->id, 'opened_at' => now(), 'starting_cash' => 0, 'expected_cash' => 0]);
        $this->actingAs($cashier);

        Livewire::test(FnbCashierTerminal::class)
            ->call('addToCart', $this->aqua->id)->call('addToCart', $this->aqua->id)->call('addToCart', $this->nasiGoreng->id)
            ->call('proceedToPayment')->call('setPaymentMethod', 'QRIS')
            ->set('qrisMode', 'MANUAL')->set('qrisRrn', '998877665544')
            ->call('submitFnbCheckout')
            ->assertSet('errorMessage', null);

        $order = Order::where('payment_status', 'PAID')->sole();
        $this->assertSame(-2, $this->aqua->fresh()->stock_qty, 'stok boleh minus, penjualan tidak diblok');
        $this->assertSame(0, $this->nasiGoreng->fresh()->stock_qty, 'menu tidak dilacak tidak dipotong');
        $sale = FnbStockMovement::sole();
        $this->assertSame([FnbStockMovement::SALE, -2, -2, $order->id], [$sale->type, $sale->qty_change, $sale->stock_after, $sale->order_id]);
        $this->assertEqualsCanonicalizing([3000.0, 20000.0], $order->items->map(fn ($i) => (float) $i->unit_cost)->all());

        // HPP diganti setelah terjual → snapshot penjualan lama tetap.
        app(FnbStockService::class)->updateSettings($this->aqua->fresh(), 3500, true, 10);
        $this->assertEqualsWithDelta(3000, (float) $order->items()->where('reference_id', $this->aqua->id)->value('unit_cost'), 0.01);

        // Notifikasi lunas berulang → stok tidak dipotong dua kali.
        app(FnbStockService::class)->deductForOrder($order);
        $this->assertSame(1, FnbStockMovement::count());
        $this->assertSame(-2, $this->aqua->fresh()->stock_qty);
    }

    public function test_duplicate_payment_notification_does_not_deduct_twice_and_fills_missing_hpp(): void
    {
        $order = Order::create(['order_number' => 'ORD-FNB-DUP', 'order_type' => 'TAKE_AWAY', 'subtotal' => 24000, 'grand_total' => 24000, 'payment_status' => 'UNPAID']);
        // Pesanan dibuat sebelum HPP dicatat (unit_cost kosong).
        $order->items()->create(['item_type' => 'FNB', 'reference_id' => $this->aqua->id, 'item_name' => 'Aqua 600ml', 'quantity' => 3, 'unit_price' => 8000, 'subtotal' => 24000]);
        Payment::create(['order_id' => $order->id, 'payment_gateway' => 'MIDTRANS', 'transaction_id' => 'ORD-FNB-DUP', 'amount' => 24000, 'payment_method' => 'QRIS', 'status' => 'PENDING']);

        $orchestrator = app(PaymentOrchestratorService::class);
        $payload = ['payment_gateway' => 'MIDTRANS', 'transaction_id' => 'ORD-FNB-DUP', 'payment_method' => 'QRIS', 'amount' => 24000];
        $orchestrator->markOrderAsPaid($order, $payload);
        $orchestrator->markOrderAsPaid($order->fresh(), $payload);

        $this->assertSame(-3, $this->aqua->fresh()->stock_qty);
        $this->assertSame(1, FnbStockMovement::where('type', 'SALE')->count());
        $this->assertEqualsWithDelta(3000, (float) $order->items()->value('unit_cost'), 0.01);
    }

    public function test_receipt_with_many_items_closes_negative_stock_records_one_expense_and_can_be_voided(): void
    {
        app(FnbStockService::class)->adjust($this->aqua, -3, 'LOST', 'Hilang di gudang belakang', $this->admin);

        $receipt = $this->receive([[$this->aqua, 24, 72000], [$this->pocari, 12, 60000]], 'Toko Sumber');
        $this->assertMatchesRegularExpression('/^PB-\d{6}-[A-Z0-9]{5}$/', $receipt->receipt_number);
        $this->assertSame([2, 'MANUAL', 'Toko Sumber'], [$receipt->item_count, $receipt->source, $receipt->supplier]);
        $this->assertSame(21, $this->aqua->fresh()->stock_qty, '−3 + 24 = 21');
        $this->assertSame(12, $this->pocari->fresh()->stock_qty);

        $expense = FnbCashEntry::sole();
        $this->assertSame(FnbCashCategory::RESTOCK_KEY, $expense->category->system_key);
        $this->assertSame('Pembelian Persediaan', $expense->category->name);
        $this->assertEqualsWithDelta(132000, (float) $expense->amount, 0.01, 'satu pengeluaran sesuai total nota');
        $this->assertSame($receipt->id, $expense->receipt_id);

        app(FnbStockService::class)->voidReceipt($receipt, 'Salah input jumlah', $this->admin);
        $this->assertSame(-3, $this->aqua->fresh()->stock_qty);
        $this->assertSame(0, $this->pocari->fresh()->stock_qty);
        $this->assertNotNull($receipt->fresh()->voided_at);
        $this->assertNotNull($expense->fresh()->voided_at);
        $this->assertSame(2, FnbStockMovement::where('type', 'RESTOCK_VOID')->count());
        $this->assertSame(2, FnbStockMovement::where('type', 'RESTOCK')->whereNotNull('voided_at')->count());

        $this->expectRejected(fn () => app(FnbStockService::class)->voidReceipt($receipt->fresh(), 'Dobel batal', $this->admin), 'sudah dibatalkan');
    }

    public function test_receipt_rejects_duplicate_menu_atomically_and_auto_enables_untracked_menu(): void
    {
        $this->expectRejected(fn () => $this->receive([[$this->aqua, 5], [$this->aqua, 2]]), 'lebih dari satu kali');
        $this->assertSame(0, $this->aqua->fresh()->stock_qty, 'semua baris atau tidak sama sekali');
        $this->assertSame(0, FnbStockReceipt::count());

        // Barang yang diterima pasti dihitung: menu yang belum dilacak diaktifkan otomatis.
        $this->receive([[$this->aqua, 5], [$this->nasiGoreng, 4, 60000]]);
        $this->assertTrue($this->nasiGoreng->fresh()->track_stock);
        $this->assertSame([5, 4], [$this->aqua->fresh()->stock_qty, $this->nasiGoreng->fresh()->stock_qty]);

        $this->expectRejected(fn () => app(FnbStockService::class)->adjust($this->aqua, -1, 'DAMAGED', '', $this->admin), 'Keterangan');
    }

    public function test_adjustment_of_many_menus_and_consignment_return_is_not_a_loss(): void
    {
        $this->receive([[$this->aqua, 10], [$this->pocari, 10]]);
        $this->expectRejected(fn () => app(FnbStockService::class)->adjustMany([
            ['menu_id' => $this->aqua->id, 'change' => -1, 'reason' => 'DAMAGED'],
            ['menu_id' => $this->aqua->id, 'change' => -1, 'reason' => 'LOST'],
        ], 'Dobel baris', $this->admin), 'lebih dari satu kali');

        $count = app(FnbStockService::class)->adjustMany([
            ['menu_id' => $this->aqua->id, 'change' => -2, 'reason' => 'DAMAGED'],
            ['menu_id' => $this->pocari->id, 'change' => -4, 'reason' => 'RETURNED_CONSIGNOR'],
        ], 'Botol pecah & sisa titipan dikembalikan', $this->admin);
        $this->assertSame(2, $count);
        $this->assertSame([8, 6], [$this->aqua->fresh()->stock_qty, $this->pocari->fresh()->stock_qty]);

        $today = now('Asia/Jakarta')->toDateString();
        $s = app(FnbReportService::class)->summary($today, $today);
        $this->assertEqualsWithDelta(6000, $s['stock_loss'], 0.01, 'hanya 2 × Rp3.000 rusak; titipan kembali bukan kerugian');
    }

    public function test_opname_records_only_differences_and_enabling_tracking_starts_from_zero(): void
    {
        $stock = app(FnbStockService::class);
        $this->receive([[$this->aqua, 10]]);
        $this->assertSame(0, FnbCashEntry::count(), 'tanpa harga pembelian (mis. barang titipan) tidak ada pengeluaran');

        $result = $stock->opname([$this->aqua->id => 7, $this->nasiGoreng->id => 3], 'Opname akhir bulan', $this->admin);
        $this->assertSame(['counted' => 1, 'changed' => 1], $result, 'menu tidak dilacak tidak ikut opname');
        $opname = FnbStockMovement::where('type', 'OPNAME')->sole();
        $this->assertSame([-3, 7], [$opname->qty_change, $opname->stock_after]);

        $stock->opname([$this->aqua->id => 7], null, $this->admin);
        $this->assertSame(1, FnbStockMovement::where('type', 'OPNAME')->count(), 'tanpa selisih tidak menambah baris');

        $stock->updateSettings($this->aqua->fresh(), 3000, false, null);
        $stock->updateSettings($this->aqua->fresh(), 3000, true, 5);
        $this->assertSame(0, $this->aqua->fresh()->stock_qty);
    }

    public function test_stock_card_rows_and_receipts_cannot_be_edited_or_deleted(): void
    {
        $receipt = $this->receive([[$this->aqua, 5]]);

        try {
            $receipt->update(['total_amount' => 1]);
            $this->fail('penerimaan tidak boleh diubah');
        } catch (LogicException) {
        }

        $this->expectException(LogicException::class);
        FnbStockMovement::where('type', 'RESTOCK')->sole()->update(['qty_change' => 50]);
    }

    public function test_stock_page_receipt_adjustment_opname_card_and_void_follow_permissions(): void
    {
        $page = Livewire::test(StokFnb::class)
            ->assertSee('Aqua 600ml')->assertSee('Nasi Goreng')->assertSee('Tidak dilacak')->assertSee('Nilai Stok (HPP)')
            ->assertSeeHtml('sf-menu-link')->assertDontSeeHtml('title="Penyesuaian Stok"')
            ->call('openModal', 'receipt')->set('lines.0.menu_id', $this->aqua->id)
            ->set('lines.0.qty', 12)->set('lines.0.price', 3000)
            ->call('addLine')->set('lines.1.menu_id', $this->pocari->id)->set('lines.1.qty', 6)->set('lines.1.price', 5000)
            ->assertSee('Rp 66.000')
            ->set('supplier', 'Agen Air')->set('invoiceNumber', 'INV-0912')
            ->call('saveReceipt')
            ->assertHasNoErrors()->assertSet('modal', null);
        $this->assertSame([12, 6], [$this->aqua->fresh()->stock_qty, $this->pocari->fresh()->stock_qty]);
        $receipt = FnbStockReceipt::sole();
        $this->assertSame('INV-0912', $receipt->invoice_number);

        // Menu yang sama di dua baris ditolak validasi.
        $page->call('openModal', 'receipt')->set('lines.0.menu_id', $this->aqua->id)->set('lines.0.qty', 1)
            ->call('addLine')->set('lines.1.menu_id', $this->aqua->id)->set('lines.1.qty', 1)
            ->call('saveReceipt')->assertHasErrors(['lines.1.menu_id' => 'distinct'])->call('closeModal');

        $page->call('openModal', 'adjust')
            ->set('lines.0.menu_id', $this->aqua->id)->set('lines.0.direction', 'OUT')->set('lines.0.qty', 2)->set('lines.0.reason', 'DAMAGED')
            ->call('addLine')->set('lines.1.menu_id', $this->pocari->id)->set('lines.1.direction', 'IN')->set('lines.1.qty', 1)->set('lines.1.reason', 'WRONG_INPUT')
            ->set('notes', 'Botol pecah')
            ->call('saveAdjustment')->assertHasNoErrors();
        $this->assertSame([10, 7], [$this->aqua->fresh()->stock_qty, $this->pocari->fresh()->stock_qty]);

        $page->call('openModal', 'opname')->set('counts.'.$this->aqua->id, 9)->set('countNotes.'.$this->aqua->id, '1 botol ketemu pecah di gudang')
            ->set('notes', 'Opname akhir bulan')->call('saveOpname');
        $this->assertSame(9, $this->aqua->fresh()->stock_qty);
        $this->assertSame('1 botol ketemu pecah di gudang · Opname akhir bulan', FnbStockMovement::where('type', 'OPNAME')->sole()->notes, 'catatan per menu + catatan umum');

        $page->call('openModal', 'card', $this->aqua->id)->assertSet('modal', 'card')
            ->assertSee('Kartu Stok · stok saat ini 9')->assertSee('No. '.$receipt->receipt_number)->assertSee('Supplier: Agen Air')->assertSee('Botol pecah')
            ->call('closeModal');

        // Pengaturan HPP tidak lagi di halaman ini (hanya di data menu).
        $page->call('openModal', 'settings', $this->aqua->id)->assertSet('modal', null);

        $page->call('setTab', 'receipts')->assertSee($receipt->receipt_number)->assertSee('INV-0912')
            ->call('openReceipt', $receipt->id)->assertSee('Jumlah Diterima')->call('closeModal')
            ->call('openVoid', $receipt->id)->set('voidReason', 'Salah input jumlah')->call('confirmVoid');
        $this->assertSame([-3, 1], [$this->aqua->fresh()->stock_qty, $this->pocari->fresh()->stock_qty]);
        $this->assertNotNull(FnbCashEntry::where('receipt_id', $receipt->id)->value('voided_at'));

        $page->call('setTab', 'history')->assertSee('Penerimaan Barang')->assertSee('Pembatalan Penerimaan')
            ->assertSee('Penyesuaian Stok · Rusak / Terbuang')->assertSee('Stok Opname');

        // Staf yang hanya boleh melihat stok.
        $viewer = User::factory()->cashier()->create();
        $viewer->givePermissionTo('View:StokFnb');
        $this->actingAs($viewer);
        $this->assertTrue(StokFnb::canAccess());
        $this->assertFalse(BukuKasFnb::canAccess());
        Livewire::test(StokFnb::class)->assertDontSee('+ Penerimaan Barang')->assertDontSee('Impor Excel')
            ->set('lines', [['menu_id' => $this->aqua->id, 'qty' => 5, 'price' => 0]])
            ->call('saveReceipt')->assertForbidden();
        $this->assertSame(-3, $this->aqua->fresh()->stock_qty);
    }

    public function test_excel_import_lists_all_menus_calculates_and_groups_per_supplier_invoice(): void
    {
        $teh = FnbMenu::create(['category_id' => $this->category->id, 'name' => 'Teh Pucuk', 'base_price' => 10000, 'is_available' => true]);
        $page = Livewire::test(StokFnb::class)->call('openModal', 'import')->assertSee('Input Manual')->assertSee('Impor Excel');
        $page->call('downloadImportTemplate')->assertFileDownloaded();

        // Templat berisi SEMUA menu (termasuk yang belum dilacak), kolom isian masih kosong.
        $template = app(FnbReceiptImport::class)->buildTemplate(tempnam(sys_get_temp_dir(), 'tpl').'.xlsx');
        $reader = new \OpenSpout\Reader\XLSX\Reader();
        $reader->open($template);
        $names = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $i => $row) {
                if ($i > 1) {
                    $names[] = $row->toArray()[1];
                }
            }
            break;
        }
        $reader->close();
        $this->assertEqualsCanonicalizing(['Aqua 600ml', 'Pocari', 'Nasi Goreng', 'Teh Pucuk'], $names);
        $this->assertStringContainsString('Belum ada baris yang diisi', app(FnbReceiptImport::class)->parse($template)['fatal']);

        // Kolom: ID, Nama, Kategori, Lacak, Stok di Sistem, Stok Fisik, Jumlah, Harga/Satuan, Total, Supplier, No. Nota.
        $page->set('importFile', $this->xlsx([
            [$this->aqua->id, 'Aqua 600ml', '', 'Ya', 0, null, 24, null, null, 'Agen Air', 'INV-1'],
            ['', 'Menu Fiktif', '', '', 0, null, 3, 1000, null, null, null],
            [$this->pocari->id, 'Pocari', '', 'Ya', 0, -1, null, null, null, null, null],
            [$teh->id, 'Teh Pucuk', '', 'Tidak', 0, null, null, 3500, null, null, null],
        ]))
            ->assertSee('4 baris bermasalah')->assertSee('Harga Beli per Satuan wajib diisi')->assertSee('Menu "Menu Fiktif" tidak ditemukan')
            ->assertSee('Stok Fisik Saat Ini harus bilangan bulat')->assertSee('Harga diisi tanpa Jumlah Diterima')
            ->call('saveImport');
        $this->assertSame(0, FnbStockReceipt::count());

        // Valid: stok fisik → opname dulu, beda supplier/nota → penerimaan terpisah, menu belum dilacak diaktifkan.
        $page->set('importFile', $this->xlsx([
            [$this->aqua->id, 'Aqua 600ml', '', 'Ya', 0, 10, 24, 3000, null, 'Agen Air', 'INV-1'],
            ['', 'pocari', '', 'Ya', 0, null, 12, 5000, null, 'agen air', 'inv-1'],
            [$teh->id, 'Teh Pucuk', '', 'Tidak', 0, null, 24, 3500, null, 'Toko Makmur', '778'],
            [$this->nasiGoreng->id, 'Nasi Goreng', '', 'Tidak', 0, null, null, null, null, null, null],
        ]))
            ->assertSee('semua baris valid: 2 Penerimaan Barang')->assertSee('Lacak Persediaan diaktifkan')
            ->set('updateHpp', true)->set('notes', 'Barang datang Senin')
            ->call('saveImport')->assertSet('modal', null);

        $this->assertSame([34, 12, 24], [$this->aqua->fresh()->stock_qty, $this->pocari->fresh()->stock_qty, $teh->fresh()->stock_qty], '10 fisik + 24 diterima = 34');
        $this->assertTrue($teh->fresh()->track_stock);
        $this->assertFalse($this->nasiGoreng->fresh()->track_stock, 'baris kosong tidak diproses');
        $this->assertEqualsWithDelta(3500, (float) $teh->fresh()->hpp, 0.01, 'Perbarui HPP');
        $this->assertSame(1, FnbStockMovement::where('type', 'OPNAME')->count());

        $receipts = FnbStockReceipt::orderBy('total_amount')->get();
        $this->assertCount(2, $receipts);
        $this->assertSame([['Toko Makmur', '778', 1, 84000.0], ['Agen Air', 'INV-1', 2, 132000.0]],
            $receipts->map(fn ($r) => [$r->supplier, $r->invoice_number, $r->item_count, (float) $r->total_amount])->all());
        $this->assertTrue($receipts->every(fn ($r) => $r->source === 'IMPORT'));
        $this->assertSame(2, FnbCashEntry::count(), 'satu pengeluaran per nota');

        // Staf tanpa izin Stok Opname tidak bisa mengimpor kolom Stok Fisik.
        $staff = User::factory()->cashier()->create();
        $staff->givePermissionTo(['View:StokFnb', 'restock_fnb_stock']);
        $this->actingAs($staff);
        Livewire::test(StokFnb::class)->call('openModal', 'import')
            ->set('importFile', $this->xlsx([[$this->aqua->id, 'Aqua 600ml', '', 'Ya', 34, 30, null, null, null, null, null]]))
            ->call('saveImport')->assertNotified('Akses ditolak');
        $this->assertSame(34, $this->aqua->fresh()->stock_qty);
    }

    public function test_pages_open_from_admin_panel_and_are_hidden_without_permission(): void
    {
        foreach ([StokFnb::class, BukuKasFnb::class, LaporanFnb::class] as $page) {
            $this->get($page::getUrl())->assertOk()->assertSee($page::getNavigationLabel());
        }

        // Semua menu F&B dalam satu grup sidebar, Menu F&B paling atas.
        foreach ([KelolaMenuFnb::class, StokFnb::class, BukuKasFnb::class, LaporanFnb::class] as $page) {
            $this->assertSame('F&B', $page::getNavigationGroup());
        }
        $this->assertLessThan(StokFnb::getNavigationSort(), KelolaMenuFnb::getNavigationSort());

        $this->actingAs(User::factory()->cashier()->create());
        foreach ([StokFnb::class, BukuKasFnb::class, LaporanFnb::class] as $page) {
            // Panel mengarahkan staf ke halaman awal role-nya (302) atau menolak (403) — halaman tidak pernah tampil.
            $this->assertContains($this->get($page::getUrl())->status(), [302, 403]);
        }
    }

    public function test_new_permissions_go_to_admin_but_not_cashier(): void
    {
        $slugs = ['View:StokFnb', 'restock_fnb_stock', 'adjust_fnb_stock', 'opname_fnb_stock', 'manage_fnb_hpp', 'View:BukuKasFnb', 'manage_fnb_cashbook', 'View:LaporanFnb'];
        $this->assertEmpty(array_diff($slugs, Club61PermissionMatrix::getAllPermissionSlugs()));
        $this->assertEmpty(array_diff($slugs, Club61PermissionMatrix::defaultRolePermissions('admin')));
        $this->assertEmpty(array_intersect($slugs, Club61PermissionMatrix::defaultRolePermissions('cashier')));
    }

    public function test_cashbook_records_expense_with_private_receipt_and_void_rules(): void
    {
        Storage::fake('local');
        $gas = FnbCashCategory::where('name', 'Gas')->sole();

        $page = Livewire::test(BukuKasFnb::class)
            ->call('openEntry')->assertSee('Catat Pengeluaran')->assertDontSee('Pemasukan Lain')
            ->set('entryCategoryId', $gas->id)->set('entryAmount', 300000)->set('entryNotes', 'Isi ulang 2 tabung')
            ->set('entryAttachment', UploadedFile::fake()->image('nota.jpg', 600, 800))
            ->call('saveEntry')
            ->assertSet('modal', null)
            ->assertSee('Isi ulang 2 tabung');

        $entry = FnbCashEntry::sole();
        $this->assertEqualsWithDelta(300000, (float) $entry->amount, 0.01);
        Storage::disk('local')->assertExists($entry->attachment_path);
        $page->call('downloadAttachment', $entry->id)->assertFileDownloaded();

        // Pembelian Persediaan hanya dari Penerimaan Barang; tanggal masa depan ditolak.
        $restockCategory = app(FnbStockService::class)->restockCategory();
        $this->expectRejected(fn () => app(FnbCashbookService::class)->record($restockCategory->id, now()->toDateString(), 1000, null, null, $this->admin), 'Penerimaan Barang');
        $this->expectRejected(fn () => app(FnbCashbookService::class)->record($gas->id, now()->addDays(2)->toDateString(), 1000, null, null, $this->admin), 'masa depan');

        $page->call('openVoid', $entry->id)->set('voidReason', 'Nominal salah ketik')->call('confirmVoid');
        $this->assertNotNull($entry->fresh()->voided_at);

        // Pengeluaran otomatis Penerimaan Barang tidak bisa dibatalkan dari Buku Kas.
        $this->receive([[$this->aqua, 5, 15000]]);
        $auto = FnbCashEntry::whereNotNull('receipt_id')->sole();
        $this->expectRejected(fn () => app(FnbCashbookService::class)->void($auto, 'Coba batal', $this->admin), 'Batalkan Penerimaan');
        $this->assertNull($auto->fresh()->voided_at);
        Livewire::test(BukuKasFnb::class)->assertSee('Dari Penerimaan Barang');

        $this->expectException(LogicException::class);
        $entry->fresh()->delete();
    }

    public function test_cashbook_categories_can_be_added_and_system_category_stays_active(): void
    {
        $page = Livewire::test(BukuKasFnb::class)->call('openCategories')
            ->set('categoryName', 'Sewa Mesin Kopi')->call('saveCategory');
        $this->assertTrue(FnbCashCategory::where('name', 'Sewa Mesin Kopi')->where('is_stock_purchase', false)->exists());

        $system = app(FnbStockService::class)->restockCategory();
        $page->call('toggleCategory', $system->id);
        $this->assertTrue($system->fresh()->is_active);
    }

    public function test_report_matches_prd_example_profit_and_cash_flow(): void
    {
        $stock = app(FnbStockService::class);
        $cashbook = app(FnbCashbookService::class);
        $category = fn (string $name) => FnbCashCategory::where('name', $name)->sole()->id;

        $stock->opname([$this->aqua->id => 20], 'Stok awal', $this->admin);             // stok awal: bukan pengeluaran, bukan kerugian
        $this->receive([[$this->aqua->fresh(), 48, 144000]]);                             // Pembelian Persediaan Rp144.000
        $this->paidOrder([[$this->aqua->fresh(), 60]], 'ORD-FNB-AQ');                      // jual 60 × 8.000, HPP 3.000
        $this->paidOrder([[$this->nasiGoreng->fresh(), 100]], 'ORD-FNB-NG');               // jual 100 × 45.000, HPP 20.000
        $stock->adjust($this->aqua->fresh(), -2, 'DAMAGED', '2 botol pecah', $this->admin);
        $stock->opname([$this->aqua->id => 5], 'Opname akhir bulan', $this->admin);        // sistem 6, fisik 5
        $cashbook->record($category('Belanja Bahan Dapur'), now()->toDateString(), 1800000, null, null, $this->admin);
        $cashbook->record($category('Gas'), now()->toDateString(), 300000, null, null, $this->admin);
        $cashbook->record($category('Gaji'), now()->toDateString(), 2000000, null, null, $this->admin);

        $today = now('Asia/Jakarta')->toDateString();
        $s = app(FnbReportService::class)->summary(now('Asia/Jakarta')->startOfMonth()->toDateString(), $today);

        $this->assertEqualsWithDelta(4980000, $s['sales'], 0.01);
        $this->assertEqualsWithDelta(2180000, $s['hpp'], 0.01);
        $this->assertEqualsWithDelta(2800000, $s['gross_profit'], 0.01);
        $this->assertEqualsWithDelta(9000, $s['stock_loss'], 0.01);
        $this->assertEqualsWithDelta(2300000, $s['operational'], 0.01);
        $this->assertEqualsWithDelta(491000, $s['net_profit'], 0.01);
        $this->assertEqualsWithDelta(4244000, $s['money_out'], 0.01);
        $this->assertEqualsWithDelta(736000, $s['cash_flow'], 0.01);
        $this->assertSame(5, $this->aqua->fresh()->stock_qty);

        Livewire::test(LaporanFnb::class)
            ->assertSee('Rp 491.000')->assertSee('Rp 736.000')->assertSee('Nasi Goreng')
            ->assertSee('Aqua 600ml');
    }

    public function test_report_exports_excel_pdf_and_opname_sheet_with_permissions(): void
    {
        $this->receive([[$this->aqua, 48, 144000]], 'Agen Air');
        $this->paidOrder([[$this->aqua->fresh(), 10]], 'ORD-FNB-EXP');
        app(FnbCashbookService::class)->record(FnbCashCategory::where('name', 'Gas')->sole()->id, now()->toDateString(), 50000, 'Gas', null, $this->admin);

        // Excel: 5 sheet, angka Laba Bersih sama dengan layar.
        $path = app(\App\Services\Fnb\FnbReportExport::class)->excel(null, null, 'Semua tanggal', tempnam(sys_get_temp_dir(), 'rep').'.xlsx');
        $reader = new \OpenSpout\Reader\XLSX\Reader();
        $reader->open($path);
        $sheets = [];
        $netProfit = null;
        foreach ($reader->getSheetIterator() as $sheet) {
            $sheets[] = $sheet->getName();
            foreach ($sheet->getRowIterator() as $row) {
                $cells = $row->toArray();
                if (($cells[0] ?? null) === 'Laba Bersih') {
                    $netProfit = $cells[1];
                }
            }
        }
        $reader->close();
        $this->assertSame(['Ringkasan', 'Per Menu', 'Pengeluaran', 'Riwayat Stok', 'Stok Saat Ini'], $sheets);
        $this->assertEqualsWithDelta(app(FnbReportService::class)->summary(null, null)['net_profit'], (float) $netProfit, 0.01);
        $this->assertEqualsWithDelta(80000 - 30000 - 50000, (float) $netProfit, 0.01, 'penjualan 80rb − HPP 30rb − gas 50rb');

        Livewire::test(LaporanFnb::class)->assertSee('Unduh Laporan')
            ->call('exportExcel')->assertFileDownloaded()
            ->call('exportPdf')->assertFileDownloaded();
        $this->assertSame(2, \App\Models\Audit\ActivityLog::where('event', 'fnb_report.exported')->count());

        Livewire::test(StokFnb::class)->call('openModal', 'opname')->assertSee('Cetak Lembar Opname')
            ->call('printOpnameSheet')->assertFileDownloaded();

        // Bisa melihat laporan & stok tapi tanpa izin export / opname → ditolak.
        $viewer = User::factory()->cashier()->create();
        $viewer->givePermissionTo(['View:LaporanFnb', 'View:StokFnb']);
        $this->actingAs($viewer);
        $this->assertFalse(LaporanFnb::canExport());
        Livewire::test(LaporanFnb::class)->assertDontSee('Unduh Laporan')->call('exportExcel')->assertForbidden();
        Livewire::test(StokFnb::class)->call('printOpnameSheet')->assertForbidden();
    }

    public function test_expense_report_export_follows_filters_and_excludes_voided_from_totals(): void
    {
        $cashbook = app(FnbCashbookService::class);
        $gas = FnbCashCategory::where('name', 'Gas')->sole()->id;
        $cashbook->record($gas, now()->toDateString(), 300000, 'Isi ulang gas', null, $this->admin);
        $voided = $cashbook->record($gas, now()->toDateString(), 999000, 'Salah ketik', null, $this->admin);
        $cashbook->void($voided, 'Nominal salah ketik', $this->admin);
        $cashbook->record(FnbCashCategory::where('name', 'Gaji')->sole()->id, now()->toDateString(), 2000000, 'Gaji barista', null, $this->admin);
        $this->receive([[$this->aqua, 10, 30000]]);

        $readSheets = function (string $path): array {
            $reader = new \OpenSpout\Reader\XLSX\Reader();
            $reader->open($path);
            $sheets = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $sheets[$sheet->getName()][] = $row->toArray();
                }
            }
            $reader->close();

            return $sheets;
        };

        // Filter kategori Gas + semua status: rincian memuat yang batal, total hanya yang aktif.
        $page = Livewire::test(BukuKasFnb::class)->assertSee('Unduh Laporan')->set('categoryFilter', $gas)->set('statusFilter', 'ALL');
        $page->call('exportExcel')->assertFileDownloaded()->call('exportPdf')->assertFileDownloaded();

        $method = new \ReflectionMethod(BukuKasFnb::class, 'filteredEntries');
        $path = app(\App\Services\Fnb\FnbReportExport::class)->expensesExcel($method->invoke($page->instance()), 'Bulan ini', 'Semua status', tempnam(sys_get_temp_dir(), 'exp').'.xlsx');
        $sheets = $readSheets($path);
        $this->assertSame(['Rincian', 'Per Kategori'], array_keys($sheets));
        $detail = array_values(array_filter($sheets['Rincian'], fn ($r) => in_array($r[5] ?? null, ['Aktif', 'Dibatalkan'], true)));
        $this->assertCount(2, $detail, 'hanya kategori Gas, termasuk yang dibatalkan');
        $this->assertSame(['Aktif', 'Dibatalkan'], collect($detail)->sortBy(4)->pluck(5)->values()->all());
        $total = collect($sheets['Rincian'])->firstWhere(0, 'Total pengeluaran aktif');
        $this->assertEqualsWithDelta(300000, (float) $total[4], 0.01, 'yang dibatalkan tidak dihitung');
        $this->assertSame([['Gas', 'Biaya operasional', 1, 300000]], array_map(fn ($r) => [$r[0], $r[1], (int) $r[2], (int) $r[3]], array_slice($sheets['Per Kategori'], 1, 1)));
        $this->assertSame(2, \App\Models\Audit\ActivityLog::where('event', 'fnb_expense.exported')->count());

        // Tanpa izin export_reports → tombol tidak tampil, aksi ditolak.
        $viewer = User::factory()->cashier()->create();
        $viewer->givePermissionTo('View:BukuKasFnb');
        $this->actingAs($viewer);
        $this->assertFalse(BukuKasFnb::canExport());
        Livewire::test(BukuKasFnb::class)->assertDontSee('Unduh Laporan')->call('exportPdf')->assertForbidden();
    }

    public function test_menu_form_is_the_single_place_for_hpp_and_cashier_sees_stock_badge(): void
    {
        Livewire::test(KelolaMenuFnb::class)
            ->call('openEditMenuModal', $this->nasiGoreng->id)
            ->assertSee('HPP (Harga Pokok Penjualan) per Satuan')->assertSee('Tidak, dibuat sesuai pesanan')
            ->set('menuHpp', 22000)->set('menuTrackStock', true)->set('menuMinStock', 3)
            ->call('saveMenu');
        $menu = $this->nasiGoreng->fresh();
        $this->assertEqualsWithDelta(22000, (float) $menu->hpp, 0.01);
        $this->assertTrue($menu->track_stock);
        $this->assertSame(3, $menu->min_stock);

        $editor = User::factory()->cashier()->create();
        $editor->givePermissionTo(['View:KelolaMenuFnb', 'manage_fnb_menu']);
        $this->actingAs($editor);
        Livewire::test(KelolaMenuFnb::class)
            ->call('openEditMenuModal', $this->nasiGoreng->id)
            ->assertDontSee('HPP (Harga Pokok Penjualan) per Satuan')
            ->set('menuHpp', 1)
            ->call('saveMenu');
        $this->assertEqualsWithDelta(22000, (float) $this->nasiGoreng->fresh()->hpp, 0.01, 'tanpa izin manage_fnb_hpp HPP tidak berubah');

        // Kasir F&B: penanda stok, penjualan tetap bisa.
        $this->receive([[$this->aqua, 2]]);
        $cashier = User::factory()->cashier()->create(['is_active' => true]);
        $cashier->givePermissionTo(['access_pos_terminal', 'process_fnb_order']);
        $this->actingAs($cashier);
        Livewire::test(FnbCashierTerminal::class)->assertSee('Sisa 2')->assertSee('Aqua 600ml');
    }

    public function test_expense_dated_today_in_wib_is_accepted_after_midnight_while_server_clock_is_utc(): void
    {
        $gas = FnbCashCategory::where('name', 'Gas')->sole();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-30 18:30:00', 'UTC')); // 31/10 01:30 WIB

        $entry = app(FnbCashbookService::class)->record($gas->id, '2026-10-31', 50000, null, null, $this->admin);
        $this->assertSame('2026-10-31', $entry->entry_date->toDateString());
        $this->expectRejected(fn () => app(FnbCashbookService::class)->record($gas->id, '2026-11-01', 50000, null, null, $this->admin), 'masa depan');
    }

    public function test_re_enabling_tracking_zeroes_old_stock_on_the_stock_card_without_counting_a_loss(): void
    {
        $stock = app(FnbStockService::class);
        $this->receive([[$this->aqua, 10]]);
        $receipt = $this->receive([[$this->pocari, 4]]);

        $stock->updateSettings($this->aqua->fresh(), 3000, false, null);
        $stock->updateSettings($this->aqua->fresh(), 3000, true, 5, $this->admin);
        $this->assertSame(0, $this->aqua->fresh()->stock_qty);
        $reset = FnbStockMovement::where('menu_id', $this->aqua->id)->where('type', FnbStockMovement::ADJUSTMENT)->sole();
        $this->assertSame([FnbStockMovement::ADJUSTMENT, -10, 0, 0.0], [$reset->type, $reset->qty_change, $reset->stock_after, (float) $reset->unit_cost]);
        $this->assertStringContainsString('Lacak Persediaan diaktifkan kembali', $reset->notes);
        $today = now('Asia/Jakarta')->toDateString();
        $this->assertEqualsWithDelta(0, app(FnbReportService::class)->summary($today, $today)['stock_loss'], 0.01);

        // Penerimaan menu yang sudah tidak dilacak dibatalkan → stoknya tidak disentuh, barisnya tetap ditandai batal.
        $stock->updateSettings($this->pocari->fresh(), 5000, false, null);
        $stock->voidReceipt($receipt, 'Nota dobel input', $this->admin);
        $this->assertSame(4, $this->pocari->fresh()->stock_qty);
        $this->assertNotNull($receipt->lines()->sole()->voided_at);
    }

    public function test_sale_of_many_menus_locks_in_order_and_zero_hpp_is_flagged_as_missing(): void
    {
        $teh = FnbMenu::create(['category_id' => $this->category->id, 'name' => 'Teh Manis', 'base_price' => 7000, 'is_available' => true]);
        $this->paidOrder([[$this->pocari, 1], [$teh, 2], [$this->aqua, 3]], 'ORD-FNB-MIX');

        $this->assertSame([-3, -1], [$this->aqua->fresh()->stock_qty, $this->pocari->fresh()->stock_qty]);
        $this->assertSame(2, FnbStockMovement::where('type', FnbStockMovement::SALE)->count());

        $today = now('Asia/Jakarta')->toDateString();
        $s = app(FnbReportService::class)->summary($today, $today);
        $this->assertSame(2, $s['missing_cost_qty'], 'HPP menu belum diisi (Rp0) ditandai, bukan dianggap laba penuh');
        Livewire::test(LaporanFnb::class)->assertSee('belum punya HPP')->assertSee('Menu: Teh Manis');
    }

    public function test_quantities_above_the_limit_are_rejected_in_service_and_excel_import(): void
    {
        $stock = app(FnbStockService::class);
        $this->expectRejected(fn () => $this->receive([[$this->aqua, FnbStockService::MAX_QTY + 1]]), 'maksimal');
        $this->expectRejected(fn () => $stock->opname([$this->aqua->id => FnbStockService::MAX_QTY + 1], null, $this->admin), 'maksimal');
        $this->expectRejected(fn () => $stock->adjust($this->aqua, -(FnbStockService::MAX_QTY + 1), 'LOST', 'Salah hitung besar', $this->admin), 'maksimal');

        $file = $this->xlsx([[$this->aqua->id, 'Aqua 600ml', '', 'Ya', 0, '', 3000000000, 1e13, null, null, null]]);
        $parsed = app(FnbReceiptImport::class)->parse($file->getRealPath());
        $this->assertSame(1, $parsed['errors']);
        $this->assertStringContainsString('Jumlah Diterima maksimal', $parsed['rows'][0]['error']);
    }

    public function test_category_type_is_locked_once_it_has_entries(): void
    {
        $cashbook = app(FnbCashbookService::class);
        $gas = FnbCashCategory::where('name', 'Gas')->sole();
        $cashbook->record($gas->id, now('Asia/Jakarta')->toDateString(), 100000, null, null, $this->admin);

        $this->expectRejected(fn () => $cashbook->saveCategory($gas, 'Gas', true), 'Jenis kategori tidak dapat diubah');
        $this->assertFalse($gas->fresh()->is_stock_purchase);
        $cashbook->saveCategory($gas->fresh(), 'Gas LPG', false);
        $this->assertSame('Gas LPG', $gas->fresh()->name, 'nama tetap boleh diganti');

        Livewire::test(BukuKasFnb::class)->call('openCategories')->call('editCategory', $gas->id)
            ->assertSet('categoryTypeLocked', true)->assertSee('sudah memiliki catatan');

        $fresh = FnbCashCategory::where('name', 'Perawatan')->sole();
        $cashbook->saveCategory($fresh, 'Perawatan', true);
        $this->assertTrue($fresh->fresh()->is_stock_purchase, 'kategori tanpa catatan masih boleh diubah jenisnya');
    }
}
