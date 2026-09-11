<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class KelolaClub extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Kelola Club';

    protected static string | UnitEnum | null $navigationGroup = 'Main Menu';

    protected static ?string $title = 'Kelola Fasilitas Club';

    protected static ?int $navigationSort = 8;

    protected string $view = 'filament.pages.kelola-club';
}
