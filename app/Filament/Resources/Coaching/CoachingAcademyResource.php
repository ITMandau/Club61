<?php

namespace App\Filament\Resources\Coaching;

use App\Filament\Resources\Coaching\Pages\ManageCoachingAcademies;
use App\Filament\Resources\Concerns\AuthorizesWithCanMethods;
use App\Models\Coaching\CoachingAcademy;
use App\Support\PhoneNumber;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Academy partner coaching (Modul 24 Opsi B). Academy yang masih punya coach tidak bisa dihapus — nonaktifkan saja. */
class CoachingAcademyResource extends Resource
{
    use AuthorizesWithCanMethods;

    protected static ?string $model = CoachingAcademy::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Academy';

    protected static ?string $modelLabel = 'Academy';

    protected static ?string $pluralModelLabel = 'Academy';

    protected static string|UnitEnum|null $navigationGroup = 'Coaching';

    protected static ?int $navigationSort = 3;

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
        return (bool) auth()->user()?->can('manage_coaching') && ! $record->coaches()->exists();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'sm' => 2])->columnSpanFull()->schema([
                TextInput::make('name')->label('Nama Academy')->placeholder('Contoh: SR Academy')->required()->maxLength(100),
                TextInput::make('pic_name')->label('Nama PIC')->maxLength(100),
                TextInput::make('phone')->label('No. WhatsApp PIC')->tel()->maxLength(20)
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? (PhoneNumber::normalize((string) $state) ?? trim((string) $state)) : null),
                Toggle::make('is_active')->label('Aktif')->default(true)->inline(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Academy')->weight('bold')->searchable()->sortable(),
                TextColumn::make('pic_name')->label('PIC')->placeholder('-'),
                TextColumn::make('phone')->label('WhatsApp')->placeholder('-'),
                TextColumn::make('coaches_count')->label('Coach')->counts('coaches'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCoachingAcademies::route('/')];
    }
}
