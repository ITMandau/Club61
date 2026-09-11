<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class MasterData extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?string $navigationLabel = 'Master Data';

    protected static string | UnitEnum | null $navigationGroup = 'Main Menu';

    protected static ?string $title = 'Master Data & Tarif';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.master-data';
}
