<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use UnitEnum;

class Dashboard extends BaseDashboard
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static string | UnitEnum | null $navigationGroup = 'Main Menu';

    protected static ?string $title = 'Dashboard';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.dashboard';

    public function getWidgets(): array
    {
        return [];
    }

    public function getColumns(): int | array
    {
        return 1;
    }
}
