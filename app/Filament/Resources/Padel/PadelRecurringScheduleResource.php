<?php

namespace App\Filament\Resources\Padel;

use App\Filament\Resources\Concerns\AuthorizesWithCanMethods;
use App\Filament\Resources\Padel\Pages\ManagePadelRecurringSchedules;
use App\Models\Padel\PadelCourt;
use App\Models\Padel\PadelRecurringOccurrence;
use App\Models\Padel\PadelRecurringSchedule;
use App\Services\Padel\RecurringScheduleService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\HttpException;
use UnitEnum;

/**
 * Jadwal Tetap Mingguan — booking rutin corporate / member (mis. IOH tiap Kamis 19:00–22:00 di 3 lapangan) yang dibuat
 * otomatis dan dipotong dari kuota jam membership. Batas reschedule mengikuti pengaturan global (tombol Batas Reschedule di Jadwal Tetap Mingguan).
 */
class PadelRecurringScheduleResource extends Resource
{
    use AuthorizesWithCanMethods;

    protected static ?string $model = PadelRecurringSchedule::class;

    protected static ?string $slug = 'jadwal-tetap';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationLabel = 'Jadwal Tetap Mingguan';

    protected static ?string $modelLabel = 'Jadwal Tetap';

    protected static ?string $pluralModelLabel = 'Jadwal Tetap Mingguan';

