<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HandlesFnbErrors;
use App\Models\Fnb\FnbCategory;
use App\Models\Fnb\FnbMenu;
use App\Models\Fnb\FnbStockMovement;
use App\Models\Fnb\FnbStockReceipt;
use App\Services\Finance\LedgerReport;
use App\Services\Fnb\FnbReceiptImport;
use App\Services\Fnb\FnbReportExport;
use App\Services\Fnb\FnbReportService;
use App\Services\Fnb\FnbStockService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Modul 29 — Stok F&B (mini inventory): daftar persediaan per menu, Penerimaan Barang (banyak menu per nota, input
 * manual atau impor Excel), Penyesuaian Stok, Stok Opname, Kartu Stok, Riwayat Stok. HPP & Lacak Persediaan diatur di
 * data menu (Kelola Menu F&B). Semua perubahan stok lewat FnbStockService (Kartu Stok + Log Aktivitas).
 */
class StokFnb extends Page
{
    use HandlesFnbErrors;
    use WithFileUploads;
    use WithPagination;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationLabel = 'Stok F&B';

    protected static string|UnitEnum|null $navigationGroup = 'F&B';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Stok F&B';

    protected string $view = 'filament.pages.stok-fnb';

    public const STATUS_FILTERS = ['ALL' => 'Semua Menu', 'TRACKED' => 'Persediaan Dilacak', 'ALERT' => 'Menipis / Habis / Minus', 'UNTRACKED' => 'Tidak Dilacak'];

    public string $tab = 'stock'; // stock, receipts, history

    public string $search = '';

    public string $categoryFilter = 'ALL';

    public string $statusFilter = 'ALL';

    // Riwayat Stok & daftar Penerimaan Barang
    public string $historyType = 'ALL';

    public string $historyPreset = 'bulan_ini';

    public ?string $historyFrom = null;

    public ?string $historyUntil = null;

    // Modal aktif: receipt, import, adjust, opname, card, receipt-detail
    public ?string $modal = null;

    public ?string $menuId = null;

    /**
     * Baris form banyak menu. Penerimaan Barang: menu_id, qty, price. Penyesuaian Stok: menu_id, direction, qty, reason.
     *
     * @var list<array<string, mixed>>
     */
    public array $lines = [];

    public string $supplier = '';

    public string $invoiceNumber = '';

    public string $notes = '';

    public $importFile = null;

    /** Impor: HPP menu diganti harga beli per satuan terbaru (butuh izin manage_fnb_hpp). */
    public bool $updateHpp = false;

    /** @var array<string, int|string|null> menu_id => hasil perhitungan fisik */
    public array $counts = [];

    /** @var array<string, string|null> menu_id => catatan per menu saat Stok Opname */
    public array $countNotes = [];

    public ?string $receiptId = null;

    public ?string $voidReceiptId = null;

