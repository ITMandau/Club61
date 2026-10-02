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

    protected static string|UnitEnum|null $navigationGroup = 'Main Menu';

    protected static ?int $navigationSort = 6;

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
        return (bool) auth()->user()?->can('manage_membership_facilities') && ! $record->is_system;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                TextInput::make('code')
                    ->label('Kode')
                    ->helperText('Huruf besar / angka / garis bawah, maks. 10 karakter. Tidak bisa diubah setelah disimpan.')
                    ->required()
                    ->maxLength(10)
                    ->regex('/^[A-Z0-9_]+$/')
                    // Divalidasi (format & unik) dalam bentuk huruf besar — yang disimpan juga huruf besar.
                    ->mutateStateForValidationUsing(fn (?string $state) => strtoupper(trim((string) $state)))
                    ->dehydrateStateUsing(fn (?string $state) => strtoupper(trim((string) $state)))
                    ->unique(ignoreRecord: true)
                    ->disabled(fn (?MembershipFacility $record) => $record !== null),
                TextInput::make('name')
                    ->label('Nama Fasilitas')
                    ->placeholder('Contoh: Kolam Renang')
                    ->required()
                    ->maxLength(100),
                TextInput::make('badge')
                    ->label('Label Singkat (ikon kartu)')
                    ->placeholder('Contoh: POOL')
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
                    // Dikunci untuk fasilitas sistem, dan untuk fasilitas yang sudah dipakai di paket (kuota yang sudah
                    // dijual tidak boleh berubah arti, mis. dari "kunjungan" jadi "info saja").
                    ->disabled(fn (?MembershipFacility $record) => $record !== null && ($record->is_system
                        || \App\Models\Membership\MembershipPlanBenefit::where('facility', $record->code)->exists()))
                    ->helperText('Fasilitas sistem (Padel, Gym, Sauna) terhubung ke booking & check-in yang sudah ada, jadi caranya dikunci. Fasilitas yang sudah dipakai di paket juga dikunci.'),
                Textarea::make('description')
                    ->label('Deskripsi untuk Customer')
                    ->helperText('Tampil di kartu benefit halaman membership. Bisa ditimpa per paket lewat "Catatan untuk customer".')
                    ->rows(3)
                    ->maxLength(500)
                    ->columnSpanFull(),
                TextInput::make('sort_order')
                    ->label('Urutan Tampil')
                    ->integer()
                    ->minValue(0)
                    ->maxValue(9999)
                    ->default(10),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->helperText('Nonaktif = tidak bisa dipilih di paket baru & disembunyikan dari halaman penjualan.')
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
                EditAction::make(),
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
