<?php

namespace App\Filament\Pages;

use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use UnitEnum;

class KelolaKaryawan extends Page
{
    use HasPageShield;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Kelola Karyawan';

    protected static string | UnitEnum | null $navigationGroup = 'Main Menu';

    protected static ?string $title = 'Kelola Karyawan & Staff';

    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.pages.kelola-karyawan';
}
