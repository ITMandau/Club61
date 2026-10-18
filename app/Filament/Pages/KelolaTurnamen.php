<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\IsComingSoon;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use UnitEnum;

class KelolaTurnamen extends Page
{
    use HasPageShield;
    use IsComingSoon;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationLabel = 'Kelola Turnamen';

    protected static string | UnitEnum | null $navigationGroup = 'Marketing & Event';

    protected static ?string $title = 'Kelola Turnamen & Event';

    protected static ?int $navigationSort = 2;

    protected static function comingSoon(): array
    {
        return [
            'eyebrow' => 'Modul Turnamen • Event Management',
            'sub' => 'Turnamen dan event padel Club 61.',
            'planned' => [
                'Buat turnamen: kategori, tanggal, kuota tim, dan biaya pendaftaran.',
                'Pendaftaran tim ganda (doubles) oleh member.',
                'Bagan pertandingan (bracket) dan hasil pertandingan.',
                'Hadiah dan sponsor turnamen.',
            ],
            'links' => [],
        ];
    }
}
