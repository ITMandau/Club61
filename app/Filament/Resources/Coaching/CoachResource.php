<?php

namespace App\Filament\Resources\Coaching;

use App\Filament\Resources\Coaching\Pages\ManageCoaches;
use App\Filament\Resources\Concerns\AuthorizesWithCanMethods;
use App\Models\Coaching\Coach;
use App\Services\Media\SecureImageUploader;
use App\Support\PhoneNumber;
use App\Support\PublicStorageUrl;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

/**
 * Data coach partner (Modul 24 Opsi B) — dipilih resepsionis saat booking coaching di POS Walk-In. Coach yang sudah
 * dipakai booking tidak bisa dihapus (riwayat), cukup dinonaktifkan; coach nonaktif tidak bisa dipilih lagi.
 * Tahap 2: bagian "Profil untuk Customer" (foto, olahraga, poin keunggulan) — tampil di halaman Coaching customer &
 * API aplikasi hanya kalau "Tampilkan di profil" dinyalakan.
 */
class CoachResource extends Resource
{
    use AuthorizesWithCanMethods;

    protected static ?string $model = Coach::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Coach';

    protected static ?string $modelLabel = 'Coach';

    protected static ?string $pluralModelLabel = 'Coach';

    protected static string|UnitEnum|null $navigationGroup = 'Coaching';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->can('manage_coaching');
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->can('manage_coaching');
    }

    public static function canEdit(Model $record): bool
    {
        return (bool) auth()->user()?->can('manage_coaching');
    }

    public static function canDelete(Model $record): bool
    {
        return (bool) auth()->user()?->can('manage_coaching') && ! $record->isUsed();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'sm' => 2])->columnSpanFull()->schema([
                TextInput::make('name')->label('Nama Coach')->required()->maxLength(100),
                Select::make('title')->label('Jabatan')->options(Coach::TITLES)->default('COACH')->required()->native(false),
                Select::make('academy_id')->label('Academy')->relationship('academy', 'name')->searchable()->preload()->placeholder('Coach independen'),
                TextInput::make('phone')->label('No. WhatsApp')->tel()->maxLength(20)->helperText('Dipakai untuk tombol pengingat jadwal. Tidak ditampilkan ke customer.')
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? (PhoneNumber::normalize((string) $state) ?? trim((string) $state)) : null),
                TextInput::make('email')->label('Email')->email()->maxLength(150),
                TextInput::make('sort_order')->label('Urutan')->integer()->minValue(0)->maxValue(999)->default(0),
                Textarea::make('notes')->label('Catatan internal')->rows(2)->maxLength(1000)->columnSpanFull(),
                Toggle::make('is_active')->label('Aktif (bisa dipilih di POS)')->default(true)->inline(false),
            ]),
            Section::make('Profil untuk Customer')
                ->description('Tampil di menu Coaching portal customer & aplikasi. No. WhatsApp, email, dan catatan internal tidak ikut tampil.')
                ->columnSpanFull()
                ->collapsible()
                ->schema([
                    Grid::make(['default' => 1, 'sm' => 2])->schema([
                        FileUpload::make('photo_path')->label('Foto')
                            ->image()->disk('public')->directory('coaches')->visibility('public')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(4096)
                            ->helperText('JPG / PNG / WEBP, maks. 4 MB. Disarankan foto potret.')
                            // Semua upload gambar lewat SecureImageUploader (decode ulang → JPEG baru, EXIF dibuang).
                            ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file) => SecureImageUploader::store($file, 'coaches'))
                            ->getUploadedFileUsing(PublicStorageUrl::uploadedFile(...)),
                        Grid::make(1)->schema([
                            TextInput::make('sport')->label('Olahraga')->default('Padel')->required()->maxLength(50),
                            Toggle::make('show_on_profile')->label('Tampilkan di profil customer')->default(false)->inline(false)
                                ->helperText('Hanya coach aktif yang tampil.'),
                        ]),
                    ]),
                    Textarea::make('motto')->label('Motto / kutipan (opsional)')->rows(2)->autosize()->maxLength(150)
                        ->placeholder('mis. Main cerdas, bukan cuma keras.')
                        ->helperText('Boleh dikosongkan — kalau kosong tidak tampil. Enter untuk baris baru (maks. 3 baris).')
                        ->dehydrateStateUsing(fn ($state) => Coach::cleanMotto($state)),
                    Repeater::make('highlights')->label('Poin keunggulan')
                        ->simple(TextInput::make('text')->label('Poin')->required()->maxLength(120)->placeholder('mis. Sertifikasi pelatih padel'))
                        ->maxItems(Coach::MAX_HIGHLIGHTS)->defaultItems(0)->reorderable()
                        ->addActionLabel('Tambah poin'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('photo_path')->label('')->circular()->imageSize(36)
                    ->state(fn (Coach $c) => PublicStorageUrl::for($c->photo_path)),
                TextColumn::make('name')->label('Coach')->weight('bold')->searchable()->sortable()
                    ->description(fn (Coach $c) => $c->titleLabel()),
                TextColumn::make('academy.name')->label('Academy')->placeholder('Independen'),
                TextColumn::make('phone')->label('WhatsApp')->placeholder('-'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                IconColumn::make('show_on_profile')->label('Di profil')->boolean(),
            ])
            ->filters([
                SelectFilter::make('academy_id')->label('Academy')->relationship('academy', 'name'),
                SelectFilter::make('is_active')->label('Status')->options(['1' => 'Aktif', '0' => 'Nonaktif']),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCoaches::route('/')];
    }
}
