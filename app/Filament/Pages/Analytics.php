<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class Analytics extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Analytics';

    protected static string | UnitEnum | null $navigationGroup = 'Main Menu';

    protected static ?string $title = 'Analytics & Performa';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.analytics';
}
