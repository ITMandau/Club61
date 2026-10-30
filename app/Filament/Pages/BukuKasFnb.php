<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HandlesFnbErrors;
use App\Models\Fnb\FnbCashCategory;
use App\Models\Fnb\FnbCashEntry;
use App\Services\Audit\ActivityLogger;
use App\Services\Finance\LedgerReport;
use App\Services\Fnb\FnbCashbookService;
use App\Services\Fnb\FnbReportExport;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Modul 29 — Pengeluaran F&B (belanja bahan, gas, gaji, dst.). Pembelian Persediaan tercatat otomatis dari Penerimaan
 * Barang. Pemasukan F&B = penjualan kasir (Buku Transaksi), tidak dicatat di sini. Catatan tidak diedit / dihapus —
 * dibatalkan dengan alasan. Nama class & izin (View:BukuKasFnb) dipertahankan supaya hak akses role tidak berubah.
 */
class BukuKasFnb extends Page
{
    use HandlesFnbErrors;
    use WithFileUploads;
    use WithPagination;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Pengeluaran F&B';

    protected static string|UnitEnum|null $navigationGroup = 'F&B';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Pengeluaran F&B';

    protected string $view = 'filament.pages.buku-kas-fnb';

    public string $preset = 'bulan_ini';

    public ?string $from = null;

    public ?string $until = null;

    public string $categoryFilter = 'ALL';

    public string $statusFilter = 'ACTIVE'; // ACTIVE, VOIDED, ALL

    // Modal: entry, categories
    public ?string $modal = null;

    public string $entryCategoryId = '';

    public string $entryDate = '';

    public int|float|string|null $entryAmount = null;

    public string $entryNotes = '';

    public $entryAttachment = null;

    public ?string $voidEntryId = null;

    public string $voidReason = '';

    public ?string $editingCategoryId = null;

    public string $categoryName = '';

    public bool $categoryIsStockPurchase = false;