    protected static string|UnitEnum|null $navigationGroup = 'Operasional Harian';

    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->can('manage_recurring_bookings');
    }

    public static function canCreate(): bool
    {
        return self::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return self::canViewAny();
    }

    /** Hanya jadwal yang belum pernah membuat booking; selebihnya cukup dijeda. */
    public static function canDelete(Model $record): bool
    {
        return self::canViewAny() && $record instanceof PadelRecurringSchedule && ! $record->occurrences()->exists();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        $hours = fn (int $from, int $to) => collect(range($from, $to))
            ->mapWithKeys(fn (int $h) => [sprintf('%02d:00', $h) => sprintf('%02d:00', $h)])->all();

        return $schema->components([
            Section::make('Pemilik kuota & jadwal')->columnSpanFull()->schema([
                Grid::make(['default' => 1, 'sm' => 2])->schema([
                    TextInput::make('name')->label('Nama jadwal')->required()->maxLength(100)
                        ->placeholder('Contoh: IOH — Kamis malam'),
                    Select::make('user_membership_id')->label('Kuota jam dari membership')
                        ->options(fn (?PadelRecurringSchedule $record) => app(RecurringScheduleService::class)->membershipOptions($record?->user_membership_id))
                        ->searchable()->required()->native(false)
                        ->disabled(fn (?PadelRecurringSchedule $record) => $record?->occurrences()->exists() ?? false)
                        ->helperText('Corporate atau member individu yang punya kuota jam Padel. Booking dibuat atas nama pemilik membership.'),
                    Select::make('day_of_week')->label('Hari')->options(PadelRecurringSchedule::DAYS)->required()->native(false),
                    Select::make('court_ids')->label('Lapangan')->multiple()->required()->native(false)
                        ->options(fn () => PadelCourt::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all()),
                    Select::make('start_time')->label('Jam mulai')->options($hours(6, 23))->required()->native(false),
                    Select::make('end_time')->label('Jam selesai')->options($hours(7, 24))->required()->native(false),
                    DatePicker::make('starts_on')->label('Mulai tanggal')->required()->native(false)->displayFormat('d M Y'),
                    DatePicker::make('ends_on')->label('Sampai tanggal (opsional)')->native(false)->displayFormat('d M Y')
                        ->helperText('Kosongkan = berjalan terus sampai kuota / masa membership habis.'),
                ]),
            ]),
            Section::make('Aturan')->columnSpanFull()->schema([
                Grid::make(['default' => 1, 'sm' => 2])->schema([
                    TextInput::make('weeks_ahead')->label('Buat booking sampai')->integer()->minValue(1)
                        ->maxValue(PadelRecurringSchedule::MAX_WEEKS_AHEAD)->required()->default(5)->suffix('minggu ke depan'),
                ]),
                Textarea::make('notes')->label('Catatan (opsional)')->rows(2)->maxLength(1000)->columnSpanFull(),
            ])->description('Customer bisa booking sampai 30 hari ke depan, jadi 5 minggu = slot jadwal tetap sudah terkunci duluan. Mengubah hari / jam / lapangan hanya berlaku untuk booking yang belum dibuat. Batas reschedule mengikuti pengaturan global (tombol Batas Reschedule di kanan atas halaman ini).'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['membership.user', 'membership.sponsorOrganization', 'membership.balances']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Jadwal')->weight('bold')->searchable()
                    ->description(fn (PadelRecurringSchedule $r) => $r->ownerLabel()),
                TextColumn::make('day_of_week')->label('Hari & jam')
                    ->formatStateUsing(fn (PadelRecurringSchedule $r) => $r->scheduleLabel())
                    ->description(fn (PadelRecurringSchedule $r) => self::courtNames($r).' · '.$r->hoursPerWeek().' jam/minggu'),
                TextColumn::make('quota')->label('Sisa kuota')
                    ->state(function (PadelRecurringSchedule $r) {
                        $left = app(RecurringScheduleService::class)->weeksLeft($r);

                        return $left === null ? 'Tidak ada kuota jam' : self::num($left['remaining']).' jam';
                    })
                    ->description(function (PadelRecurringSchedule $r) {
                        $left = app(RecurringScheduleService::class)->weeksLeft($r);

                        return $left === null ? null : '≈ '.$left['weeks'].' minggu lagi'.($left['leftover'] > 0 ? ' + '.self::num($left['leftover']).' jam' : '');
                    }),
                TextColumn::make('starts_on')->label('Periode')->date('d M Y')
                    ->description(fn (PadelRecurringSchedule $r) => $r->ends_on ? 's/d '.$r->ends_on->translatedFormat('d M Y') : 'tanpa tanggal akhir'),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => $state === PadelRecurringSchedule::STATUS_ACTIVE ? 'Aktif' : 'Dijeda')
                    ->color(fn (string $state) => $state === PadelRecurringSchedule::STATUS_ACTIVE ? 'success' : 'gray'),
            ])
            ->recordActions([
                Action::make('history')->label('Riwayat')->icon('heroicon-o-list-bullet')->color('gray')
                    ->modalHeading(fn (PadelRecurringSchedule $r) => 'Riwayat '.$r->name)
                    ->modalSubmitAction(false)->modalCancelActionLabel('Tutup')->modalWidth('3xl')
                    ->modalContent(fn (PadelRecurringSchedule $r) => view('filament.resources.padel.recurring-occurrences', [
                        'schedule' => $r,
                        'rows' => $r->occurrences()->with(['court', 'booking'])->orderByDesc('occurrence_date')->limit(60)->get()
                            ->groupBy(fn (PadelRecurringOccurrence $o) => $o->occurrence_date->toDateString()),
                    ])),
                ActionGroup::make([
                    Action::make('generate')->label('Buat booking sekarang')->icon('heroicon-o-bolt')
                        ->visible(fn (PadelRecurringSchedule $r) => $r->isActive())
                        ->requiresConfirmation()
                        ->modalDescription(fn (PadelRecurringSchedule $r) => 'Booking minggu yang belum dibuat (sampai '.$r->weeks_ahead.' minggu ke depan) dibuat sekarang dan dipotong dari kuota jam. Sistem juga menjalankan ini otomatis tiap malam.')
                        ->action(fn (PadelRecurringSchedule $record) => self::notifyGenerated(app(RecurringScheduleService::class)->generate($record, auth()->user()))),
                    Action::make('skip')->label('Lewati tanggal')->icon('heroicon-o-forward')->color('warning')
                        ->modalDescription('Semua lapangan jadwal ini di tanggal itu dilewati. Booking yang sudah dibuat dibatalkan dan jam kuotanya dikembalikan.')
                        ->schema(fn (PadelRecurringSchedule $record) => [
                            Select::make('date')->label('Tanggal')->required()->native(false)
                                ->options(self::upcomingDateOptions($record)),
                            Textarea::make('reason')->label('Alasan')->required()->minLength(3)->maxLength(255)->rows(2)
                                ->placeholder('Contoh: Grand Opening'),
                        ])
                        ->action(fn (PadelRecurringSchedule $record, array $data) => self::attempt(function () use ($record, $data) {
                            $cancelled = app(RecurringScheduleService::class)->skip($record, (string) $data['date'], (string) $data['reason'], auth()->user());

                            return 'Tanggal dilewati'.($cancelled ? " — {$cancelled} booking dibatalkan, kuota jam dikembalikan." : '.');
                        })),
                    Action::make('unskip')->label('Batal lewati')->icon('heroicon-o-arrow-uturn-left')->color('gray')
                        ->visible(fn (PadelRecurringSchedule $r) => $r->occurrences()->where('status', PadelRecurringOccurrence::STATUS_SKIPPED)
                            ->whereDate('occurrence_date', '>=', now()->toDateString())->exists())
                        ->modalDescription('Tanggal ini dibuatkan booking lagi saat "Buat booking sekarang" / proses otomatis malam berikutnya (kalau slotnya masih kosong).')
                        ->schema(fn (PadelRecurringSchedule $record) => [
                            Select::make('date')->label('Tanggal yang dilewati')->required()->native(false)
                                ->options($record->occurrences()->where('status', PadelRecurringOccurrence::STATUS_SKIPPED)
                                    ->whereDate('occurrence_date', '>=', now()->toDateString())->orderBy('occurrence_date')->get()
                                    ->mapWithKeys(fn (PadelRecurringOccurrence $o) => [$o->occurrence_date->toDateString() => $o->occurrence_date->translatedFormat('l, d M Y').($o->note ? ' — '.$o->note : '')])
                                    ->all()),
                        ])
                        ->action(fn (PadelRecurringSchedule $record, array $data) => self::attempt(function () use ($record, $data) {
                            app(RecurringScheduleService::class)->unskip($record, (string) $data['date'], auth()->user());

                            return 'Tanggal tidak jadi dilewati.';
                        })),
                    Action::make('toggle')
                        ->label(fn (PadelRecurringSchedule $r) => $r->isActive() ? 'Jeda' : 'Aktifkan')
                        ->icon(fn (PadelRecurringSchedule $r) => $r->isActive() ? 'heroicon-o-pause' : 'heroicon-o-play')
                        ->requiresConfirmation()
                        ->modalDescription(fn (PadelRecurringSchedule $r) => $r->isActive()
                            ? 'Booking baru berhenti dibuat. Booking yang sudah ada tidak berubah (lewati tanggalnya kalau perlu dibatalkan).'
                            : 'Booking minggu berikutnya dibuat lagi otomatis.')
                        ->action(function (PadelRecurringSchedule $record) {
                            $record->update(['status' => $record->isActive() ? PadelRecurringSchedule::STATUS_PAUSED : PadelRecurringSchedule::STATUS_ACTIVE]);
                            Notification::make()->title($record->isActive() ? 'Jadwal diaktifkan.' : 'Jadwal dijeda.')->success()->send();
                        }),
                    EditAction::make()->modalWidth('3xl')
                        ->mutateRecordDataUsing(fn (array $data, PadelRecurringSchedule $record) => RecurringScheduleService::formState($record))
                        ->mutateDataUsing(fn (array $data, PadelRecurringSchedule $record, EditAction $action) => self::normalizeOrHalt($data, $action, $record)),
                    DeleteAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePadelRecurringSchedules::route('/')];
    }

    /** Dipakai CreateAction & EditAction: periksa isian di server; gagal → notifikasi, modal tetap terbuka. */
    public static function normalizeOrHalt(array $data, Action $action, ?PadelRecurringSchedule $record = null): array
    {
        try {
            return app(RecurringScheduleService::class)->normalize($data, $record);
        } catch (HttpException $e) {
            Notification::make()->title('Belum bisa disimpan')->body($e->getMessage())->danger()->send();
            $action->halt();
        }
    }

    public static function notifyGenerated(array $result): void
    {
        if ($result['busy']) {
            Notification::make()->title('Sedang diproses')->body('Jadwal ini sedang dibuat oleh proses lain. Coba lagi sebentar.')->warning()->send();

            return;
        }

        $notification = Notification::make()->title($result['booked'].' booking dibuat');
        if ($result['failed'] !== []) {
            $notification->body('Gagal: '.implode(' · ', array_slice($result['failed'], 0, 5)).(count($result['failed']) > 5 ? ' …' : ''))->warning()->persistent();
        } else {
            $notification->body($result['booked'] ? null : 'Semua minggu dalam jangkauan sudah dibuat / dilewati.')->success();
        }
        $notification->send();
    }

    private static function attempt(callable $callback): void
    {
        try {
            Notification::make()->title($callback())->success()->send();
        } catch (HttpException $e) {
            Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
        }
    }

    /** Tanggal main 12 minggu ke depan (hari jadwal ini) untuk "Lewati tanggal". */
    private static function upcomingDateOptions(PadelRecurringSchedule $schedule): array
    {
        $options = [];
        $date = $schedule->firstDateOnOrAfter(now()->startOfDay()->max($schedule->starts_on->copy()->startOfDay()));
        for ($i = 0; $i < 12; $i++, $date = $date->copy()->addWeek()) {
            if ($schedule->ends_on && $date->gt($schedule->ends_on)) {
                break;
            }
            $options[$date->toDateString()] = $date->translatedFormat('l, d M Y');
        }

        return $options;
    }

    private static function courtNames(PadelRecurringSchedule $schedule): string
    {
        static $courts = null;
        $courts ??= PadelCourt::pluck('name', 'id');

        return collect((array) $schedule->court_ids)->map(fn ($id) => $courts[$id] ?? '?')->implode(', ');
    }

    private static function num(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }
}
