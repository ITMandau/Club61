<?php

namespace App\Filament\Pages;

use App\Models\Finance\LedgerEntry;
use App\Models\Pos\PosCashierShift;
use App\Services\Audit\ActivityLogger;
use App\Services\Finance\LedgerReport;
use App\Services\Finance\LedgerTransactionPresenter;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Modul 17 Fase 2 — Buku Transaksi Terpadu (READ-ONLY). Satu baris = satu pembayaran atau satu refund, dari tabel
 * buku besar ledger_entries yang ditulis sekali saat uang masuk / keluar. Angka pendapatan = penjualan bersih
 * sebelum pajak; pajak ditampilkan sebagai pajak terkumpul, biaya layanan terpisah.
 */
class BukuTransaksi extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Buku Transaksi';

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Buku Transaksi Terpadu';

    protected string $view = 'filament.pages.buku-transaksi';

    /**
     * Tautan dari Analytics & Keuangan (rincian per kategori / sumber / metode) membuka tabel yang sudah tersaring:
     * ?periode=bulan_ini&kategori=FNB&sumber=POS_FNB&metode=QRIS (&dari / &sampai untuk periode kustom).
     */
    public function mount(): void
    {
        $query = request()->query();
        if (! array_intersect(['periode', 'kategori', 'sumber', 'metode'], array_keys($query))) {
            return;
        }

        $preset = array_key_exists((string) ($query['periode'] ?? ''), LedgerReport::PRESETS) ? $query['periode'] : LedgerReport::DEFAULT_PRESET;
        $date = fn ($value) => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
        $pick = fn (string $key, array $allowed) => array_values(array_intersect((array) ($query[$key] ?? []), $allowed));

        $this->tableFilters = [
            'periode' => ['preset' => $preset, 'dari' => $date($query['dari'] ?? null), 'sampai' => $date($query['sampai'] ?? null)],
            'category' => ['values' => $pick('kategori', array_keys(LedgerEntry::CATEGORIES))],
            'source' => ['values' => $pick('sumber', array_keys(LedgerEntry::SOURCES))],
            'payment_method' => ['values' => array_slice(array_filter(array_map(fn ($v) => mb_substr((string) $v, 0, 50), (array) ($query['metode'] ?? []))), 0, 10)],
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => LedgerReport::grouped(LedgerEntry::query()))
            ->defaultSort(fn (Builder $query) => LedgerReport::orderNewestFirst($query))
            // Pencarian lewat fungsi yang sama dengan export & kartu ringkasan.
            ->searchUsing(fn (Builder $query, string $search) => LedgerReport::applySearch($query, $search))
            ->searchPlaceholder('No. order, kode booking, nama / HP customer, RRN')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->striped()
            ->emptyStateHeading('Belum ada transaksi pada filter ini')
            ->emptyStateDescription('Ubah periode atau hapus filter. Transaksi lama yang belum tercatat bisa diisi dengan `php artisan ledger:backfill`.')
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Waktu')
                    ->dateTime('d M Y, H:i', LedgerReport::TIMEZONE)
                    ->sortable(query: fn (Builder $query, string $direction) => LedgerReport::orderNewestFirst($query, $direction))
                    ->description(fn (LedgerEntry $row) => LedgerEntry::ENTRY_TYPES[$row->entry_type] ?? $row->entry_type),
                TextColumn::make('order_number')
                    ->label('No. Order')
                    ->searchable()
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->description(fn (LedgerEntry $row) => LedgerReport::categoriesLabel($row->categories)),
                TextColumn::make('source')
                    ->label('Sumber')
                    ->badge()
                    ->color(fn (string $state) => str_starts_with($state, 'ONLINE') || str_ends_with($state, 'ONLINE') ? 'info' : 'gray')
                    ->formatStateUsing(fn (string $state) => LedgerEntry::sourceLabel($state)),
                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->placeholder('-')
                    ->limit(28),
                TextColumn::make('cashier_name')
                    ->label('Kasir / Shift')
                    ->getStateUsing(fn (LedgerEntry $row) => LedgerReport::cashierLabel($row))
                    ->description(fn (LedgerEntry $row) => $row->pos_shift_id ? self::shiftNumber($row->pos_shift_id) : null)
                    ->toggleable(),
                TextColumn::make('payment_method_label')
                    ->label('Metode Bayar')
                    ->placeholder('-')
                    ->limit(30)
                    ->description(fn (LedgerEntry $row) => $row->payment_reference ? \Illuminate\Support\Str::limit($row->payment_reference, 34) : null),
                TextColumn::make('net_amount')
                    ->label('Penjualan Bersih')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => self::rupiah($state)),
                TextColumn::make('tax_amount')
                    ->label('Pajak')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => self::rupiah($state))
                    ->toggleable(),
                TextColumn::make('service_amount')
                    ->label('Biaya Layanan')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state) => self::rupiah($state))
                    ->toggleable(),
                TextColumn::make('total_amount')
                    ->label('Total')
                    ->alignEnd()
                    ->weight('bold')
                    ->color(fn ($state) => (float) $state < 0 ? 'danger' : null)
                    ->formatStateUsing(fn ($state) => self::rupiah($state)),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (LedgerEntry $row) => LedgerReport::statusLabel($row))
                    ->color(fn (string $state) => LedgerReport::statusColor($state)),
            ])
            ->filters([
                Filter::make('periode')
                    ->schema([
                        Select::make('preset')
                            ->label('Periode')
                            ->options(LedgerReport::PRESETS)
                            ->default(LedgerReport::DEFAULT_PRESET)
                            ->selectablePlaceholder(false)
                            ->live(),
                        DatePicker::make('dari')->label('Dari tanggal')->native(false)->displayFormat('d M Y')
                            ->visible(fn (Get $get) => $get('preset') === 'kustom'),
                        DatePicker::make('sampai')->label('Sampai tanggal')->native(false)->displayFormat('d M Y')
                            ->visible(fn (Get $get) => $get('preset') === 'kustom'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        [$from, $until] = LedgerReport::resolveRange($data['preset'] ?? null, $data['dari'] ?? null, $data['sampai'] ?? null);

                        return LedgerReport::applyDateRange($query, $from, $until);
                    })
                    ->indicateUsing(fn (array $data) => [self::periodLabel($data['preset'] ?? null, $data['dari'] ?? null, $data['sampai'] ?? null)]),
                SelectFilter::make('source')->label('Sumber')->options(LedgerEntry::SOURCES)->multiple(),
                SelectFilter::make('category')
                    ->label('Kategori')
                    ->options(LedgerEntry::CATEGORIES)
                    ->multiple()
                    ->query(fn (Builder $query, array $data) => LedgerReport::applyCategories($query, $data['values'] ?? [])),
                SelectFilter::make('payment_method')->label('Metode Bayar')->options(fn () => LedgerReport::methodOptions())->multiple()->searchable(),
                SelectFilter::make('cashier_id')->label('Kasir')->options(fn () => LedgerReport::cashierOptions())->searchable(),
                SelectFilter::make('pos_shift_id')
                    ->label('Shift Kasir')
                    ->options(fn () => PosCashierShift::query()->latest('opened_at')->limit(200)->pluck('shift_number', 'id')->all())
                    ->searchable(),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(LedgerReport::STATUSES)
                    ->multiple()
                    ->query(fn (Builder $query, array $data) => LedgerReport::applyStatuses($query, $data['values'] ?? [])),
            ])
            ->filtersFormColumns(2)
            ->recordAction('detail')
            ->recordActions([
                Action::make('detail')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->slideOver()
                    ->modalWidth('4xl')
                    ->modalHeading(fn (LedgerEntry $record) => ($record->entry_type === LedgerEntry::TYPE_REFUND ? 'Refund · ' : '').$record->order_number)
                    ->modalDescription(fn (LedgerEntry $record) => $record->occurred_at?->timezone(LedgerReport::TIMEZONE)->translatedFormat('l, d F Y · H:i').' WIB · '.LedgerEntry::sourceLabel($record->source))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (LedgerEntry $record) => view('filament.pages.partials.ledger-detail',
                        app(LedgerTransactionPresenter::class)->detail($record, auth()->user()?->can('View:LogAktivitas') ?? false))),
                Action::make('invoice')
                    ->label('Invoice')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->visible(fn () => auth()->user()?->can('view_ledger_invoice') ?? false)
                    ->authorize(fn () => auth()->user()?->can('view_ledger_invoice') ?? false)
                    ->modalWidth('lg')
                    ->modalHeading(fn (LedgerEntry $record) => 'Invoice '.$record->order_number)
                    ->modalDescription('Salinan admin — ditandai "SALINAN ADMIN" dan tercatat di Log Aktivitas.')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->mountUsing(fn (LedgerEntry $record) => self::logInvoiceViewed($record))
                    ->modalContent(fn (LedgerEntry $record) => view('filament.pages.partials.ledger-invoice-modal',
                        app(LedgerTransactionPresenter::class)->invoice($record))),
            ])
            ->headerActions([
                ActionGroup::make([
                    Action::make('exportXlsx')
                        ->label('Excel (.xlsx)')
                        ->icon('heroicon-o-table-cells')
                        // Route GET biasa (LedgerExportController), BUKAN aksi Livewire — respons aksi Livewire di-buffer
                        // penuh di memori + base64 dalam JSON. URL-nya dikecualikan dari mode SPA (AdminPanelProvider).
                        ->url(fn () => $this->exportUrl('xlsx')),
                    Action::make('exportPdf')
                        ->label('PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->url(fn () => $this->exportUrl('pdf')),
                ])
                    ->label('Export')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->button()
                    ->visible(fn () => auth()->user()?->can('export_ledger') ?? false),
            ]);
    }

    /**
     * State filter & pencarian tabel dalam format filter LedgerReport (dipakai kartu ringkasan & URL export).
     *
     * @return array<string, mixed>
     */
    public function currentFilters(): array
    {
        $filters = $this->tableFilters ?? [];
        $date = fn ($value) => filled($value) ? Carbon::parse($value)->toDateString() : null;

        return array_filter([
            'periode' => $filters['periode']['preset'] ?? LedgerReport::DEFAULT_PRESET,
            'dari' => $date($filters['periode']['dari'] ?? null),
            'sampai' => $date($filters['periode']['sampai'] ?? null),
            'source' => $filters['source']['values'] ?? null,
            'category' => $filters['category']['values'] ?? null,
            'method' => $filters['payment_method']['values'] ?? null,
            'cashier_id' => $filters['cashier_id']['value'] ?? null,
            'pos_shift_id' => $filters['pos_shift_id']['value'] ?? null,
            'status' => $filters['status']['values'] ?? null,
            'search' => $this->tableSearch ?: null,
        ], fn ($value) => filled($value));
    }

    public function exportUrl(string $format): string
    {
        return route('admin.buku-transaksi.export', ['format' => $format] + $this->currentFilters());
    }

    /** Angka kartu ringkasan untuk filter yang sedang aktif. */
    public function summary(): array
    {
        $filters = $this->currentFilters();
        [$from, $until] = LedgerReport::resolveRange($filters['periode'] ?? null, $filters['dari'] ?? null, $filters['sampai'] ?? null);

        return LedgerReport::summary(LedgerReport::applyFilters(LedgerEntry::query(), $filters), $from, $until)
            + ['period_label' => self::periodLabel($filters['periode'] ?? null, $filters['dari'] ?? null, $filters['sampai'] ?? null)];
    }

    public static function logInvoiceViewed(LedgerEntry $record): void
    {
        abort_unless(auth()->user()?->can('view_ledger_invoice'), 403);

        ActivityLogger::record(
            module: 'FINANCE',
            event: 'ledger.invoice_viewed',
            description: "Membuka salinan invoice {$record->order_number} dari Buku Transaksi",
            subject: $record->order,
            meta: array_filter([
                'no_order' => $record->order_number,
                'jenis' => LedgerEntry::ENTRY_TYPES[$record->entry_type] ?? $record->entry_type,
                'id_pembayaran' => $record->payment_id,
                'id_refund' => $record->refund_id,
            ]),
        );
    }

    public static function periodLabel(?string $preset, ?string $from, ?string $until): string
    {
        $preset = $preset ?: LedgerReport::DEFAULT_PRESET;
        if ($preset !== 'kustom') {
            return 'Periode: '.(LedgerReport::PRESETS[$preset] ?? $preset);
        }
        $fmt = fn ($d) => $d ? Carbon::parse($d)->translatedFormat('d M Y') : '…';

        return 'Periode: '.$fmt($from).' – '.$fmt($until);
    }

    public static function rupiah(mixed $value): string
    {
        $value = (float) $value;

        return ($value < 0 ? '-' : '').'Rp '.number_format(abs($value), 0, ',', '.');
    }

    /** @var array<string, ?string> */
    private static array $shiftNumbers = [];

    private static function shiftNumber(string $shiftId): ?string
    {
        return self::$shiftNumbers[$shiftId] ??= PosCashierShift::whereKey($shiftId)->value('shift_number');
    }
}
