<?php

namespace App\Filament\Pages;

use App\Models\Audit\ActivityLog;
use App\Services\Audit\ActivityLogger;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Modul 16 — Panel Log Aktivitas (READ-ONLY). Tidak ada satu pun aksi tulis di halaman ini:
 * log tidak bisa diedit / dihapus dari aplikasi, termasuk oleh super_admin.
 */
class LogAktivitas extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-finger-print';

    protected static ?string $navigationLabel = 'Log Aktivitas';

    protected static string|UnitEnum|null $navigationGroup = 'Main Menu';

    protected static ?int $navigationSort = 16;

    protected static ?string $title = 'Log Aktivitas & Jejak Audit';

    protected string $view = 'filament.pages.log-aktivitas';

    private const TIMEZONE = 'Asia/Jakarta';

    public const MODULES = [
        'FINANCE' => 'Keuangan & Transaksi',
        'PADEL' => 'Padel & Booking',
        'POS' => 'Shift Kasir',
        'FNB' => 'F&B',
        'MEMBERSHIP' => 'Membership',
        'SPONSOR' => 'Sponsor',
        'MASTER_DATA' => 'Master Data',
        'USER_ROLE' => 'User & Hak Akses',
        'CONTENT' => 'Konten Website',
        'AUTH' => 'Login & Keamanan',
        'SYSTEM' => 'Sistem',
    ];

    public const CHANNELS = [
        'ADMIN_PANEL' => 'Panel Admin',
        'POS_WALKIN' => 'POS Walk-in Padel',
        'POS_FNB' => 'POS Kasir F&B',
        'POS_CHECKIN' => 'Gate Check-in',
        'KDS' => 'Dapur (KDS)',
        'LOGIN_PAGE' => 'Halaman Login',
        'CUSTOMER_WEB' => 'Web Customer',
        'MOBILE_API' => 'Aplikasi Mobile',
        'WEBHOOK' => 'Webhook Midtrans',
        'SCHEDULER' => 'Scheduler',
        'CONSOLE' => 'Console Server',
        'SYSTEM' => 'Sistem',
    ];

    public const ACTOR_TYPES = [
        'STAFF' => 'Staf',
        'CUSTOMER' => 'Customer',
        'SYSTEM' => 'Sistem',
        'WEBHOOK' => 'Webhook',
        'GUEST' => 'Tamu (belum login)',
    ];

    public const SEVERITIES = [
        'INFO' => 'Info',
        'WARNING' => 'Perlu Perhatian',
        'CRITICAL' => 'Kritis',
    ];

    public function table(Table $table): Table
    {
        return $table
            ->query(ActivityLog::query())
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('created_at')->orderByDesc('id'))
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->striped()
            ->emptyStateHeading('Belum ada aktivitas pada filter ini')
            ->emptyStateDescription('Ubah rentang tanggal atau hapus filter untuk melihat aktivitas lain.')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y, H:i:s', self::TIMEZONE)
                    ->sortable()
                    ->description(fn (ActivityLog $log) => $log->created_at?->timezone(self::TIMEZONE)->diffForHumans()),
                TextColumn::make('causer_name')
                    ->label('Pengguna')
                    ->searchable()
                    ->weight('bold')
                    ->getStateUsing(fn (ActivityLog $log) => $log->causer_name ?? match ($log->actor_type) {
                        'SYSTEM' => 'Sistem',
                        'WEBHOOK' => 'Midtrans (Webhook)',
                        default => 'Tamu (belum login)',
                    })
                    ->description(fn (ActivityLog $log) => $log->causer_role ?? (self::ACTOR_TYPES[$log->actor_type] ?? $log->actor_type)),
                TextColumn::make('module')
                    ->label('Modul')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => self::MODULES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'FINANCE' => 'success',
                        'USER_ROLE', 'AUTH' => 'danger',
                        'PADEL', 'POS' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('description')
                    ->label('Aktivitas')
                    ->searchable(['description', 'subject_label', 'event'])
                    ->wrap()
                    ->description(fn (ActivityLog $log) => $log->event),
                TextColumn::make('channel')
                    ->label('Dari')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state) => self::CHANNELS[$state] ?? $state)
                    ->toggleable(),
                TextColumn::make('severity')
                    ->label('Tingkat')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => self::SEVERITIES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'CRITICAL' => 'danger',
                        'WARNING' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('tanggal')
                    ->schema([
                        DatePicker::make('dari')->label('Dari tanggal')->native(false)->displayFormat('d M Y')->default(now(self::TIMEZONE)->toDateString()),
                        DatePicker::make('sampai')->label('Sampai tanggal')->native(false)->displayFormat('d M Y'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['dari'] ?? null, fn (Builder $q, $date) => $q->where('created_at', '>=', Carbon::parse($date, self::TIMEZONE)->startOfDay()->utc()))
                            ->when($data['sampai'] ?? null, fn (Builder $q, $date) => $q->where('created_at', '<=', Carbon::parse($date, self::TIMEZONE)->endOfDay()->utc()));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['dari'] ?? null) {
                            $indicators[] = 'Dari '.Carbon::parse($data['dari'])->translatedFormat('d M Y');
                        }
                        if ($data['sampai'] ?? null) {
                            $indicators[] = 'Sampai '.Carbon::parse($data['sampai'])->translatedFormat('d M Y');
                        }

                        return $indicators;
                    }),
                SelectFilter::make('causer_id')
                    ->label('Pengguna')
                    ->relationship('causer', 'name')
                    ->searchable(),
                SelectFilter::make('module')->label('Modul')->options(self::MODULES)->multiple(),
                SelectFilter::make('severity')->label('Tingkat')->options(self::SEVERITIES)->multiple(),
                SelectFilter::make('channel')->label('Dari')->options(self::CHANNELS)->multiple(),
                SelectFilter::make('actor_type')->label('Jenis Pelaku')->options(self::ACTOR_TYPES),
                SelectFilter::make('event')
                    ->label('Jenis Aksi')
                    ->options(fn () => ActivityLog::query()->distinct()->orderBy('event')->limit(300)->pluck('event', 'event')->all())
                    ->searchable()
                    ->multiple(),
            ])
            ->filtersFormColumns(2)
            ->recordAction('detail')
            ->recordActions([
                Action::make('detail')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->slideOver()
                    ->modalWidth('3xl')
                    ->modalHeading(fn (ActivityLog $record) => Str::limit($record->description, 90))
                    ->modalDescription(fn (ActivityLog $record) => $record->created_at?->timezone(self::TIMEZONE)->translatedFormat('l, d F Y · H:i:s').' WIB')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (ActivityLog $record) => view('filament.pages.partials.activity-log-detail', [
                        'log' => $record,
                        'related' => $record->batch_id
                            ? ActivityLog::query()->inBatch($record->batch_id)->whereKeyNot($record->getKey())->orderBy('created_at')->orderBy('id')->limit(50)->get()
                            : collect(),
                    ])),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn () => auth()->user()?->can('export_activity_logs') ?? false)
                    ->action(fn () => $this->exportCsv()),
            ]);
    }

    /**
     * Export sesuai filter & pencarian yang sedang aktif di tabel. Protected (bukan public) supaya
     * tidak bisa dipanggil langsung dari browser — hanya lewat header action yang sudah dicek izinnya.
     */
    protected function exportCsv(): StreamedResponse
    {
        abort_unless(auth()->user()?->can('export_activity_logs'), 403, 'Akses ditolak: Anda tidak memiliki izin [export_activity_logs] untuk export log aktivitas.');

        $limit = max(1, (int) config('audit.export_max_rows', 50000));
        $query = $this->getFilteredSortedTableQuery()->limit($limit);
        $total = (clone $query)->count();

        ActivityLogger::record(
            module: 'AUTH',
            event: 'activity_log.exported',
            description: "Export {$total} baris log aktivitas ke CSV",
            meta: ['jumlah_baris' => $total, 'filter' => $this->tableFilters, 'pencarian' => $this->tableSearch ?: null],
            severity: ActivityLogger::WARNING,
        );

        $filename = 'log-aktivitas-'.now(self::TIMEZONE)->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM supaya Excel membaca UTF-8 dengan benar
            fputcsv($out, ['Waktu (WIB)', 'Pengguna', 'Role', 'Jenis Pelaku', 'Modul', 'Aksi', 'Aktivitas', 'Data', 'Dari', 'Tingkat', 'IP', 'Perubahan', 'Detail']);

            foreach ($query->cursor() as $log) {
                fputcsv($out, array_map([self::class, 'csvSafe'], [
                    $log->created_at?->timezone(self::TIMEZONE)->format('Y-m-d H:i:s'),
                    $log->causer_name,
                    $log->causer_role,
                    self::ACTOR_TYPES[$log->actor_type] ?? $log->actor_type,
                    self::MODULES[$log->module] ?? $log->module,
                    $log->event,
                    $log->description,
                    $log->subject_label,
                    self::CHANNELS[$log->channel] ?? $log->channel,
                    self::SEVERITIES[$log->severity] ?? $log->severity,
                    $log->ip_address,
                    $log->changes ? json_encode($log->changes, JSON_UNESCAPED_UNICODE) : '',
                    $log->meta ? json_encode($log->meta, JSON_UNESCAPED_UNICODE) : '',
                ]));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Cegah CSV/Formula injection: sel yang diawali = + - @ (atau tab/CR) dieksekusi sebagai rumus
     * oleh Excel. Isi log bisa berasal dari input user (nama, catatan refund), jadi wajib dinetralkan.
     */
    public static function csvSafe(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
