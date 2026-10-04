<?php

namespace App\Filament\Resources\Membership\Facilities;

use App\Filament\Resources\Membership\Facilities\Pages\ManageMembershipFacilities;
use App\Models\Membership\MembershipFacility;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Master fasilitas membership: fasilitas yang bisa dimasukkan ke paket + nama & deskripsi yang dilihat customer.
 */
class MembershipFacilityResource extends Resource
{
    use \App\Filament\Resources\Concerns\AuthorizesWithCanMethods;

    protected static ?string $model = MembershipFacility::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-plus';

    protected static ?string $navigationLabel = 'Fasilitas Membership';

    protected static ?string $modelLabel = 'Fasilitas Membership';

    protected static ?string $pluralModelLabel = 'Fasilitas Membership';

    protected static string|UnitEnum|null $navigationGroup = 'Customer & Membership';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->can('view_membership_plans');
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->can('manage_membership_facilities');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return (bool) auth()->user()?->can('manage_membership_facilities');
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return (bool) auth()->user()?->can('manage_membership_facilities') && ! $record->is_system && ! $record->isInUse();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            // Root schema modal Filament 5 berkolom 2 — tanpa columnSpanFull grid ini hanya mengisi setengah modal.
            Grid::make(['default' => 1, 'sm' => 2])->columnSpanFull()->schema([
                TextInput::make('name')
                    ->label('Nama Fasilitas')
                    ->placeholder('Contoh: Kolam Renang')
                    ->required()
                    ->maxLength(100)
                    ->columnSpanFull(),
                TextInput::make('code')
                    ->label('Kode')
                    ->placeholder('Contoh: POOL')
                    ->helperText('A–Z, 0–9, _ · maks. 10 karakter · tidak bisa diubah setelah disimpan.')
                    ->required()
                    ->maxLength(10)
                    ->regex('/^[A-Z0-9_]+$/')
                    // Divalidasi (format & unik) dalam bentuk huruf besar — yang disimpan juga huruf besar.
                    ->mutateStateForValidationUsing(fn (?string $state) => strtoupper(trim((string) $state)))
                    ->dehydrateStateUsing(fn (?string $state) => strtoupper(trim((string) $state)))
                    ->unique(ignoreRecord: true)
                    ->disabled(fn (?MembershipFacility $record) => $record !== null),
                TextInput::make('badge')
                    ->label('Label Singkat')
                    ->placeholder('Contoh: POOL')
                    ->helperText('Tampil di ikon kartu benefit · maks. 8 karakter.')
                    ->required()
                    ->maxLength(8)
                    ->dehydrateStateUsing(fn (?string $state) => strtoupper(trim((string) $state))),
                Select::make('usage_mode')
                    ->label('Cara Pemakaian Kuota')
                    ->options(fn (?MembershipFacility $record) => $record?->is_system
                        ? [$record->usage_mode => self::modeLabel($record->usage_mode)]
                        : MembershipFacility::CUSTOM_MODES)
                    ->default(MembershipFacility::MODE_CHECK_IN)
                    ->required()
                    ->native(false)
                    // Dikunci untuk fasilitas sistem, dan untuk fasilitas yang sudah dipakai di paket (kuota yang sudah
                    // dijual tidak boleh berubah arti, mis. dari "kunjungan" jadi "info saja").
                    ->disabled(fn (?MembershipFacility $record) => $record !== null && ($record->is_system || $record->isInUse()))
                    ->helperText(fn (?MembershipFacility $record) => match (true) {
                        (bool) $record?->is_system => 'Fasilitas sistem terhubung ke booking & check-in yang sudah ada, jadi caranya dikunci.',
                        $record !== null && $record->isInUse() => 'Dikunci karena fasilitas ini sudah dipakai di paket / kartu member.',
                        default => 'Tidak bisa diubah lagi setelah fasilitas dipakai di paket.',
                    })
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label('Deskripsi untuk Customer')
                    ->placeholder('Contoh: Akses kolam renang indoor setiap hari 06.00–21.00.')
                    ->helperText('Tampil di kartu benefit halaman membership. Bisa ditimpa per paket lewat "Catatan untuk customer".')
                    ->rows(3)
                    ->maxLength(500)
                    ->columnSpanFull(),
                TextInput::make('sort_order')
                    ->label('Urutan Tampil')
                    ->helperText('Angka kecil tampil lebih dulu.')
                    ->required()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(9999)
                    ->default(10),
                Toggle::make('is_active')
                    ->label('Aktif Dijual')
                    ->inline(false)
                    ->helperText('Nonaktif = tidak bisa dipilih di paket baru. Kuota member lama tetap bisa dipakai.')
                    ->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('badge')->label('')->badge()->color(fn (MembershipFacility $r) => $r->is_active ? 'warning' : 'gray'),
                TextColumn::make('name')->label('Fasilitas')->weight('bold')->description(fn (MembershipFacility $r) => $r->description)->wrap()->searchable(),
                TextColumn::make('code')->label('Kode')->fontFamily('mono')->size('xs')->color('gray'),
                TextColumn::make('usage_mode')->label('Cara Pemakaian')->badge()->formatStateUsing(fn (string $state) => self::modeLabel($state, short: true))->color('gray'),
                IconColumn::make('is_system')->label('Sistem')->boolean(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('sort_order')->label('Urutan')->sortable(),
            ])
            ->recordActions([
                EditAction::make()->modalWidth('2xl'),
                DeleteAction::make()
                    ->modalDescription('Hanya bisa dihapus kalau belum dipakai di paket atau kartu member mana pun. Kalau sudah dipakai, nonaktifkan saja.'),
            ]);
    }

    public static function modeLabel(?string $mode, bool $short = false): string
    {
        return match ($mode) {
            MembershipFacility::MODE_PADEL_BOOKING => $short ? 'Booking Padel' : 'Booking lapangan padel (kuota jam dipotong saat booking)',
            MembershipFacility::MODE_WELLNESS_BOOKING => $short ? 'Booking Wellness' : 'Booking sesi wellness / sauna (kuota sesi dipotong saat booking)',
            MembershipFacility::MODE_CHECK_IN => $short ? 'Check-in' : MembershipFacility::CUSTOM_MODES[MembershipFacility::MODE_CHECK_IN],
            MembershipFacility::MODE_INFO => $short ? 'Info saja' : MembershipFacility::CUSTOM_MODES[MembershipFacility::MODE_INFO],
            default => (string) $mode,
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMembershipFacilities::route('/'),
        ];
    }
}
