<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class BookingSystem extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Booking System';

    protected static string | UnitEnum | null $navigationGroup = 'Main Menu';

    protected static ?string $title = 'Booking System Lapangan';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.booking-system';
}
