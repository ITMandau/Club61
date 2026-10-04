<?php

namespace Tests\Feature\Finance;

use App\Filament\Pages\AntrianRefund;
use App\Filament\Pages\BukuTransaksi;
use App\Filament\Pages\LogAktivitas;
use App\Models\Audit\ActivityLog;
use App\Models\Finance\LedgerEntry;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\ClubFinanceSetting;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\Pos\PosCashierShift;
use App\Models\Pos\Refund;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\LedgerReport;
use App\Services\Finance\LedgerWriter;
use App\Services\Finance\RefundQueueService;
use App\Services\Finance\TaxAndFeeService;
use App\Services\Payment\PaymentOrchestratorService;
use App\Services\Permission\Club61PermissionMatrix;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Modul 17 Fase 2 — halaman Buku Transaksi, invoice salinan admin, export, dan Antrian Refund.
 */
class BukuTransaksiTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');
        ClubFinanceSetting::getSettings()->update([
            'is_tax_enabled' => true, 'tax_name' => 'PB1', 'tax_type' => 'PERCENTAGE', 'tax_rate' => 10, 'tax_channels' => 'ALL',
            'is_admin_fee_enabled' => true, 'admin_fee_name' => 'Biaya Layanan', 'admin_fee_type' => 'PERCENTAGE',
            'admin_fee_amount' => 3, 'admin_fee_channels' => 'ALL',
        ]);
        Cache::forget(ClubFinanceSetting::CACHE_KEY);
        LedgerReport::flushMemo();

        $this->owner = User::factory()->superAdmin()->create(['name' => 'Owner']);
        $this->customer = User::factory()->customer()->create(['name' => 'Budi Santoso', 'phone' => '081277001122']);
        PosCashierShift::create([
            'shift_number' => 'SFT-PADEL-BT-0001', 'counter' => 'PADEL_FRONTDESK', 'status' => 'OPEN',
            'opened_by_id' => $this->owner->id, 'opened_at' => now(), 'starting_cash' => 0, 'expected_cash' => 0,
        ]);
    }

    /** Walk-in lapangan 300.000 + raket 50.000, diskon 35.000 → total 355.950 (contoh PRD §2), dilunasi di kasir. */
    /** $customerName = walk-in tanpa akun (nama diketik kasir); null = order milik akun customer. */
    private function paidWalkIn(?string $customerName = null, bool $withBooking = false): Order
    {
        $calc = app(TaxAndFeeService::class)->calculate(350000, 35000, 'POS_WALKIN', 'PADEL');
        $order = Order::create([
            'order_number' => 'ORD-BT-'.strtoupper(Str::random(8)), 'user_id' => $customerName === null ? $this->customer->id : null, 'cashier_id' => $this->owner->id,
            'customer_name' => $customerName, 'order_type' => 'WALK_IN', 'subtotal' => $calc['subtotal'], 'discount_amount' => $calc['discount_amount'],
            'tax_amount' => $calc['tax_amount'], 'service_charge' => $calc['admin_fee_amount'], 'grand_total' => $calc['grand_total'],
            'payment_status' => 'UNPAID',
        ]);
        $order->items()->create(['item_type' => 'PADEL', 'item_name' => 'Sewa Court 1', 'quantity' => 1, 'unit_price' => 300000, 'subtotal' => 300000]);
        $order->items()->create(['item_type' => 'EQUIPMENT', 'item_name' => 'Raket', 'quantity' => 1, 'unit_price' => 50000, 'subtotal' => 50000]);

        if ($withBooking) {
            $court = PadelCourt::create(['name' => 'Court BT', 'type' => 'INDOOR', 'hourly_rate_regular' => 300000, 'hourly_rate_prime' => 400000, 'is_active' => true]);
            $start = Carbon::parse(now()->addDay()->format('Y-m-d').' 10:00');
            PadelBooking::create([
                'booking_code' => 'BK-BT-'.Str::random(4), 'order_id' => $order->id, 'user_id' => $this->customer->id, 'court_id' => $court->id,
                'booking_date' => $start->toDateString(), 'start_time' => $start, 'end_time' => $start->copy()->addHour(),
                'court_fee' => 300000, 'total_amount' => $calc['grand_total'], 'status' => 'PENDING_PAYMENT',
            ]);
        }

        app(PaymentOrchestratorService::class)->markOrderAsPaid($order, [
            'payment_gateway' => 'CASHIER_POS', 'counter' => 'PADEL_FRONTDESK', 'payment_method' => 'QRIS', 'amount' => (float) $calc['grand_total'],
            'payload_log' => ['qris_details' => ['provider' => 'BCA_QRIS', 'rrn' => 'RRN'.random_int(100000, 999999)]],
        ]);

        return $order->fresh();
    }

    private function groupedRow(Order $order, string $type = LedgerEntry::TYPE_PAYMENT): LedgerEntry
    {
        return LedgerReport::grouped(LedgerEntry::query()->where('order_id', $order->id)->where('entry_type', $type))->firstOrFail();
    }

    private function adminWith(array $permissions): User
    {
        $admin = User::factory()->admin()->create();
        $admin->givePermissionTo($permissions);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return $admin;
    }

    public function test_only_super_admin_opens_the_pages_by_default(): void
    {
        $this->actingAs($this->owner)->get(BukuTransaksi::getUrl())->assertOk();
        $this->actingAs($this->owner)->get(AntrianRefund::getUrl())->assertOk();

        foreach (['admin', 'cashier'] as $role) {
            $user = User::factory()->role($role)->create();
            $this->actingAs($user)->get(BukuTransaksi::getUrl())->assertForbidden();
            $this->actingAs($user)->get(AntrianRefund::getUrl())->assertForbidden();
            $this->actingAs($user)->get(route('admin.buku-transaksi.export', ['format' => 'pdf']))->assertForbidden();
        }

        foreach (['View:BukuTransaksi', 'export_ledger', 'view_ledger_invoice', 'process_refund_queue'] as $permission) {
            $this->assertContains($permission, Club61PermissionMatrix::BACKDOOR_PERMISSIONS);
            $this->assertNotContains($permission, Club61PermissionMatrix::defaultRolePermissions('admin'));
        }
    }

    public function test_one_row_per_payment_and_summary_cards_follow_prd_definitions(): void
    {
        $order = $this->paidWalkIn();
        $this->actingAs($this->owner);

        $component = Livewire::test(BukuTransaksi::class)
            ->assertCountTableRecords(1)
            ->assertSee($order->order_number)
            ->assertSee('Sewa Lapangan Padel + Add-on Padel');

        $summary = $component->instance()->summary();
        $this->assertEquals(315000, $summary['net']);
        $this->assertEquals(9450, $summary['service']);
        $this->assertEquals(31500, $summary['tax']);
        $this->assertEquals(355950, $summary['money_net']);
        $this->assertSame(1, $summary['payments_count']);
    }

    public function test_refund_shows_as_its_own_row_and_reduces_the_net_total(): void
    {
        $order = $this->paidWalkIn();
        $payment = Payment::where('order_id', $order->id)->firstOrFail();
        Refund::create(['order_id' => $order->id, 'payment_id' => $payment->id, 'refund_amount' => 50850, 'reason' => 'Raket rusak', 'status' => 'PROCESSED']);
        $this->actingAs($this->owner);

        $component = Livewire::test(BukuTransaksi::class)->assertCountTableRecords(2)->assertSee('Direfund sebagian');
        $summary = $component->instance()->summary();
        $this->assertEquals(-50850, $summary['refunds']);
        $this->assertEquals(355950 - 50850, $summary['money_net']);
        // Kartu dihitung SETELAH refund dan bisa dijumlah: bersih + layanan + pajak = total uang masuk bersih.
        $this->assertEquals($summary['money_net'], $summary['net'] + $summary['service'] + $summary['tax']);
        $this->assertLessThan(315000, $summary['net']);
        $this->assertEquals(315000, $summary['net_before_refund']);

        Livewire::test(BukuTransaksi::class)->filterTable('status', ['REFUND'])->assertCountTableRecords(1);
    }

    public function test_changing_tax_or_fee_settings_later_does_not_change_past_transactions(): void
    {
        $order = $this->paidWalkIn();
        $before = LedgerEntry::where('order_id', $order->id)->orderBy('category')->get(['net_amount', 'service_amount', 'tax_amount', 'total_amount'])->toArray();

        ClubFinanceSetting::getSettings()->update(['tax_rate' => 11, 'admin_fee_amount' => 5]);
        Cache::forget(ClubFinanceSetting::CACHE_KEY);

        $this->assertSame($before, LedgerEntry::where('order_id', $order->id)->orderBy('category')->get(['net_amount', 'service_amount', 'tax_amount', 'total_amount'])->toArray());
        $this->assertEquals(31500, LedgerReport::summary(LedgerEntry::query())['tax']);
    }

    public function test_date_presets_use_wib_calendar_days(): void
    {
        $order = $this->paidWalkIn();
        $payment = Payment::create([
            'order_id' => $order->id, 'payment_gateway' => 'CASHIER_POS', 'transaction_id' => 'POS-LATE-NIGHT',
            'amount' => 100000, 'payment_method' => 'QRIS', 'status' => 'SUCCESS',
        ]);
        // 23:30 WIB kemarin — dulu jatuh ke hari berikutnya kalau batas hari dihitung UTC.
        $lateNight = Carbon::now(LedgerReport::TIMEZONE)->subDay()->setTime(23, 30)->setTimezone(config('app.timezone'));
        app(LedgerWriter::class)->recordPayment($payment, LedgerEntry::TYPE_OVERPAYMENT, $lateNight);

        $count = fn (string $preset) => LedgerReport::grouped(LedgerReport::applyFilters(LedgerEntry::query(), ['periode' => $preset]))->get()->count();
        $this->assertSame(1, $count('kemarin'));
        $this->assertSame(1, $count('hari_ini'));
        $this->assertSame(2, $count('7_hari'));

        $this->actingAs($this->owner);
        Livewire::test(BukuTransaksi::class)->filterTable('periode', ['preset' => 'kemarin'])->assertCountTableRecords(1)->assertSee('POS-LATE-NIGHT');
    }

    public function test_search_finds_booking_code_and_customer_phone(): void
    {
        $order = $this->paidWalkIn(withBooking: true);
        $code = PadelBooking::where('order_id', $order->id)->value('booking_code');

        foreach ([$code, '081277001122', $order->order_number] as $term) {
            $this->assertSame(1, LedgerReport::grouped(LedgerReport::applySearch(LedgerEntry::query(), $term))->get()->count(), $term);
        }
        $this->assertSame(0, LedgerReport::grouped(LedgerReport::applySearch(LedgerEntry::query(), 'tidak-ada'))->get()->count());
    }

    public function test_detail_slide_over_shows_split_proof_and_order_history(): void
    {
        $order = $this->paidWalkIn(withBooking: true);
        $this->actingAs($this->owner);

        Livewire::test(BukuTransaksi::class)
            ->mountTableAction('detail', $this->groupedRow($order))
            ->assertMountedActionModalSee('Pembagian per kategori')
            ->assertMountedActionModalSee('Add-on Padel')
            ->assertMountedActionModalSee('RRN QRIS')
            ->assertMountedActionModalSee('Riwayat pembayaran')
            ->assertMountedActionModalSee('Booking terkait');
    }

    public function test_invoice_is_an_admin_copy_and_is_logged(): void
    {
        $walkIn = $this->paidWalkIn(withBooking: true);
        $online = $this->paidWalkIn();
        $this->actingAs($this->owner);

        Livewire::test(BukuTransaksi::class)
            ->mountTableAction('invoice', $this->groupedRow($walkIn))
            ->assertMountedActionModalSee('SALINAN ADMIN')
            ->assertMountedActionModalSee($walkIn->order_number);

        Livewire::test(BukuTransaksi::class)
            ->mountTableAction('invoice', $this->groupedRow($online))
            ->assertMountedActionModalSee('SALINAN ADMIN')
            ->assertMountedActionModalSee('TOTAL DIBAYAR');

        $this->assertSame(2, ActivityLog::where('event', 'ledger.invoice_viewed')->count());
    }

    public function test_invoice_and_export_need_their_own_permissions(): void
    {
        $this->paidWalkIn();
        $viewer = $this->adminWith(['View:BukuTransaksi']);
        $this->actingAs($viewer);

        Livewire::test(BukuTransaksi::class)
            ->assertTableActionHidden('invoice', $this->groupedRow(Order::firstOrFail()))
            ->assertTableActionHidden('exportXlsx')
            ->assertTableActionHidden('exportPdf');
        $this->get(route('admin.buku-transaksi.export', ['format' => 'pdf']))->assertForbidden();
        $this->get(route('admin.buku-transaksi.export', ['format' => 'xlsx']))->assertForbidden();
        $this->assertSame(0, ActivityLog::where('event', 'ledger.invoice_viewed')->count());
    }

    public function test_export_links_bypass_spa_navigation_so_the_file_downloads(): void
    {
        // Dulu: mode SPA panel mengambil URL export lewat fetch → isi XLSX tampil sebagai teks di browser.
        $panel = \Filament\Facades\Filament::getPanel('admin');
        foreach (['xlsx', 'pdf'] as $format) {
            $url = route('admin.buku-transaksi.export', ['format' => $format, 'periode' => 'bulan_ini']);
            $this->assertTrue(\Illuminate\Support\Str::is($panel->getSpaUrlExceptions(), $url), $url);
        }
        $this->assertTrue(\Illuminate\Support\Str::is($panel->getSpaUrlExceptions(), route('admin.log-aktivitas.export')));
    }

    /** @return array<string, array<int, array<int, mixed>>> nama lembar → baris */
    private function readXlsx(\Illuminate\Testing\TestResponse $response): array
    {
        $path = $response->baseResponse->getFile()->getPathname();
        $reader = new Reader;
        $reader->open($path);
        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $rows = [];
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
            $sheets[$sheet->getName()] = $rows;
        }
        $reader->close();
        @unlink($path);

        return $sheets;
    }

    public function test_xlsx_export_has_both_sheets_neutralizes_formulas_and_omits_phone(): void
    {
        $order = $this->paidWalkIn('=HYPERLINK("http://evil")');
        $this->actingAs($this->owner);

        $response = $this->get(route('admin.buku-transaksi.export', ['format' => 'xlsx', 'periode' => 'hari_ini']))->assertOk();
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $sheets = $this->readXlsx($response);

        $this->assertSame(['Transaksi', 'Rincian Kategori'], array_keys($sheets));
        $this->assertCount(2, $sheets['Transaksi']);
        $this->assertCount(3, $sheets['Rincian Kategori'], 'Header + 2 baris kategori');
        $this->assertSame($order->order_number, $sheets['Transaksi'][1][2]);
        $this->assertSame('\'=HYPERLINK("http://evil")', $sheets['Transaksi'][1][5]);
        $this->assertEquals(355950, $sheets['Transaksi'][1][14]);
        $this->assertStringNotContainsString('081277001122', json_encode($sheets));

        $this->get(route('admin.buku-transaksi.export', ['format' => 'xlsx', 'source' => ['HACK']]))->assertSessionHasErrors('source.0');
        $this->get(route('admin.buku-transaksi.export', ['format' => 'csv']))->assertSessionHasErrors('format');
        $this->assertTrue(ActivityLog::where('event', 'ledger.exported')->exists());
    }

    public function test_pdf_export_downloads_a_report(): void
    {
        $order = $this->paidWalkIn();
        $this->actingAs($this->owner);

        $response = $this->get(route('admin.buku-transaksi.export', ['format' => 'pdf']))->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertTrue(ActivityLog::where('event', 'ledger.exported')->where('meta->format', 'pdf')->exists());

        $html = view('exports.buku-transaksi-pdf', [
            'summary' => LedgerReport::summary(LedgerEntry::query()), 'byCategory' => collect(), 'rows' => LedgerReport::grouped(LedgerEntry::query())->get(),
            'truncated' => false, 'total' => 1, 'limit' => 1500, 'periodLabel' => 'Periode: Bulan ini', 'printedBy' => 'Owner',
            'printedAt' => 'sekarang', 'company' => null,
        ])->render();
        $this->assertStringContainsString($order->order_number, $html);
        $this->assertStringContainsString('Rp 355.950', $html);
        $this->assertStringNotContainsString('081277001122', $html);
    }
    public function test_refund_queue_processes_into_the_ledger_once_and_rejects_with_reason(): void
    {
        $order = $this->paidWalkIn();
        $payment = Payment::where('order_id', $order->id)->firstOrFail();
        $first = Refund::create(['order_id' => $order->id, 'payment_id' => $payment->id, 'refund_amount' => 50000, 'reason' => 'Kelebihan bayar', 'status' => 'PENDING']);
        $second = Refund::create(['order_id' => $order->id, 'payment_id' => $payment->id, 'refund_amount' => 20000, 'reason' => 'Komplain', 'status' => 'PENDING']);
        $this->actingAs($this->owner);

        Livewire::test(AntrianRefund::class)
            ->assertCanSeeTableRecords([$first, $second])
            ->callTableAction('process', $first, ['refund_method' => 'TRANSFER_BANK', 'refund_reference' => 'TRF-889900', 'admin_notes' => 'BCA'])
            ->assertHasNoTableActionErrors();

        $first->refresh();
        $this->assertSame('PROCESSED', $first->status);
        $this->assertSame($this->owner->id, $first->processed_by_id);
        $this->assertEquals(-50000, (float) LedgerEntry::where('refund_id', $first->id)->sum('total_amount'));
        $this->assertTrue(ActivityLog::where('event', 'refund.processed')->where('severity', 'CRITICAL')->exists());

        try {
            app(RefundQueueService::class)->process($first, $this->owner, 'TRANSFER_BANK', 'TRF-2');
            $this->fail('Refund yang sudah diproses tidak boleh diproses lagi');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $this->assertEquals(-50000, (float) LedgerEntry::where('refund_id', $first->id)->sum('total_amount'));

        Livewire::test(AntrianRefund::class)
            ->callTableAction('reject', $second, ['reason' => 'Customer membatalkan permintaan'])
            ->assertHasNoTableActionErrors();
        $this->assertSame('REJECTED', $second->fresh()->status);
        $this->assertSame(0, LedgerEntry::where('refund_id', $second->id)->count());
        $this->assertTrue(ActivityLog::where('event', 'refund.rejected')->exists());
    }

    public function test_refund_queue_requires_permission_on_the_server(): void
    {
        $order = $this->paidWalkIn();
        $refund = Refund::create([
            'order_id' => $order->id, 'payment_id' => Payment::where('order_id', $order->id)->value('id'),
            'refund_amount' => 1000, 'reason' => 'x', 'status' => 'PENDING',
        ]);

        try {
            app(RefundQueueService::class)->process($refund, $this->adminWith(['View:BukuTransaksi']), 'TRANSFER_BANK', 'TRF-1');
            $this->fail('Tanpa izin process_refund_queue harus ditolak');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertSame('PENDING', $refund->fresh()->status);
    }

    public function test_activity_log_link_opens_filtered_by_order(): void
    {
        $this->actingAs($this->owner);

        Livewire::withQueryParams(['cari' => 'ORD-BT-XYZ'])
            ->test(LogAktivitas::class)
            ->assertSet('tableSearch', 'ORD-BT-XYZ');
    }
}
