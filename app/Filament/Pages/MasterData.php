<?php

namespace App\Filament\Pages;

use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use UnitEnum;

class MasterData extends Page
{
    use HasPageShield;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?string $navigationLabel = 'Master Data';

    protected static string | UnitEnum | null $navigationGroup = 'Main Menu';

    protected static ?string $title = 'Master Data & Tarif';

    protected static ?int $navigationSort = 11;

    protected string $view = 'filament.pages.master-data';
}
