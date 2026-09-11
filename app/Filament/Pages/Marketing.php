<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class Marketing extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationLabel = 'Marketing';

    protected static string | UnitEnum | null $navigationGroup = 'Main Menu';

    protected static ?string $title = 'Marketing & Promosi';

    protected static ?int $navigationSort = 9;

    protected string $view = 'filament.pages.marketing';
}
