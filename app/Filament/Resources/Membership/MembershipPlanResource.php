<?php

namespace App\Filament\Resources\Membership;

use App\Filament\Resources\Membership\Pages\CreateMembershipPlan;
use App\Filament\Resources\Membership\Pages\EditMembershipPlan;
use App\Filament\Resources\Membership\Pages\ListMembershipPlans;
use App\Models\Membership\MembershipPlan;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class MembershipPlanResource extends Resource
{
    protected static ?string $model = MembershipPlan::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationLabel = 'Paket Membership';

    protected static string|UnitEnum|null $navigationGroup = 'Main Menu';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Utama Paket')
                    ->description('Tentukan identitas paket, kode sistem, masa berlaku, dan harga jual keanggotaan.')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 3,
                        ])->schema([
                            TextInput::make('code')
                                ->label('Kode Paket')
                                ->placeholder('Contoh: MBR-SILVER')
                                ->required()
                                ->maxLength(30)
                                ->unique(ignoreRecord: true),

                            TextInput::make('name')
                                ->label('Nama Paket')
                                ->placeholder('Contoh: Silver Padel Addict')
                                ->required()
                                ->maxLength(150),

                            Select::make('ownership_type')
                                ->label('Tipe Kepemilikan')
                                ->options([
                                    'INDIVIDUAL' => 'Individual (Perorangan)',
                                    'ORGANIZATIONAL' => 'Organizational (Perusahaan / Sponsor)',
                                ])
                                ->default('INDIVIDUAL')
                                ->required(),

                            TextInput::make('duration_days')
                                ->label('Masa Aktif (Hari)')
                                ->numeric()
                                ->default(30)
                                ->suffix('Hari')
                                ->required(),

                            TextInput::make('price')
                                ->label('Harga (Rp)')
                                ->numeric()
                                ->prefix('Rp')
                                ->required(),

                            Toggle::make('is_active')
                                ->label('Status Aktif')
                                ->helperText('Paket dapat dibeli oleh customer jika aktif')
                                ->default(true),
                        ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Matriks Entitlement Fasilitas (Padel, Gym, Sauna)')
                    ->description('Konfigurasikan kuota, diskon bermain, prioritas reservasi, serta batasan jam akses untuk masing-masing fasilitas.')
                    ->schema([
                        Repeater::make('benefits')
                            ->relationship('benefits')
                            ->itemLabel(fn (array $state): ?string => match ($state['facility'] ?? null) {
                                'PADEL' => 'Benefit Fasilitas: Padel Court',
                                'GYM' => 'Benefit Fasilitas: Fitness & Gym',
                                'SAUNA' => 'Benefit Fasilitas: Sauna & Ice Bath',
                                default => 'Benefit Fasilitas',
                            })
                            ->addActionLabel('Tambah Fasilitas / Benefit')
                            ->collapsible()
                            ->collapsed(false)
                            ->schema([
                                Select::make('facility')
                                    ->label('Fasilitas')
                                    ->options([
                                        'PADEL' => 'Padel Court',
                                        'GYM' => 'Fitness & Gym',
                                        'SAUNA' => 'Sauna & Ice Bath',
                                    ])
                                    ->required(),

                                Select::make('quota_type')
                                    ->label('Tipe Kuota')
                                    ->options([
                                        'HOURS' => 'Jam Bermain (Hours)',
                                        'VISITS' => 'Sesi Kunjungan (Visits)',
                                        'NONE' => 'Tanpa Kuota (Diskon / Unlimited)',
                                    ])
                                    ->required(),

                                TextInput::make('quota_value')
                                    ->label('Besaran Kuota')
                                    ->numeric()
                                    ->helperText('Kosongkan untuk akses unlimited gym/sauna'),

                                TextInput::make('discount_percent')
                                    ->label('Diskon Biaya Lapangan / Sesi (%)')
                                    ->numeric()
                                    ->default(0)
                                    ->suffix('%'),

                                TextInput::make('booking_priority_days')
                                    ->label('Prioritas Booking (Hari Lebih Awal)')
                                    ->numeric()
                                    ->default(0),

                                TimePicker::make('time_window_start')
                                    ->label('Jam Akses Mulai (Opsional)'),

                                TimePicker::make('time_window_end')
                                    ->label('Jam Akses Selesai (Opsional)'),
                            ])
                            ->columns([
                                'default' => 1,
                                'md' => 3,
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('name')
                    ->label('Nama Paket')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('ownership_type')
                    ->label('Tipe')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'ORGANIZATIONAL' ? 'info' : 'gray'),

                TextColumn::make('duration_days')
                    ->label('Masa Aktif')
                    ->suffix(' Hari')
                    ->sortable(),

                TextColumn::make('price')
                    ->label('Harga')
                    ->money('IDR', locale: 'id')
                    ->sortable(),

                TextColumn::make('benefits.facility')
                    ->label('Cakupan Fasilitas')
                    ->badge()
                    ->separator(', '),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMembershipPlans::route('/'),
            'create' => CreateMembershipPlan::route('/create'),
            'edit' => EditMembershipPlan::route('/{record}/edit'),
        ];
    }
}
