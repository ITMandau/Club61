<?php

namespace App\Filament\Pages;

use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use UnitEnum;

class Kustomer extends Page
{
    use HasPageShield;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Kustomer';

    protected static string | UnitEnum | null $navigationGroup = 'Main Menu';

    protected static ?string $title = 'Data Kustomer & Member VIP';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.kustomer';
}