    /** Jenis kategori yang sudah punya catatan tidak bisa diubah (laba periode lalu tidak boleh ikut berubah). */
    public bool $categoryTypeLocked = false;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('View:BukuKasFnb');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['preset', 'from', 'until', 'categoryFilter', 'statusFilter'], true)) {
            $this->resetPage();
        }
    }

    public function openEntry(): void
    {
        if (! auth()->user()->can('manage_fnb_cashbook')) {
            Notification::make()->title('Akses ditolak')->danger()->send();

            return;
        }

        $this->resetErrorBag();
        $this->entryCategoryId = (string) $this->entryCategories()->value('id');
        $this->entryDate = now(LedgerReport::TIMEZONE)->toDateString();
        $this->entryAmount = null;
        $this->entryNotes = '';
        $this->entryAttachment = null;
        $this->modal = 'entry';
    }

    public function saveEntry(FnbCashbookService $cashbook): void
    {
        $this->authorizeAction('manage_fnb_cashbook');
        $this->validate([
            'entryCategoryId' => ['required', 'exists:fnb_cash_categories,id'],
            'entryDate' => ['required', 'date', 'before_or_equal:'.now(LedgerReport::TIMEZONE)->toDateString()],
            'entryAmount' => ['required', 'numeric', 'min:1', 'max:10000000000'],
            'entryNotes' => ['nullable', 'string', 'max:500'],
            'entryAttachment' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [], ['entryCategoryId' => 'kategori', 'entryDate' => 'tanggal', 'entryAmount' => 'nominal', 'entryAttachment' => 'foto nota']);

        $this->attempt(function () use ($cashbook) {
            $cashbook->record($this->entryCategoryId, $this->entryDate, (float) $this->entryAmount, $this->entryNotes, $this->entryAttachment, auth()->user());
            Notification::make()->title('Pengeluaran Tercatat')->success()->send();
            $this->closeModal();
        });
    }

    public function openVoid(string $entryId): void
    {
        if (! auth()->user()->can('manage_fnb_cashbook')) {
            Notification::make()->title('Akses ditolak')->danger()->send();

            return;
        }

        $this->resetErrorBag();
        $this->voidEntryId = $entryId;
        $this->voidReason = '';
    }

    public function confirmVoid(FnbCashbookService $cashbook): void
    {
        $this->authorizeAction('manage_fnb_cashbook');
        $this->validate(['voidReason' => ['required', 'string', 'min:5', 'max:255']], [], ['voidReason' => 'alasan']);

        $this->attempt(function () use ($cashbook) {
            $cashbook->void(FnbCashEntry::findOrFail($this->voidEntryId), $this->voidReason, auth()->user());
            Notification::make()->title('Catatan Dibatalkan')->success()->send();
            $this->voidEntryId = null;
        });
    }

    public function cancelVoid(): void
    {
        $this->voidEntryId = null;
    }

    public function openCategories(): void
    {
        if (! auth()->user()->can('manage_fnb_cashbook')) {
            Notification::make()->title('Akses ditolak')->danger()->send();

            return;
        }

        $this->resetCategoryForm();
        $this->modal = 'categories';
    }

    public function editCategory(string $categoryId): void
    {
        $this->authorizeAction('manage_fnb_cashbook');
        $category = FnbCashCategory::findOrFail($categoryId);
        $this->resetErrorBag();
        $this->editingCategoryId = $category->id;
        $this->categoryName = $category->name;
        $this->categoryIsStockPurchase = $category->is_stock_purchase;
        $this->categoryTypeLocked = $category->isSystem() || $category->entries()->exists();
    }

    public function saveCategory(FnbCashbookService $cashbook): void
    {
        $this->authorizeAction('manage_fnb_cashbook');
        $this->validate([
            'categoryName' => ['required', 'string', 'max:60'],
        ], [], ['categoryName' => 'nama kategori']);

        $this->attempt(function () use ($cashbook) {
            $category = $this->editingCategoryId ? FnbCashCategory::findOrFail($this->editingCategoryId) : null;
            $cashbook->saveCategory($category, $this->categoryName, $this->categoryIsStockPurchase);
            Notification::make()->title($category ? 'Kategori Diperbarui' : 'Kategori Ditambahkan')->success()->send();
            $this->resetCategoryForm();
        });
    }

    public function toggleCategory(string $categoryId): void
    {
        $this->authorizeAction('manage_fnb_cashbook');
        $this->attempt(fn () => app(FnbCashbookService::class)->toggleCategory(FnbCashCategory::findOrFail($categoryId)));
    }

    public function resetCategoryForm(): void
    {
        $this->resetErrorBag();
        $this->editingCategoryId = null;
        $this->categoryName = '';
        $this->categoryIsStockPurchase = false;
        $this->categoryTypeLocked = false;
    }

    public function closeModal(): void
    {
        $this->modal = null;
        $this->entryAttachment = null;
    }

    public function downloadAttachment(string $entryId): ?StreamedResponse
    {
        $this->authorizeAction('View:BukuKasFnb');
        $entry = FnbCashEntry::findOrFail($entryId);
        $disk = Storage::disk(FnbCashbookService::ATTACHMENT_DISK);
        if (! $entry->attachment_path || ! $disk->exists($entry->attachment_path)) {
            Notification::make()->title('Foto nota tidak ditemukan')->danger()->send();

            return null;
        }

        return $disk->download($entry->attachment_path, 'nota-'.$entry->entry_date->format('Ymd').'-'.substr($entry->id, -6).'.'.pathinfo($entry->attachment_path, PATHINFO_EXTENSION));
    }

    protected function entryCategories(): Builder
    {
        return FnbCashCategory::query()->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('system_key')->orWhere('system_key', '!=', FnbCashCategory::RESTOCK_KEY))
            ->orderBy('sort_order');
    }

    public static function canExport(): bool
    {
        return self::canAccess() && (bool) auth()->user()?->can('export_reports');
    }

    public function exportExcel(): BinaryFileResponse
    {
        $label = $this->exportLog('Excel');
        $path = app(FnbReportExport::class)->expensesExcel($this->filteredEntries(), $label, $this->statusLabel(), $this->tempXlsxPath('fnb-exp'));

        return response()->download($path, $this->exportFileName('xlsx'))->deleteFileAfterSend();
    }

    public function exportPdf(): StreamedResponse
    {
        $label = $this->exportLog('PDF');
        $pdf = app(FnbReportExport::class)->expensesPdf($this->filteredEntries(), $label, $this->statusLabel(), auth()->user()?->name);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, $this->exportFileName('pdf'), ['Content-Type' => 'application/pdf']);
    }

    private function exportLog(string $format): string
    {
        $this->authorizeAction('View:BukuKasFnb');
        $this->authorizeAction('export_reports');
        [$from, $until] = LedgerReport::resolveRange($this->preset, $this->from, $this->until);
        $label = BukuTransaksi::periodLabel($this->preset, $from, $until);
        $category = $this->categoryFilter !== 'ALL' ? FnbCashCategory::find($this->categoryFilter)?->name : null;

        ActivityLogger::record('FNB', 'fnb_expense.exported', "Export Pengeluaran F&B ke {$format} ({$label})",
            meta: array_filter(['format' => $format, 'dari' => $from, 'sampai' => $until, 'kategori' => $category, 'status' => $this->statusLabel()]));

        return $label.($category ? " · Kategori: {$category}" : '');
    }

    private function statusLabel(): string
    {
        return ['ACTIVE' => 'Catatan aktif', 'VOIDED' => 'Dibatalkan', 'ALL' => 'Semua status'][$this->statusFilter] ?? 'Catatan aktif';
    }

    private function exportFileName(string $extension): string
    {
        return 'pengeluaran-fnb-'.now(LedgerReport::TIMEZONE)->format('Ymd-His').'.'.$extension;
    }

    /** Periode + kategori (tanpa filter status) — dasar kartu ringkasan. */
    private function baseEntries(): Builder
    {
        [$from, $until] = LedgerReport::resolveRange($this->preset, $this->from, $this->until);

        return FnbCashEntry::query()
            ->when($from, fn (Builder $q) => $q->whereDate('entry_date', '>=', $from))
            ->when($until, fn (Builder $q) => $q->whereDate('entry_date', '<=', $until))
            ->when($this->categoryFilter !== 'ALL', fn (Builder $q) => $q->where('category_id', $this->categoryFilter));
    }

    /** Persis yang tampil di tabel (periode + kategori + status) — dipakai layar & file unduhan. */
    private function filteredEntries(): Builder
    {
        return $this->baseEntries()
            ->when($this->statusFilter === 'ACTIVE', fn (Builder $q) => $q->whereNull('voided_at'))
            ->when($this->statusFilter === 'VOIDED', fn (Builder $q) => $q->whereNotNull('voided_at'))
            ->with(['category:id,name,is_stock_purchase,system_key', 'creator:id,name', 'voider:id,name', 'receipt:id,receipt_number'])
            ->orderByDesc('entry_date')->orderByDesc('created_at');
    }

    protected function getViewData(): array
    {
        [$from, $until] = LedgerReport::resolveRange($this->preset, $this->from, $this->until);
        $out = $this->baseEntries()->whereNull('voided_at')->with('category:id,is_stock_purchase')->get(['id', 'amount', 'category_id']);

        return [
            'periodLabel' => BukuTransaksi::periodLabel($this->preset, $from, $until),
            'totals' => [
                'out' => (float) $out->sum('amount'),
                'stock' => (float) $out->filter(fn ($e) => $e->category?->is_stock_purchase)->sum('amount'),
                'operational' => (float) $out->reject(fn ($e) => $e->category?->is_stock_purchase)->sum('amount'),
            ],
            'entries' => $this->filteredEntries()->paginate(25),
            'allCategories' => FnbCashCategory::withCount('entries')->orderBy('sort_order')->get(),
            'entryCategoryOptions' => $this->modal === 'entry' ? $this->entryCategories()->get(['id', 'name', 'is_stock_purchase']) : collect(),
            'voidTarget' => $this->voidEntryId ? FnbCashEntry::with('category')->find($this->voidEntryId) : null,
        ];
    }
}
