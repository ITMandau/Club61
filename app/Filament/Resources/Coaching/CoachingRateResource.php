<?php

namespace App\Filament\Resources\Coaching;

use App\Filament\Resources\Coaching\Pages\ManageCoachingRates;
use App\Filament\Resources\Concerns\AuthorizesWithCanMethods;
use App\Models\Coaching\CoachingRate;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
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
use UnitEnum;

/**
 * Tarif setoran coaching (Modul 24 Opsi B): aturan per hari + jam mulai slot + harga per jam, bisa diubah kapan saja
 * (berlaku untuk booking BARU & reschedule; booking yang sudah dibayar tidak berubah). Beberapa aturan cocok → prioritas
 * terkecil dipakai. Lihat CoachingPricingService.
 */
class CoachingRateResource extends Resource
{
    use AuthorizesWithCanMethods;

    protected static ?string $model = CoachingRate::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Tarif Setoran';

    protected static ?string $modelLabel = 'Tarif Setoran Coaching';

    protected static ?string $pluralModelLabel = 'Tarif Setoran Coaching';

    protected static string|UnitEnum|null $navigationGroup = 'Coaching';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->can('manage_coaching_rates') || $user?->can('manage_coaching'));
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->can('manage_coaching_rates');
    }

    public static function canEdit(Model $record): bool
    {
        return (bool) auth()->user()?->can('manage_coaching_rates');
    }

    public static function canDelete(Model $record): bool
    {
        return (bool) auth()->user()?->can('manage_coaching_rates');
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    /** @return array<string, string> "06:00" … ("24:00" hanya untuk jam selesai) */
    public static function hourOptions(bool $forEnd): array
    {
        $options = [];
        for ($h = $forEnd ? 1 : 0; $h <= ($forEnd ? 24 : 23); $h++) {
            $value = sprintf('%02d:00', $h);
            $options[$value] = $h === 24 ? '24:00 (tengah malam)' : $value;
        }

        return $options;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'sm' => 2])->columnSpanFull()->schema([
                TextInput::make('name')->label('Nama Tarif')->placeholder('Contoh: Malam / Weekend')->required()->maxLength(100)->columnSpanFull(),
                CheckboxList::make('days')->label('Hari berlaku')->options(CoachingRate::DAYS)->columns(4)->required()
                    ->in(array_keys(CoachingRate::DAYS))
                    ->default(array_keys(CoachingRate::DAYS))
                    ->dehydrateStateUsing(fn ($state) => array_values(array_map('intval', (array) $state)))
                    ->columnSpanFull(),
                Select::make('start_time')->label('Jam mulai (slot mulai jam ini)')->options(self::hourOptions(false))->required()->native(false)
                    ->in(array_keys(self::hourOptions(false))),
                Select::make('end_time')->label('Sampai sebelum jam')->options(self::hourOptions(true))->required()->native(false)
                    ->in(array_keys(self::hourOptions(true)))
                    ->rule(fn (Get $get) => function (string $attribute, $value, Closure $fail) use ($get) {
                        if ((string) $value <= (string) $get('start_time')) {
                            $fail('Jam selesai harus setelah jam mulai.');
                        }
                    }),
                TextInput::make('price_per_hour')->label('Tarif setoran / jam')->numeric()->prefix('Rp')->required()->minValue(1)->maxValue(100000000),
                TextInput::make('priority')->label('Prioritas (kecil = dipakai duluan)')->integer()->minValue(0)->maxValue(999)->default(10)->required()
                    ->helperText('Kalau dua tarif sama-sama cocok, yang angkanya lebih kecil yang dipakai (mis. tarif weekend khusus = 1).'),
                Toggle::make('is_active')->label('Aktif')->default(true)->inline(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('priority')
            ->columns([
                TextColumn::make('name')->label('Tarif')->weight('bold')
                    ->description(fn (CoachingRate $r) => $r->daysLabel()),
                TextColumn::make('start_time')->label('Jam')
                    ->formatStateUsing(fn (CoachingRate $r) => $r->start_time.' – '.$r->end_time),
                TextColumn::make('price_per_hour')->label('Setoran / jam')->money('IDR', locale: 'id')->alignEnd(),
                TextColumn::make('priority')->label('Prioritas')->alignEnd(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCoachingRates::route('/')];
    }
}
