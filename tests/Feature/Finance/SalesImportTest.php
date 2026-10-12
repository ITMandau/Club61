<?php

namespace Tests\Feature\Finance;

use App\Filament\Pages\ImportPenjualan;
use App\Models\Finance\LedgerEntry;
use App\Models\Fnb\FnbCategory;
use App\Models\Fnb\FnbMenu;
use App\Models\Fnb\FnbStation;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\KitchenTicket;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\User;
use App\Services\Import\SalesImportFailed;
use App\Services\Import\SalesImportService;
use App\Services\Import\SalesImportTemplate;
use App\Services\Permission\Club61PermissionMatrix;
use Carbon\Carbon;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/**
 * Import penjualan soft opening dari Excel: booking padel walk-in (jadwal + akun + pembayaran) & penjualan F&B,
 * semua QRIS, langsung ke Buku Transaksi pada tanggal bayarnya — tanpa shift kasir, Midtrans, atau slip dapur.
 */
class SalesImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected PadelCourt $court1;

    protected PadelCourt $court2;

    protected FnbMenu $latte;

    protected FnbMenu $toast;

    protected function setUp(): void
    {
        parent::setUp();
        Club61PermissionMatrix::syncAllPermissions('web');
        Carbon::setTestNow(Carbon::parse('2026-10-12 10:00:00', 'Asia/Jakarta')->setTimezone(config('app.timezone')));

        $this->admin = User::factory()->superAdmin()->create(['name' => 'Owner Club61']);
        $this->court1 = PadelCourt::create(['name' => 'Court 1', 'type' => 'INDOOR', 'hourly_rate_regular' => 350000, 'hourly_rate_prime' => 450000, 'is_active' => true]);
        $this->court2 = PadelCourt::create(['name' => 'Court 2', 'type' => 'INDOOR', 'hourly_rate_regular' => 350000, 'hourly_rate_prime' => 450000, 'is_active' => true]);

        $category = FnbCategory::create(['name' => 'Coffee', 'sort_order' => 1]);
        $kitchen = FnbStation::create(['name' => 'Kitchen', 'printer_host' => '192.168.1.50']);
        $this->latte = FnbMenu::create(['category_id' => $category->id, 'name' => 'Iced Spanish Latte', 'base_price' => 38000, 'is_available' => true]);
        $this->toast = FnbMenu::create(['category_id' => $category->id, 'name' => 'Smashed Avocado Sourdough', 'base_price' => 55000, 'station_id' => $kitchen->id, 'is_available' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** File .xlsx dengan judul kolom template + baris data (array per baris, urutan kolom template). */
    protected function xlsx(array $padelRows = [], array $fnbRows = []): string
    {
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        $writer = new Writer();
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName(SalesImportService::SHEET_PADEL);
        $writer->addRow(Row::fromValues(SalesImportService::PADEL_HEADERS));
        foreach ($padelRows as $row) {
            $writer->addRow($row instanceof Row ? $row : Row::fromValues($row));
        }
        $writer->addNewSheetAndMakeItCurrent()->setName(SalesImportService::SHEET_FNB);
        $writer->addRow(Row::fromValues(SalesImportService::FNB_HEADERS));
        foreach ($fnbRows as $row) {
            $writer->addRow($row instanceof Row ? $row : Row::fromValues($row));
        }
        $writer->close();

        return $path;
    }

    /** Baris Booking Padel (kolom lain kosong). */
    /** Baris Booking Padel sesuai urutan kolom template (kolom lain kosong). */
    protected function padel(array $v): array
    {
        $titles = [
            'no' => 'No.', 'ref' => 'No. Transaksi', 'paid_at' => 'Tanggal Bayar*', 'name' => 'Nama Customer*', 'phone' => 'No. HP*',
            'email' => 'Email Customer', 'court' => 'Lapangan*', 'date' => 'Tanggal Main*', 'start' => 'Jam Mulai*',
            'duration' => 'Durasi (Jam)*', 'price' => 'Harga Sewa*', 'discount' => 'Diskon', 'tax' => 'Pajak',
            'service' => 'Biaya Layanan', 'total' => 'Total Dibayar*', 'cashier' => 'Email Kasir', 'notes' => 'Catatan',
        ];
        $byTitle = [];
        foreach ($v as $key => $value) {
            $byTitle[$titles[$key]] = $value;
        }

        return array_map(fn ($title) => $byTitle[$title] ?? '', SalesImportService::PADEL_HEADERS);
    }

    protected function fnb(array $v): array
    {
        $titles = [
            'no' => 'No.', 'ref' => 'No. Transaksi', 'paid_at' => 'Tanggal Bayar*', 'type' => 'Tipe Pesanan*', 'table' => 'No. Meja',
            'customer' => 'Nama Pelanggan', 'menu' => 'Menu*', 'qty' => 'Qty*', 'price' => 'Harga Satuan*', 'notes' => 'Catatan Item',
            'discount' => 'Diskon', 'tax' => 'Pajak', 'service' => 'Biaya Layanan', 'total' => 'Total Dibayar*', 'cashier' => 'Email Kasir',
        ];
        $byTitle = [];
        foreach ($v as $key => $value) {
            $byTitle[$titles[$key]] = $value;
        }

        return array_map(fn ($title) => $byTitle[$title] ?? '', SalesImportService::FNB_HEADERS);
    }

    protected function service(): SalesImportService
    {
        return app(SalesImportService::class);
    }

    public function test_template_has_the_import_sheets_and_reference_lists(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        app(SalesImportTemplate::class)->build($path);

        $reader = new \OpenSpout\Reader\XLSX\Reader();
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

        $this->assertSame(['Petunjuk', 'Booking Padel', 'Penjualan F&B', 'Lapangan & Menu'], array_keys($sheets));
        $this->assertSame(SalesImportService::PADEL_HEADERS, $sheets['Booking Padel'][0]);
        $this->assertSame(SalesImportService::FNB_HEADERS, $sheets['Penjualan F&B'][0]);
        $this->assertContains('Court 1', array_column($sheets['Lapangan & Menu'], 0));
        $this->assertContains('Iced Spanish Latte', array_column($sheets['Lapangan & Menu'], 5));
        $court1 = collect($sheets['Lapangan & Menu'])->first(fn ($r) => ($r[0] ?? null) === 'Court 1');
        $this->assertSame([350000, 450000], [$court1[2], $court1[3]], 'tarif reguler & prime time per jam ikut ditampilkan');

        // Sheet data: kolom No. urut terisi, tanpa kolom Metode Bayar / Ref QRIS.
        $this->assertSame('No.', $sheets['Booking Padel'][0][0]);
        $this->assertSame(1, $sheets['Booking Padel'][1][0]);
        $this->assertNotContains('Ref QRIS', $sheets['Penjualan F&B'][0]);

        // Template kosong yang langsung di-upload: tidak ada data, tidak ada yang ter-import (contoh ada di Petunjuk).
        $analysis = $this->service()->analyze($path);
        $this->assertSame([], $analysis['padel']);
        $this->assertSame([], $analysis['fnb']);
        $this->assertStringContainsString('Tidak ada data', $analysis['errors'][0]['message']);
    }

    public function test_padel_bookings_are_imported_with_account_schedule_payment_and_ledger(): void
    {
        $existing = User::factory()->customer()->create(['name' => 'Sinta Lama', 'phone' => '081299990000']);
        $path = $this->xlsx([
            // Transaksi 1: dua lapangan, sudah dimainkan, pajak 10%.
            $this->padel(['ref' => 'SO-0001', 'paid_at' => '2026-10-05 14:30', 'name' => 'Budi Santoso', 'phone' => '0812-3456-7890', 'court' => 'court 1', 'date' => '2026-10-05', 'start' => '15:00', 'duration' => '2', 'price' => '700.000', 'tax' => '105000', 'total' => 'Rp 1.155.000']),
            $this->padel(['ref' => 'SO-0001', 'court' => 'Court 2', 'date' => '2026-10-05', 'start' => '15:00', 'duration' => '1', 'price' => '350000', 'notes' => 'Bawa raket sendiri']),
            // Transaksi 2: dibayar saat soft opening, mainnya nanti → booking aktif. Customer lama (cocok No. HP).
            $this->padel(['ref' => 'SO-0002', 'paid_at' => '2026-10-06', 'name' => 'Sinta', 'phone' => '+62 812-9999-0000', 'court' => 'Court 1', 'date' => '2026-10-20', 'start' => '19:00', 'duration' => '2', 'price' => '900000', 'discount' => '100000', 'total' => '800000']),
        ]);

        $analysis = $this->service()->analyze($path);
        $this->assertSame([], $analysis['errors']);
        $this->assertSame(2, $analysis['summary']['padel_transactions']);
        $this->assertSame(3, $analysis['summary']['padel_bookings']);
        $this->assertSame(1, $analysis['summary']['new_customers']);

        $this->service()->commit($path, $this->admin);

        // Transaksi 1
        $order = Order::where('order_number', 'IMP-PAD-SO-0001')->sole();
        $this->assertSame('WALK_IN', $order->order_type);
        $this->assertSame('PAID', $order->payment_status);
        $this->assertEquals(1050000, (float) $order->subtotal);
        $this->assertEquals(105000, (float) $order->tax_amount);
        $this->assertEquals(1155000, (float) $order->grand_total);
        $this->assertSame($this->admin->id, $order->cashier_id);
        $this->assertSame('2026-10-05 14:30', $order->created_at->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'));

        $budi = User::where('phone', '081234567890')->sole();
        $this->assertSame('Budi Santoso', $budi->name);
        $this->assertSame('WALK_IN', $budi->registration_source);
        $this->assertTrue(Hash::check('567890', $budi->password), 'akun walk-in: password = 6 digit terakhir No. HP');

        $bookings = PadelBooking::where('order_id', $order->id)->orderBy('court_id')->get();
        $this->assertCount(2, $bookings);
        $this->assertTrue($bookings->every(fn ($b) => $b->status === 'COMPLETED' && $b->user_id === $budi->id && $b->qr_code_hash));
        $this->assertSame(['15:00', '17:00'], [$bookings->firstWhere('court_id', $this->court1->id)->start_time->format('H:i'), $bookings->firstWhere('court_id', $this->court1->id)->end_time->format('H:i')]);
        $this->assertSame(['PADEL', 'PADEL'], $order->items->pluck('item_type')->all());

        $payment = Payment::where('order_id', $order->id)->sole();
        $this->assertSame(['SUCCESS', 'QRIS', 'CASHIER_POS'], [$payment->status, $payment->payment_method, $payment->payment_gateway]);
        $this->assertNull($payment->pos_shift_id);
        $this->assertSame('IMPORT_EXCEL', $payment->payload_log['source']);
        $this->assertSame('QRIS', $payment->payload_log['payment_method']);
        $this->assertSame(['Bawa raket sendiri'], $payment->payload_log['notes']);
        $this->assertSame('2026-10-05 14:30', $payment->paid_at->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'));

        $ledger = LedgerEntry::where('order_id', $order->id)->get();
        $this->assertEquals(1155000, (float) $ledger->sum('total_amount'));
        $this->assertEquals(105000, (float) $ledger->sum('tax_amount'));
        $this->assertSame(['POS_WALKIN_PADEL'], $ledger->pluck('source')->unique()->values()->all());
        $this->assertSame('2026-10-05', $ledger->first()->occurred_at->setTimezone('Asia/Jakarta')->toDateString(), 'masuk buku pada tanggal bayar asli');

        // Transaksi 2: akun lama dipakai, booking aktif mengunci slot, tanggal bayar tanpa jam → 12:00 WIB.
        $order2 = Order::where('order_number', 'IMP-PAD-SO-0002')->sole();
        $this->assertSame($existing->id, $order2->user_id);
        $this->assertEquals(100000, (float) $order2->discount_amount);
        $this->assertSame('PAID', PadelBooking::where('order_id', $order2->id)->sole()->status);
        $this->assertSame('2026-10-06 12:00', Payment::where('order_id', $order2->id)->sole()->paid_at->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'));
        $this->assertSame(1, User::where('phone', '081299990000')->count(), 'tidak membuat akun ganda');
    }

    public function test_fnb_sales_are_imported_without_kitchen_slips(): void
    {
        $cashier = User::factory()->cashier()->create(['email' => 'kasir.fnb@club61.id', 'name' => 'Kasir FnB']);
        $path = $this->xlsx([], [
            $this->fnb(['ref' => 'FB-0001', 'paid_at' => '2026-10-05 15:10', 'type' => 'Dine-In', 'table' => '7', 'customer' => 'Budi', 'menu' => 'iced spanish latte', 'qty' => '2', 'price' => '38000', 'notes' => 'Less sugar', 'total' => '131000', 'cashier' => 'kasir.fnb@club61.id']),
            $this->fnb(['ref' => 'FB-0001', 'menu' => 'Smashed Avocado Sourdough', 'qty' => '1', 'price' => '55000']),
            $this->fnb(['ref' => 'FB-0002', 'paid_at' => '2026-10-05 16:00', 'type' => 'Bawa Pulang', 'table' => '9', 'menu' => 'Iced Spanish Latte', 'qty' => '1', 'price' => '35000', 'total' => '35000']),
        ]);

        $this->service()->commit($path, $this->admin);

        $order = Order::where('order_number', 'IMP-FNB-FB-0001')->sole();
        $this->assertSame(['DINE_IN', '7', 'Budi', 'PAID'], [$order->order_type, $order->table_number, $order->customer_name, $order->payment_status]);
        $this->assertSame($cashier->id, $order->cashier_id);
        $this->assertNull($order->user_id);
        $latteLine = $order->items->firstWhere('reference_id', $this->latte->id);
        $this->assertSame(['FNB', 2, 'Less sugar'], [$latteLine->item_type, (int) $latteLine->quantity, $latteLine->notes]);
        $this->assertEquals(76000, (float) $latteLine->subtotal);

        $takeAway = Order::where('order_number', 'IMP-FNB-FB-0002')->sole();
        $this->assertSame('TAKE_AWAY', $takeAway->order_type);
        $this->assertNull($takeAway->table_number, 'bawa pulang tidak punya meja');
        $this->assertEquals(35000, (float) $takeAway->items->sole()->unit_price, 'harga sesuai struk asli, bukan harga menu sekarang');

        $this->assertSame(0, KitchenTicket::count(), 'data lampau tidak mengirim slip ke dapur');
        $this->assertSame(['POS_FNB'], LedgerEntry::whereIn('order_id', [$order->id, $takeAway->id])->pluck('source')->unique()->values()->all());
        $this->assertSame('Kasir FnB', LedgerEntry::where('order_id', $order->id)->value('cashier_name'));
        $this->assertEquals(166000, (float) LedgerEntry::sum('total_amount'));
    }

    public function test_every_problem_is_reported_per_row_and_nothing_is_written(): void
    {
        PadelBooking::create([
            'booking_code' => 'BK-EXIST', 'user_id' => $this->admin->id, 'court_id' => $this->court2->id, 'booking_date' => '2026-10-07',
            'start_time' => Carbon::parse('2026-10-07 18:00:00'), 'end_time' => Carbon::parse('2026-10-07 20:00:00'),
            'court_fee' => 700000, 'total_amount' => 700000, 'status' => 'PAID',
        ]);

        $path = $this->xlsx([
            $this->padel(['ref' => 'P-1', 'paid_at' => '2026-10-05 10:00', 'name' => 'A', 'phone' => '081200000001', 'court' => 'Court 9', 'date' => '2026-10-05', 'start' => '10:00', 'duration' => '1', 'price' => '350000', 'total' => '350000']),
            $this->padel(['ref' => 'P-2', 'paid_at' => '2026-10-05 10:00', 'name' => 'B', 'phone' => '081200000002', 'court' => 'Court 1', 'date' => '2026-10-05', 'start' => '10:30', 'duration' => '1', 'price' => '350000', 'total' => '350000']),
            $this->padel(['ref' => 'P-3', 'paid_at' => '2026-10-05 10:00', 'name' => 'C', 'phone' => '081200000003', 'court' => 'Court 1', 'date' => '2026-10-05', 'start' => '12:00', 'duration' => '1', 'price' => '350000', 'tax' => '10000', 'total' => '350000']),
            $this->padel(['ref' => 'P-4', 'paid_at' => '2026-10-05 10:00', 'name' => 'D', 'phone' => '081200000004', 'court' => 'Court 1', 'date' => '2026-10-05', 'start' => '14:00', 'duration' => '1', 'price' => '350000', 'total' => '350000']),
            $this->padel(['ref' => 'P-5', 'paid_at' => '2026-10-07 10:00', 'name' => 'E', 'phone' => '081200000005', 'court' => 'Court 2', 'date' => '2026-10-07', 'start' => '19:00', 'duration' => '2', 'price' => '700000', 'total' => '700000']),
            $this->padel(['ref' => 'P-6', 'paid_at' => '2026-10-05 10:00', 'name' => 'F', 'phone' => '081200000006', 'court' => 'Court 1', 'date' => '2026-10-08', 'start' => '08:00', 'duration' => '2', 'price' => '700000', 'total' => '700000']),
            $this->padel(['ref' => 'P-7', 'paid_at' => '2026-10-05 10:00', 'name' => 'G', 'phone' => '081200000007', 'court' => 'Court 1', 'date' => '2026-10-08', 'start' => '09:00', 'duration' => '2', 'price' => '700000', 'total' => '700000']),
            $this->padel(['ref' => 'P-8', 'paid_at' => '2026-10-05 10:00', 'name' => 'H', 'phone' => '081200000008', 'court' => 'Court 2', 'date' => '2026-10-09', 'start' => '08:00', 'duration' => '1', 'price' => '350000', 'total' => '700000']),
            $this->padel(['ref' => 'P-8', 'paid_at' => '2026-10-06 10:00', 'court' => 'Court 2', 'date' => '2026-10-09', 'start' => '09:00', 'duration' => '1', 'price' => '350000']),
            $this->padel(['ref' => 'P-9', 'paid_at' => '2026-12-01 10:00', 'name' => 'I', 'phone' => '0812', 'court' => 'Court 2', 'date' => '2026-10-10', 'start' => '08:00', 'duration' => '1', 'price' => '350000', 'total' => '350000', 'cashier' => 'bukan@staf.id']),
        ], [
            $this->fnb(['ref' => 'F-1', 'paid_at' => '2026-10-05 10:00', 'type' => 'Delivery', 'menu' => 'Nasi Goreng', 'qty' => '0', 'price' => '25000', 'total' => '25000']),
        ]);

        $messages = collect($this->service()->analyze($path)['errors'])->map(fn ($e) => ($e['ref'] ?? '-').': '.$e['message']);

        $expect = [
            'P-1: Lapangan "Court 9" tidak ada di sistem',
            'P-2: Jam Mulai harus jam penuh',
            'P-3: Total Dibayar Rp 350.000 tidak sama dengan hitungan Rp 360.000',
            'P-5: Court 2 2026-10-07 19:00–21:00 sudah terisi booking BK-EXIST',
            'P-7: Court 1 2026-10-08 09:00–11:00 bentrok dengan No. 6',
            'P-8: Kolom "Tanggal Bayar" beda dengan baris pertama',
            'P-9: Tanggal Bayar tidak boleh di masa depan',
            'P-9: No. HP tidak valid',
            'P-9: Email Kasir "bukan@staf.id" bukan akun staf',
            'F-1: Tipe Pesanan harus "Dine-In" atau "Bawa Pulang"',
            'F-1: Menu "Nasi Goreng" tidak ada di sistem',
            'F-1: Qty harus angka 1–999',
        ];
        foreach ($expect as $needle) {
            $this->assertTrue($messages->contains(fn ($m) => str_contains($m, $needle)), "Error tidak ditemukan: {$needle}\n".$messages->implode("\n"));
        }

        try {
            $this->service()->commit($path, $this->admin);
            $this->fail('Import dengan error harus dibatalkan.');
        } catch (SalesImportFailed $e) {
            $this->assertNotEmpty($e->errors);
        }
        $this->assertSame(0, Order::where('order_number', 'like', 'IMP-%')->count());
        $this->assertSame(1, PadelBooking::count());
        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_reimporting_the_same_file_skips_transactions_already_imported(): void
    {
        $path = $this->xlsx(
            [$this->padel(['ref' => 'SO-1', 'paid_at' => '2026-10-05 14:30', 'name' => 'Budi', 'phone' => '081234567890', 'court' => 'Court 1', 'date' => '2026-10-05', 'start' => '15:00', 'duration' => '1', 'price' => '350000', 'total' => '350000'])],
            [$this->fnb(['ref' => 'FB-1', 'paid_at' => '2026-10-05 15:10', 'type' => 'Dine-In', 'menu' => 'Iced Spanish Latte', 'qty' => '1', 'price' => '38000', 'total' => '38000'])],
        );

        $this->service()->commit($path, $this->admin);
        $second = $this->service()->commit($path, $this->admin);

        $this->assertSame(0, $second['summary']['padel_transactions']);
        $this->assertSame(0, $second['summary']['fnb_transactions']);
        $this->assertSame(2, $second['summary']['skipped']);
        $this->assertSame(2, Order::count());
        $this->assertSame(1, PadelBooking::count());
        $this->assertEquals(388000, (float) LedgerEntry::sum('total_amount'));
    }

    public function test_excel_date_time_and_number_cells_are_understood(): void
    {
        $date = (new Style())->setFormat('yyyy-mm-dd hh:mm');
        $day = (new Style())->setFormat('yyyy-mm-dd');
        $time = (new Style())->setFormat('hh:mm');
        $row = new Row([
            Cell::fromValue(1),
            Cell::fromValue('SO-77'),
            Cell::fromValue(new DateTimeImmutable('2026-10-05 14:30:00'), $date),
            Cell::fromValue('Rina'),
            Cell::fromValue(81277788899),
            Cell::fromValue(''),
            Cell::fromValue('Court 2'),
            Cell::fromValue(new DateTimeImmutable('2026-10-05 00:00:00'), $day),
            Cell::fromValue(new DateTimeImmutable('1899-12-30 08:00:00'), $time),
            Cell::fromValue(2),
            Cell::fromValue(700000),
            Cell::fromValue(''), Cell::fromValue(''), Cell::fromValue(''),
            Cell::fromValue(700000.0),
        ]);

        $analysis = $this->service()->analyze($this->xlsx([$row]));

        $this->assertSame([], $analysis['errors']);
        $tx = $analysis['padel'][0];
        $this->assertSame('2026-10-05 14:30', $tx['paid_at']->copy()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'));
        $this->assertSame(['2026-10-05', '08:00', '10:00'], [$tx['bookings'][0]['play_date'], $tx['bookings'][0]['start'], $tx['bookings'][0]['end']]);
        $this->assertSame('081277788899', $tx['phone'], 'angka 0 di depan yang dibuang Excel dikembalikan');
    }

    public function test_only_super_admin_can_open_the_import_page(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->assertFalse(ImportPenjualan::canAccess());

        $this->actingAs(User::factory()->cashier()->create());
        $this->assertFalse(ImportPenjualan::canAccess());

        $this->actingAs($this->admin);
        $this->assertTrue(ImportPenjualan::canAccess());

        // Terdaftar di panel admin: muncul di menu Keuangan dan URL-nya bisa dibuka.
        $this->get('/admin/import-penjualan')->assertOk()->assertSee('Import Penjualan');
        $this->get('/admin/buku-transaksi')->assertOk()->assertSee('/admin/import-penjualan', false);
    }

    public function test_page_previews_then_imports_the_uploaded_file(): void
    {
        $this->actingAs($this->admin);
        $path = $this->xlsx(
            [$this->padel(['ref' => 'SO-1', 'paid_at' => '2026-10-05 14:30', 'name' => 'Budi', 'phone' => '081234567890', 'court' => 'Court 1', 'date' => '2026-10-05', 'start' => '15:00', 'duration' => '1', 'price' => '350000', 'total' => '350000'])],
            [$this->fnb(['ref' => 'FB-1', 'paid_at' => '2026-10-05 15:10', 'type' => 'Dine-In', 'menu' => 'Iced Spanish Latte', 'qty' => '1', 'price' => '38000', 'total' => '38000'])],
        );
        $upload = UploadedFile::fake()->createWithContent('penjualan.xlsx', file_get_contents($path));

        Livewire::test(ImportPenjualan::class)
            ->assertSee('Unduh Template Excel')
            ->set('file', $upload)
            ->assertHasNoErrors()
            ->assertSet('preview.summary.padel_transactions', 1)
            ->assertSet('preview.summary.fnb_transactions', 1)
            ->assertSet('preview.error_count', 0)
            ->assertSee('Court 1 · 05/10/2026 15:00–16:00')
            ->call('import')
            ->assertSet('result.padel_transactions', 1)
            ->assertSee('Import berhasil');

        $this->assertSame(2, Order::where('order_number', 'like', 'IMP-%')->count());
        $this->assertEquals(388000, (float) LedgerEntry::sum('total_amount'));
    }

    public function test_page_rejects_non_excel_upload(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ImportPenjualan::class)
            ->set('file', UploadedFile::fake()->createWithContent('data.csv', "a,b\n1,2"))
            ->assertHasErrors(['file' => 'mimes'])
            ->assertSet('preview', null);
    }

    public function test_template_can_be_downloaded_from_the_page(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ImportPenjualan::class)
            ->call('downloadTemplate')
            ->assertFileDownloaded('Template-Import-Penjualan-Club61.xlsx');
    }

    public function test_imported_upcoming_booking_blocks_the_slot_for_cashier_and_online(): void
    {
        $path = $this->xlsx([
            $this->padel(['ref' => 'SO-9', 'paid_at' => '2026-10-06 10:00', 'name' => 'Budi', 'phone' => '081234567890', 'court' => 'Court 1', 'date' => '2026-10-20', 'start' => '19:00', 'duration' => '2', 'price' => '900000', 'total' => '900000']),
        ]);
        $this->service()->commit($path, $this->admin);

        $this->expectException(\App\Exceptions\SlotConflictException::class);
        app(\App\Services\Padel\PadelBookingService::class)->holdBatchSlots(
            [['court_id' => $this->court1->id, 'start_time' => '20:00', 'end_time' => '21:00']],
            '2026-10-20',
            User::factory()->customer()->create(),
        );
    }

    public function test_blank_transaction_number_is_generated_and_rows_without_payment_date_join_the_row_above(): void
    {
        $path = $this->xlsx([
            // Satu pembayaran: Court 1 3 jam + Court 2 1 jam (baris kedua Tanggal Bayar kosong → ikut atas).
            $this->padel(['paid_at' => '2026-10-05 14:30', 'name' => 'Budi', 'phone' => '081234567890', 'court' => 'Court 1', 'date' => '2026-10-05', 'start' => '15:00', 'duration' => '3 jam', 'price' => '1050000', 'total' => '1400000']),
            $this->padel(['court' => 'Court 2', 'date' => '2026-10-05', 'start' => '15:00', 'duration' => '1', 'price' => '350000']),
            // Transaksi berikutnya (Tanggal Bayar terisi lagi).
            $this->padel(['paid_at' => '2026-10-05 18:00', 'name' => 'Rina', 'phone' => '081277700000', 'court' => 'Court 1', 'date' => '2026-10-05', 'start' => '19:00', 'duration' => '2', 'price' => '700000', 'total' => '700000']),
        ], [
            $this->fnb(['paid_at' => '2026-10-05 15:10', 'type' => 'Dine-In', 'menu' => 'Iced Spanish Latte', 'qty' => '2', 'price' => '38000', 'total' => '131000']),
            $this->fnb(['menu' => 'Smashed Avocado Sourdough', 'qty' => '1', 'price' => '55000']),
        ]);

        $analysis = $this->service()->analyze($path);
        $this->assertSame([], $analysis['errors']);
        $this->assertSame([2, 1], array_map(fn ($tx) => count($tx['bookings']), $analysis['padel']));
        $this->assertNull($analysis['padel'][0]['ref']);

        $this->service()->commit($path, $this->admin);

        $orders = Order::where('order_number', 'like', 'IMP-PAD-%')->orderBy('created_at')->get();
        $this->assertCount(2, $orders);
        $this->assertMatchesRegularExpression('/^IMP-PAD-261005-[A-Z0-9]{6}$/', $orders[0]->order_number);
        $this->assertEquals(1400000, (float) $orders[0]->grand_total);

        // Booking 3 jam = SATU booking 15:00–18:00, bukan 3 baris per jam.
        $long = PadelBooking::where('order_id', $orders[0]->id)->where('court_id', $this->court1->id)->sole();
        $this->assertSame(['15:00', '18:00'], [$long->start_time->format('H:i'), $long->end_time->format('H:i')]);
        $this->assertEquals(1050000, (float) $long->court_fee);

        $fnb = Order::where('order_number', 'like', 'IMP-FNB-%')->sole();
        $this->assertCount(2, $fnb->items);

        // Upload ulang file yang sama (tanpa No. Transaksi): dikenali dari isinya, tidak masuk dua kali.
        $again = $this->service()->analyze($path);
        $this->assertSame([], $again['errors']);
        $this->assertSame(3, $again['summary']['skipped']);
        $this->service()->commit($path, $this->admin);
        $this->assertSame(3, Order::count());
        $this->assertSame(3, PadelBooking::count());
    }

    public function test_identical_rows_without_numbers_and_bookings_past_midnight_are_reported(): void
    {
        $row = ['paid_at' => '2026-10-05 15:10', 'type' => 'Bawa Pulang', 'menu' => 'Iced Spanish Latte', 'qty' => '1', 'price' => '38000', 'total' => '38000'];
        $path = $this->xlsx([
            $this->padel(['paid_at' => '2026-10-05 20:00', 'name' => 'Budi', 'phone' => '081234567890', 'court' => 'Court 1', 'date' => '2026-10-05', 'start' => '22:00', 'duration' => '3', 'price' => '1050000', 'total' => '1050000']),
            $this->padel(['court' => 'Court 2', 'date' => '2026-10-05', 'start' => '20:00', 'price' => '350000']),
        ], [$this->fnb($row), $this->fnb($row)]);

        $messages = collect($this->service()->analyze($path)['errors'])->pluck('message');

        $this->assertTrue($messages->contains(fn ($m) => str_contains($m, 'melewati jam 24:00')));
        $this->assertTrue($messages->contains(fn ($m) => str_contains($m, 'Durasi (Jam) wajib diisi')));
        $this->assertTrue($messages->contains(fn ($m) => str_contains($m, 'Sama persis dengan transaksi No. 1')));
    }

    public function test_file_from_the_old_template_with_end_time_column_still_works(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        $writer = new Writer();
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName(SalesImportService::SHEET_PADEL);
        $writer->addRow(Row::fromValues(['No. Transaksi*', 'Tanggal Bayar*', 'Nama Customer*', 'No. HP*', 'Email Customer', 'Lapangan*', 'Tanggal Main*', 'Jam Mulai*', 'Jam Selesai*', 'Harga Sewa*', 'Diskon', 'Pajak', 'Biaya Layanan', 'Total Dibayar*']));
        $writer->addRow(Row::fromValues(['SO-1', '2026-10-05 14:30', 'Budi', '081234567890', '', 'Court 1', '2026-10-05', '15:00', '17:00', '700000', '', '', '', '700000']));
        $writer->close();

        $analysis = $this->service()->analyze($path);

        $this->assertSame([], $analysis['errors']);
        $this->assertSame(['15:00', '17:00'], [$analysis['padel'][0]['bookings'][0]['start'], $analysis['padel'][0]['bookings'][0]['end']]);
    }

    public function test_phone_owned_by_an_account_with_another_name_needs_confirmation_before_import(): void
    {
        $andi = User::factory()->customer()->create(['name' => 'Andi Wijaya (Customer)', 'phone' => '081234567890']);
        $path = $this->xlsx([
            $this->padel(['paid_at' => '2026-10-05 14:30', 'name' => 'Budi', 'phone' => '081234567890', 'court' => 'Court 1', 'date' => '2026-10-05', 'start' => '15:00', 'duration' => '1', 'price' => '350000', 'total' => '350000']),
            // Nama sama dengan akun (tanpa "(Customer)") → bukan peringatan.
            $this->padel(['paid_at' => '2026-10-05 16:30', 'name' => 'andi wijaya', 'phone' => '081234567890', 'court' => 'Court 2', 'date' => '2026-10-05', 'start' => '17:00', 'duration' => '1', 'price' => '350000', 'total' => '350000']),
            // Nomor baru dipakai dua nama berbeda di file.
            $this->padel(['paid_at' => '2026-10-05 18:00', 'name' => 'Rina', 'phone' => '081277700000', 'court' => 'Court 1', 'date' => '2026-10-05', 'start' => '19:00', 'duration' => '1', 'price' => '350000', 'total' => '350000']),
            $this->padel(['paid_at' => '2026-10-05 19:00', 'name' => 'Doni', 'phone' => '081277700000', 'court' => 'Court 2', 'date' => '2026-10-05', 'start' => '20:00', 'duration' => '1', 'price' => '350000', 'total' => '350000']),
        ]);

        $warnings = collect($this->service()->analyze($path)['warnings'])->pluck('message');
        $this->assertCount(2, $warnings);
        $this->assertStringContainsString('sudah dipakai akun "Andi Wijaya (Customer)" — booking masuk ke akun itu, bukan atas nama "Budi"', $warnings[0]);
        $this->assertStringContainsString('juga dipakai No. 3 atas nama "Rina"', $warnings[1]);

        $this->actingAs($this->admin);
        $page = Livewire::test(ImportPenjualan::class)
            ->set('file', UploadedFile::fake()->createWithContent('penjualan.xlsx', file_get_contents($path)))
            ->assertSet('preview.warning_count', 2)
            ->assertSee('Perlu Dicek Sebelum Import')
            ->call('import');
        $this->assertSame(0, Order::count(), 'belum dicentang → belum import');

        $page->set('confirmWarnings', true)->call('import')->assertSet('result.padel_transactions', 4);
        $order = Order::whereHas('padelBookings', fn ($q) => $q->where('start_time', Carbon::parse('2026-10-05 15:00:00')))->sole();
        $this->assertSame($andi->id, $order->user_id);
        $this->assertSame('Budi', Payment::where('order_id', $order->id)->sole()->payload_log['excel_customer_name'], 'nama di Excel tetap tercatat');
    }

    public function test_import_is_kept_in_history_with_the_original_file(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $path = $this->xlsx(
            [$this->padel(['paid_at' => '2026-10-05 14:30', 'name' => 'Budi', 'phone' => '081234567890', 'court' => 'Court 1', 'date' => '2026-10-05', 'start' => '15:00', 'duration' => '2', 'price' => '700000', 'total' => '700000'])],
            [$this->fnb(['paid_at' => '2026-10-05 15:10', 'type' => 'Dine-In', 'menu' => 'Iced Spanish Latte', 'qty' => '1', 'price' => '38000', 'total' => '38000'])],
        );

        $result = $this->service()->commit($path, $this->admin, 'soft-opening.xlsx');

        $batch = \App\Models\Finance\SalesImportBatch::sole();
        $this->assertSame($result['batch'], $batch->batch_code);
        $this->assertSame(['soft-opening.xlsx', 1, 1, 1, 'IMPORTED'], [$batch->file_name, $batch->padel_transactions, $batch->padel_bookings, $batch->fnb_transactions, $batch->status]);
        $this->assertEquals(700000, (float) $batch->padel_total);
        $this->assertCount(1, $batch->new_customer_ids);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($batch->file_path);

        $this->actingAs($this->admin);
        Livewire::test(ImportPenjualan::class)
            ->assertSee($batch->batch_code)
            ->assertSee('soft-opening.xlsx')
            ->call('downloadBatchFile', $batch->id)
            ->assertFileDownloaded('soft-opening.xlsx');
    }

    public function test_cancelling_an_import_removes_its_data_and_frees_the_schedule(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $andi = User::factory()->customer()->create(['name' => 'Andi', 'phone' => '081234567890']);
        $path = $this->xlsx(
            [
                $this->padel(['paid_at' => '2026-10-05 14:30', 'name' => 'Andi', 'phone' => '081234567890', 'court' => 'Court 1', 'date' => '2026-10-05', 'start' => '15:00', 'duration' => '2', 'price' => '700000', 'total' => '700000']),
                $this->padel(['paid_at' => '2026-10-06 10:00', 'name' => 'Rina Baru', 'phone' => '081277700000', 'court' => 'Court 1', 'date' => '2026-10-20', 'start' => '19:00', 'duration' => '2', 'price' => '700000', 'total' => '700000']),
            ],
            [$this->fnb(['paid_at' => '2026-10-05 15:10', 'type' => 'Dine-In', 'menu' => 'Iced Spanish Latte', 'qty' => '1', 'price' => '38000', 'total' => '38000'])],
        );
        $this->service()->commit($path, $this->admin, 'salah.xlsx');
        $batch = \App\Models\Finance\SalesImportBatch::sole();
        // Data lain di luar import tidak boleh ikut terhapus.
        $other = Order::create(['order_number' => 'ORD-LAIN', 'user_id' => $andi->id, 'order_type' => 'WALK_IN', 'subtotal' => 1, 'grand_total' => 1, 'payment_status' => 'PAID']);

        $this->actingAs($this->admin);
        Livewire::test(ImportPenjualan::class)->call('cancelBatch', $batch->id);

        $this->assertSame('CANCELLED', $batch->fresh()->status);
        $this->assertSame($this->admin->id, $batch->fresh()->cancelled_by_id);
        $this->assertSame(['ORD-LAIN'], Order::withTrashed()->pluck('order_number')->all());
        $this->assertSame(0, PadelBooking::withTrashed()->count());
        $this->assertSame(0, Payment::count());
        $this->assertSame(0, LedgerEntry::count());
        $this->assertNull(User::withTrashed()->where('phone', '081277700000')->first(), 'akun baru dari import ikut dihapus');
        $this->assertNotNull($andi->fresh(), 'akun lama tidak dihapus');
        $this->assertSame(1, \App\Models\Audit\ActivityLog::where('event', 'sales.import_cancelled')->count());

        // Slot kosong lagi & file yang sudah dibetulkan bisa di-import ulang (tidak dianggap sudah pernah di-import).
        $again = $this->service()->commit($path, $this->admin);
        $this->assertSame(2, $again['summary']['padel_transactions']);
    }

    public function test_import_already_used_in_operations_cannot_be_cancelled(): void
    {
        $path = $this->xlsx([
            $this->padel(['paid_at' => '2026-10-06 10:00', 'name' => 'Rina', 'phone' => '081277700000', 'court' => 'Court 1', 'date' => '2026-10-20', 'start' => '19:00', 'duration' => '2', 'price' => '700000', 'total' => '700000']),
        ]);
        $this->service()->commit($path, $this->admin);
        PadelBooking::query()->update(['status' => 'CHECKED_IN', 'checked_in_at' => now()]);
        $batch = \App\Models\Finance\SalesImportBatch::sole();

        try {
            $this->service()->cancelBatch($batch, $this->admin);
            $this->fail('Import yang bookingnya sudah check-in tidak boleh dibatalkan.');
        } catch (\App\Services\Import\SalesImportCancelBlocked $e) {
            $this->assertStringContainsString('sudah check-in', $e->reasons[0]);
        }

        $this->assertSame('IMPORTED', $batch->fresh()->status);
        $this->assertSame(1, Order::count());
        $this->assertSame(1, LedgerEntry::count());
    }
}