    public string $voidReason = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('View:StokFnb');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'categoryFilter', 'statusFilter', 'historyType', 'historyPreset', 'historyFrom', 'historyUntil'], true)) {
            $this->resetPage();
        }
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['stock', 'receipts', 'history'], true) ? $tab : 'stock';
        $this->resetPage();
    }

    public function openModal(string $modal, ?string $menuId = null): void
    {
        // Kartu Stok & rincian penerimaan cukup izin halaman; aksi lain butuh izin masing-masing.
        $permissions = ['receipt' => 'restock_fnb_stock', 'import' => 'restock_fnb_stock', 'adjust' => 'adjust_fnb_stock', 'opname' => 'opname_fnb_stock', 'card' => 'View:StokFnb'];
        if (! array_key_exists($modal, $permissions) || ! auth()->user()->can($permissions[$modal])) {
            Notification::make()->title('Akses ditolak')->body('Anda tidak memiliki izin untuk tindakan ini.')->danger()->send();

            return;
        }

        $this->resetErrorBag();
        $this->reset(['supplier', 'invoiceNumber', 'notes', 'counts', 'countNotes', 'importFile', 'updateHpp', 'voidReceiptId', 'voidReason']);
        $this->menuId = $menuId;
        $this->modal = $modal;
        $this->lines = [$this->blankLine()];
    }

    public function closeModal(): void
    {
        $this->modal = null;
        $this->menuId = null;
        $this->receiptId = null;
        $this->importFile = null;
    }

    public function addLine(): void
    {
        if (count($this->lines) < FnbStockService::MAX_RECEIPT_LINES) {
            $this->lines[] = $this->blankLine();
        }
    }

    private function blankLine(): array
    {
        return $this->modal === 'adjust'
            ? ['menu_id' => '', 'direction' => 'OUT', 'qty' => null, 'reason' => 'DAMAGED']
            : ['menu_id' => '', 'qty' => null, 'price' => null];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines) ?: [$this->blankLine()];
    }

    public function saveReceipt(FnbStockService $stock): void
    {
        $this->authorizeAction('restock_fnb_stock');
        $this->validate([
            'lines' => ['required', 'array', 'min:1', 'max:'.FnbStockService::MAX_RECEIPT_LINES],
            'lines.*.menu_id' => ['required', 'distinct', 'exists:fnb_menus,id'],
            'lines.*.qty' => ['required', 'integer', 'min:1', 'max:'.FnbStockService::MAX_QTY],
            'lines.*.price' => ['required', 'numeric', 'min:0', 'max:'.FnbStockService::MAX_UNIT_PRICE],
            'supplier' => ['nullable', 'string', 'max:100'],
            'invoiceNumber' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'lines.*.menu_id.required' => 'Pilih menu.',
            'lines.*.menu_id.distinct' => 'Menu ini sudah ada di baris lain.',
            'lines.*.qty.required' => 'Isi jumlah.',
            'lines.*.qty.min' => 'Minimal 1.',
            'lines.*.price.required' => 'Isi harga (0 untuk titipan).',
        ], ['supplier' => 'supplier', 'invoiceNumber' => 'nomor nota']);

        $this->attempt(function () use ($stock) {
            // Total dihitung sistem: jumlah × harga beli per satuan.
            $lines = array_map(fn ($l) => ['menu_id' => $l['menu_id'], 'qty' => (int) $l['qty'], 'total' => round((int) $l['qty'] * (float) $l['price'], 2)], $this->lines);
            $receipt = $stock->receive($lines, $this->supplier, $this->invoiceNumber, $this->notes, auth()->user());
            Notification::make()->title('Penerimaan Barang Tersimpan')
                ->body("{$receipt->receipt_number}: {$receipt->item_count} item, ".BukuTransaksi::rupiah($receipt->total_amount).'.')
                ->success()->send();
            $this->closeModal();
        });
    }

    public function downloadImportTemplate(): BinaryFileResponse
    {
        $this->authorizeAction('restock_fnb_stock');
        $path = $this->tempXlsxPath('club61-pb');
        app(FnbReceiptImport::class)->buildTemplate($path);

        return response()->download($path, 'Templat-Penerimaan-Barang-FnB-'.now(LedgerReport::TIMEZONE)->format('Ymd').'.xlsx')->deleteFileAfterSend();
    }

    public function updatedImportFile(): void
    {
        $this->authorizeAction('restock_fnb_stock');
        $this->resetErrorBag('importFile');
        $this->validate(['importFile' => ['required', 'file', 'max:5120', 'mimes:xlsx']], [
            'importFile.mimes' => 'File harus berformat Excel .xlsx (gunakan templat).',
            'importFile.max' => 'Ukuran file maksimal 5 MB.',
        ]);
    }

    public function saveImport(): void
    {
        $this->authorizeAction('restock_fnb_stock');
        $this->validate([
            'importFile' => ['required', 'file', 'max:5120', 'mimes:xlsx'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], ['importFile.required' => 'Pilih file Excel terlebih dahulu.']);

        // Dibaca ulang dari file saat disimpan — pratinjau di layar tidak dipercaya.
        $import = app(FnbReceiptImport::class);
        $parsed = $import->parse($this->importFile->getRealPath());
        if ($parsed['fatal'] || $parsed['errors'] > 0) {
            Notification::make()->title('Impor belum dapat disimpan')->body($parsed['fatal'] ?? "Terdapat {$parsed['errors']} baris bermasalah. Perbaiki file lalu unggah kembali.")->danger()->send();

            return;
        }
        // Stok Fisik = Stok Opname; Perbarui HPP = ubah data menu — masing-masing butuh izinnya sendiri.
        $missing = array_filter([
            $parsed['counted'] > 0 && ! auth()->user()->can('opname_fnb_stock') ? 'Stok Opname (kolom Stok Fisik Saat Ini)' : null,
            $this->updateHpp && ! auth()->user()->can('manage_fnb_hpp') ? 'Kelola HPP (Perbarui HPP)' : null,
        ]);
        if ($missing) {
            Notification::make()->title('Akses ditolak')->body('Anda tidak memiliki izin: '.implode(', ', $missing).'. Kosongkan kolom tersebut atau hubungi admin.')->danger()->send();

            return;
        }

        $this->attempt(function () use ($import, $parsed) {
            $result = $import->commit($parsed, $this->updateHpp, $this->notes, auth()->user());
            Notification::make()->title('Impor Tersimpan')
                ->body(implode(' ', array_filter([
                    $result['receipts'] ? count($result['receipts']).' Penerimaan Barang ('.implode(', ', $result['receipts']).').' : null,
                    $result['counted'] ? "{$result['counted']} menu disesuaikan dengan Stok Fisik." : null,
                    $result['enabled'] ? "Lacak Persediaan diaktifkan untuk {$result['enabled']} menu." : null,
                    $result['hpp_updated'] ? "HPP {$result['hpp_updated']} menu diperbarui." : null,
                ])))
                ->success()->send();
            $this->closeModal();
        });
    }

    public function saveAdjustment(FnbStockService $stock): void
    {
        $this->authorizeAction('adjust_fnb_stock');
        $this->validate([
            'lines' => ['required', 'array', 'min:1', 'max:'.FnbStockService::MAX_RECEIPT_LINES],
            'lines.*.menu_id' => ['required', 'distinct', 'exists:fnb_menus,id'],
            'lines.*.direction' => ['required', 'in:IN,OUT'],
            'lines.*.qty' => ['required', 'integer', 'min:1', 'max:'.FnbStockService::MAX_QTY],
            'lines.*.reason' => ['required', 'in:'.implode(',', array_keys(FnbStockMovement::REASONS))],
            'notes' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'lines.*.menu_id.required' => 'Pilih menu.',
            'lines.*.menu_id.distinct' => 'Menu ini sudah ada di baris lain.',
            'lines.*.qty.required' => 'Isi jumlah.',
            'lines.*.qty.min' => 'Minimal 1.',
        ], ['notes' => 'keterangan']);

        $this->attempt(function () use ($stock) {
            $count = $stock->adjustMany(array_map(fn ($l) => [
                'menu_id' => $l['menu_id'],
                'change' => $l['direction'] === 'IN' ? (int) $l['qty'] : -(int) $l['qty'],
                'reason' => $l['reason'],
            ], $this->lines), $this->notes, auth()->user());
            Notification::make()->title('Penyesuaian Stok Tersimpan')->body("{$count} menu disesuaikan (tercatat di Kartu Stok).")->success()->send();
            $this->closeModal();
        });
    }

    /** Lembar hitung fisik (PDF) untuk dibawa ke gudang — tanpa angka sistem (hitung buta). */
    public function printOpnameSheet(): StreamedResponse
    {
        $this->authorizeAction('opname_fnb_stock');
        $pdf = app(FnbReportExport::class)->opnameSheet(auth()->user()?->name);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, 'lembar-opname-fnb-'.now(LedgerReport::TIMEZONE)->format('Ymd').'.pdf', ['Content-Type' => 'application/pdf']);
    }

    public function saveOpname(FnbStockService $stock): void
    {
        $this->authorizeAction('opname_fnb_stock');
        $this->validate([
            'notes' => ['nullable', 'string', 'max:500'],
            'counts.*' => ['nullable', 'integer', 'min:0', 'max:'.FnbStockService::MAX_QTY],
            'countNotes.*' => ['nullable', 'string', 'max:200'],
        ], [], ['counts.*' => 'hasil hitung fisik', 'countNotes.*' => 'catatan menu']);

        $this->attempt(function () use ($stock) {
            $result = $stock->opname($this->counts, $this->notes, auth()->user(), $this->countNotes);
            Notification::make()->title('Stok Opname Tersimpan')
                ->body("{$result['counted']} menu dihitung, {$result['changed']} menu memiliki selisih (tercatat di Kartu Stok).")
                ->success()->send();
            $this->closeModal();
        });
    }

    public function openReceipt(string $receiptId): void
    {
        $this->receiptId = $receiptId;
        $this->modal = 'receipt-detail';
    }

    public function openVoid(string $receiptId): void
    {
        if (! auth()->user()->can('adjust_fnb_stock')) {
            Notification::make()->title('Akses ditolak')->danger()->send();

            return;
        }

        $this->voidReceiptId = $receiptId;
        $this->voidReason = '';
        $this->resetErrorBag();
    }

    public function confirmVoid(FnbStockService $stock): void
    {
        $this->authorizeAction('adjust_fnb_stock');
        $this->validate(['voidReason' => ['required', 'string', 'min:5', 'max:255']], [], ['voidReason' => 'alasan']);

        $this->attempt(function () use ($stock) {
            $stock->voidReceipt(FnbStockReceipt::findOrFail($this->voidReceiptId), $this->voidReason, auth()->user());
            Notification::make()->title('Penerimaan Barang Dibatalkan')->body('Stok seluruh barang dan pengeluaran Pembelian Persediaan-nya telah dikembalikan.')->success()->send();
            $this->voidReceiptId = null;
            $this->voidReason = '';
        });
    }

    public function cancelVoid(): void
    {
        $this->voidReceiptId = null;
    }

    protected function getViewData(): array
    {
        $menus = FnbMenu::query()->with('category:id,name')
            ->when(trim($this->search) !== '', fn (Builder $q) => $q->where('name', 'like', '%'.trim($this->search).'%'))
            ->when($this->categoryFilter !== 'ALL', fn (Builder $q) => $q->where('category_id', $this->categoryFilter))
            ->when($this->statusFilter === 'TRACKED', fn (Builder $q) => $q->where('track_stock', true))
            ->when($this->statusFilter === 'UNTRACKED', fn (Builder $q) => $q->where('track_stock', false))
            ->when($this->statusFilter === 'ALERT', fn (Builder $q) => $q->where('track_stock', true)
                ->where(fn (Builder $w) => $w->where('stock_qty', '<=', 0)->orWhereColumn('stock_qty', '<=', 'min_stock')))
            ->orderBy('name')->get();

        $tracked = FnbMenu::where('track_stock', true);
        [$from, $until] = LedgerReport::resolveRange($this->historyPreset, $this->historyFrom, $this->historyUntil);

        $data = [
            'menus' => $menus,
            'categories' => FnbCategory::orderBy('sort_order')->get(['id', 'name']),
            'kpi' => [
                'tracked' => (clone $tracked)->count(),
                'low' => app(FnbReportService::class)->stockAlerts()->where('stock_qty', '>', 0)->count(),
                'empty' => (clone $tracked)->where('stock_qty', '<=', 0)->count(),
                'value' => (float) (clone $tracked)->where('stock_qty', '>', 0)->selectRaw('COALESCE(SUM(stock_qty * hpp), 0) as v')->value('v'),
            ],
            'activeMenu' => $this->menuId ? FnbMenu::find($this->menuId) : null,
            'trackedMenus' => in_array($this->modal, ['adjust', 'opname'], true)
                ? FnbMenu::where('track_stock', true)->orderBy('name')->get(['id', 'name', 'stock_qty', 'hpp']) : collect(),
            // Penerimaan Barang: semua menu (yang belum dilacak diaktifkan otomatis saat disimpan).
            'receiptMenus' => $this->modal === 'receipt'
                ? FnbMenu::orderBy('name')->get(['id', 'name', 'stock_qty', 'hpp', 'track_stock']) : collect(),
            'cardMovements' => $this->modal === 'card' && $this->menuId
                ? FnbStockMovement::with(['creator:id,name', 'voider:id,name', 'receipt:id,receipt_number,supplier,invoice_number'])
                    ->where('menu_id', $this->menuId)->latest('created_at')->limit(100)->get() : collect(),
            'importPreview' => $this->modal === 'import' && $this->importFile && ! $this->getErrorBag()->has('importFile')
                ? app(FnbReceiptImport::class)->parse($this->importFile->getRealPath()) : null,
            'activeReceipt' => $this->modal === 'receipt-detail' && $this->receiptId
                ? FnbStockReceipt::with(['lines', 'creator:id,name', 'voider:id,name'])->find($this->receiptId) : null,
            'voidTarget' => $this->voidReceiptId ? FnbStockReceipt::find($this->voidReceiptId) : null,
            'history' => null,
            'receipts' => null,
        ];

        if ($this->tab === 'history') {
            $data['history'] = app(FnbReportService::class)->movementsBetween($from, $until)
                ->with(['creator:id,name', 'voider:id,name', 'receipt:id,receipt_number,supplier,invoice_number'])
                ->when($this->historyType !== 'ALL', fn (Builder $q) => $q->where('type', $this->historyType))
                ->when(trim($this->search) !== '', fn (Builder $q) => $q->where('menu_name', 'like', '%'.trim($this->search).'%'))
                ->latest('created_at')
                ->paginate(25);
        }

        if ($this->tab === 'receipts') {
            $tz = config('app.timezone');
            $like = '%'.trim($this->search).'%';
            $data['receipts'] = FnbStockReceipt::query()->with(['creator:id,name', 'voider:id,name'])
                ->when($from, fn (Builder $q) => $q->where('created_at', '>=', \Carbon\Carbon::parse($from, LedgerReport::TIMEZONE)->startOfDay()->setTimezone($tz)))
                ->when($until, fn (Builder $q) => $q->where('created_at', '<', \Carbon\Carbon::parse($until, LedgerReport::TIMEZONE)->addDay()->startOfDay()->setTimezone($tz)))
                ->when(trim($this->search) !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w->where('receipt_number', 'like', $like)
                    ->orWhere('supplier', 'like', $like)->orWhere('invoice_number', 'like', $like)))
                ->latest()
                ->paginate(25);
        }

        return $data;
    }
}
