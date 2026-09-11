<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class KelolaTurnamen extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationLabel = 'Kelola Turnamen';

    protected static string | UnitEnum | null $navigationGroup = 'Main Menu';

    protected static ?string $title = 'Kelola Turnamen & Event';

    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.pages.kelola-turnamen';
}
