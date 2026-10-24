<?php

namespace App\Filament\Resources\Marketing;

use App\Filament\Resources\Concerns\AuthorizesWithCanMethods;
use App\Filament\Resources\Marketing\Pages\ManageLinkBioLinks;
use App\Models\Marketing\LinkBioLink;
use App\Models\Setting\CompanyProfileSetting;
use App\Services\Media\SecureImageUploader;
use App\Support\PublicStorageUrl;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

/**
 * Tombol halaman link bio Instagram (/links) — Modul 24 Opsi B Tahap 2. Tujuan tombol otomatis diambil dari data yang
 * sudah ada (WA admin coaching dari Pengaturan Coaching, WA admin Club & alamat dari Konten Website); media sosial
 * tampil otomatis dari Konten Website. Link ketikan admin wajib http/https.
 */
class LinkBioLinkResource extends Resource
{
    use AuthorizesWithCanMethods;

    protected static ?string $model = LinkBioLink::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationLabel = 'Link Bio';

    protected static ?string $modelLabel = 'Tombol Link Bio';

    protected static ?string $pluralModelLabel = 'Tombol Link Bio';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing & Event';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->can('manage_link_bio');
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->can('manage_link_bio');
    }

    public static function canEdit(Model $record): bool
    {
        return (bool) auth()->user()?->can('manage_link_bio');
    }

    public static function canDelete(Model $record): bool
    {
        return (bool) auth()->user()?->can('manage_link_bio');
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'sm' => 2])->columnSpanFull()->schema([
                TextInput::make('label')->label('Tulisan tombol')->required()->maxLength(60),
                Select::make('type')->label('Tujuan')->options(LinkBioLink::TYPES)->required()->native(false)->live()
                    ->helperText(fn (Get $get) => self::typeHelp($get('type'))),
                TextInput::make('url')->label(fn (Get $get) => $get('type') === 'LOCATION' ? 'Link Google Maps (opsional)' : 'Link tujuan')
                    ->visible(fn (Get $get) => in_array($get('type'), ['URL', 'LOCATION'], true))
                    ->required(fn (Get $get) => $get('type') === 'URL')
                    ->maxLength(300)->placeholder('https://…')->columnSpanFull()
                    ->rule(fn () => function (string $attribute, $value, Closure $fail) {
                        if (filled($value) && ! CompanyProfileSetting::isSafeWebUrl($value)) {
                            $fail('Link harus diawali http:// atau https://.');
                        }
                    }),
                FileUpload::make('image_path')->label('Gambar')
                    ->visible(fn (Get $get) => $get('type') === 'IMAGE')
                    ->required(fn (Get $get) => $get('type') === 'IMAGE')
                    ->image()->disk('public')->directory('link-bio')->visibility('public')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(6144)
                    ->helperText('JPG / PNG / WEBP, maks. 6 MB.')
                    ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file) => SecureImageUploader::store($file, 'link-bio'))
                    ->getUploadedFileUsing(PublicStorageUrl::uploadedFile(...))
                    ->columnSpanFull(),
                TextInput::make('sort_order')->label('Urutan')->integer()->minValue(0)->maxValue(999)->default(0),
                Toggle::make('is_active')->label('Tampil')->default(true)->inline(false),
            ]),
        ]);
    }

    private static function typeHelp(?string $type): ?string
    {
        return match ($type) {
            'BOOK_COURT' => 'Ke halaman booking (customer diminta login dulu).',
            'LOCATION' => 'Kosongkan untuk memakai alamat di Konten Website.',
            'COACHING_WA' => 'Nomor dari Coaching → Tarif Setoran → Pengaturan Coaching.',
            'CLUB_WA' => 'Nomor dari Konten Website → Lokasi & Kontak.',
            'COACHES' => 'Ke halaman profil coach (customer diminta login dulu).',
            default => null,
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('label')->label('Tombol')->weight('bold'),
                TextColumn::make('type')->label('Tujuan')->formatStateUsing(fn (LinkBioLink $record) => $record->typeLabel())
                    ->description(fn (LinkBioLink $record) => $record->href() ? null : 'Belum tampil — tujuan belum diisi'),
                IconColumn::make('is_active')->label('Tampil')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageLinkBioLinks::route('/')];
    }
}
