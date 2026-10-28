<?php

namespace App\Filament\Resources\Staff;

use App\Filament\Resources\Concerns\AuthorizesWithCanMethods;
use App\Filament\Resources\Staff\Pages\ManageGuides;
use App\Models\Staff\Guide;
use App\Services\Staff\GuidePdfRenderer;
use App\Services\Staff\GuideService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\HttpException;
use UnitEnum;

/**
 * Karyawan & Akses → Panduan: modul / SOP (mis. panduan resepsionis) dalam PDF, dibagikan lewat link tanpa login yang
 * hanya menampilkan gambar halaman ber-watermark (GuideController). Seperti Digital Signage: link bisa dinonaktifkan dan
 * dibuat ulang. Semua aksi butuh izin manage_guides.
 */
class GuideResource extends Resource
{
    use AuthorizesWithCanMethods;

    public const MANAGE = 'manage_guides';

    protected static ?string $model = Guide::class;

    protected static ?string $slug = 'panduan';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Panduan';

    protected static ?string $modelLabel = 'Panduan';

    protected static ?string $pluralModelLabel = 'Panduan Karyawan';

    protected static string|UnitEnum|null $navigationGroup = 'Karyawan & Akses';

    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->can(self::MANAGE);
    }

    public static function canCreate(): bool
    {
        return self::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return self::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return self::canViewAny();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('title')->label('Judul panduan')->required()->maxLength(150)
                    ->placeholder('Contoh: Panduan Modul Resepsionis'),
                TextInput::make('version')->label('Versi (opsional)')->maxLength(30)->placeholder('Contoh: v1.0 — Okt 2026'),
            ]),
            Textarea::make('description')->label('Deskripsi (opsional)')->rows(2)->maxLength(1000)
                ->placeholder('Isi singkat & untuk siapa panduan ini.'),
            FileUpload::make('file')->label(fn (string $operation) => $operation === 'create' ? 'File PDF' : 'Ganti file PDF (opsional)')
                ->disk(Guide::DISK)->directory('guides/uploads')->visibility('private')
                ->acceptedFileTypes(['application/pdf'])->maxSize(30 * 1024)
                ->storeFileNamesIn('source_name')
                ->required(fn (string $operation) => $operation === 'create')
                ->helperText('PDF, maks 30 MB & '.GuidePdfRenderer::MAX_PAGES.' halaman. PowerPoint: File → Save As → PDF. Setelah diubah jadi gambar, file PDF-nya dihapus dari server.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('accessLogs')->withMax('accessLogs', 'viewed_at'))
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Belum ada panduan')
            ->emptyStateDescription('Unggah PDF panduan (mis. modul resepsionis), cek lewat Pratinjau, lalu aktifkan dan bagikan link-nya.')
            ->columns([
                TextColumn::make('title')->label('Panduan')->weight('bold')->searchable()
                    ->description(fn (Guide $r) => $r->description ? str($r->description)->limit(90)->toString() : null),
                TextColumn::make('version')->label('Versi')->placeholder('—'),
                TextColumn::make('pages_count')->label('Halaman')
                    ->formatStateUsing(fn (int $state) => $state.' halaman')
                    ->description(fn (Guide $r) => $r->source_name ? str($r->source_name)->limit(40)->toString() : null),
                TextColumn::make('is_active')->label('Link')->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Aktif' : 'Nonaktif')
                    ->color(fn (bool $state) => $state ? 'success' : 'gray'),
                TextColumn::make('access_logs_count')->label('Dibuka')
                    ->formatStateUsing(fn (int $state) => $state.'×')
                    ->description(fn (Guide $r) => $r->access_logs_max_viewed_at
                        ? 'Terakhir '.\Carbon\Carbon::parse($r->access_logs_max_viewed_at)->locale('id')->diffForHumans()
                        : 'Belum pernah'),
            ])
            ->recordActions([
                Action::make('link')->label('Link')->icon('heroicon-o-link')->color('gray')
                    ->modalHeading(fn (Guide $r) => 'Link '.$r->title)
                    ->modalSubmitAction(false)->modalCancelActionLabel('Tutup')->modalWidth('xl')
                    ->modalContent(fn (Guide $r) => view('filament.resources.staff.guide-link', ['guide' => $r])),
                Action::make('preview')->label('Pratinjau')->icon('heroicon-o-eye')->color('gray')
                    ->url(fn (Guide $r) => route('guide.preview', $r))->openUrlInNewTab(),
                ActionGroup::make([
                    Action::make('toggle')
                        ->label(fn (Guide $r) => $r->is_active ? 'Nonaktifkan link' : 'Aktifkan link')
                        ->icon(fn (Guide $r) => $r->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
                        ->color(fn (Guide $r) => $r->is_active ? 'warning' : 'success')
                        ->authorize(fn () => self::canViewAny())
                        ->requiresConfirmation()
                        ->modalDescription(fn (Guide $r) => $r->is_active
                            ? 'Link langsung tidak bisa dibuka, termasuk oleh yang sedang membaca. Link yang sama hidup lagi saat diaktifkan.'
                            : 'Siapa pun yang memegang link bisa membaca panduan ini (tanpa login, tanpa unduh).')
                        ->action(function (Guide $record) {
                            app(GuideService::class)->setActive($record, ! $record->is_active);
                            Notification::make()->title($record->is_active ? 'Link panduan aktif' : 'Link panduan dinonaktifkan')->success()->send();
                        }),
                    Action::make('regenerate')->label('Buat ulang link')->icon('heroicon-o-arrow-path')->color('danger')
                        ->authorize(fn () => self::canViewAny())
                        ->requiresConfirmation()
                        ->modalDescription('Link lama mati permanen (termasuk yang sedang membaca) dan diganti link baru. Pakai ini kalau link sudah tersebar ke orang yang salah.')
                        ->action(function (Guide $record) {
                            app(GuideService::class)->regenerateToken($record, auth()->user());
                            Notification::make()->title('Link baru dibuat')->body('Link lama tidak berlaku lagi — bagikan link baru dari tombol Link.')->warning()->send();
                        }),
                    Action::make('access')->label('Log akses')->icon('heroicon-o-list-bullet')->color('gray')
                        ->modalHeading(fn (Guide $r) => 'Log akses '.$r->title)
                        ->modalSubmitAction(false)->modalCancelActionLabel('Tutup')->modalWidth('2xl')
                        ->modalContent(fn (Guide $r) => view('filament.resources.staff.guide-access', [
                            'logs' => $r->accessLogs()->orderByDesc('viewed_at')->limit(100)->get(),
                            'total' => $r->accessLogs()->count(),
                            'ips' => $r->accessLogs()->distinct()->count('ip'),
                        ])),
                    EditAction::make()->modalWidth('2xl')
                        ->mutateRecordDataUsing(fn (array $data) => array_intersect_key($data, array_flip(['title', 'description', 'version'])))
                        ->using(fn (Guide $record, array $data, EditAction $action) => self::attempt($action, fn () => app(GuideService::class)
                            ->update($record, $data, $data['file'] ?? null, $data['source_name'] ?? null)))
                        ->successNotificationTitle('Panduan disimpan'),
                    DeleteAction::make()->modalDescription('Panduan, gambar halaman & log aksesnya dihapus permanen. Link langsung mati.'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageGuides::route('/')];
    }

    /** Dipakai Tambah & Edit: gagal ubah PDF (bukan PDF / rusak / server belum siap) → notifikasi, modal tetap terbuka. */
    public static function attempt(Action $action, \Closure $callback): Guide
    {
        try {
            return $callback();
        } catch (HttpException $e) {
            Notification::make()->title('Panduan belum bisa disimpan')->body($e->getMessage())->danger()->persistent()->send();
            $action->halt();
        }
    }
}
