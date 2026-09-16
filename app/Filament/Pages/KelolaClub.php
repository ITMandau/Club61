<?php

namespace App\Filament\Pages;

use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use UnitEnum;

class KelolaClub extends Page
{
    use HasPageShield;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Kelola Club';

    protected static string | UnitEnum | null $navigationGroup = 'Main Menu';

    protected static ?string $title = 'Kelola Fasilitas Club';

    protected static ?int $navigationSort = 9;

    protected string $view = 'filament.pages.kelola-club';
}
