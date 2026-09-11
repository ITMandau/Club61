<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class KelolaKaryawan extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Kelola Karyawan';

    protected static string | UnitEnum | null $navigationGroup = 'Main Menu';

    protected static ?string $title = 'Kelola Karyawan & Staff';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.kelola-karyawan';
}
