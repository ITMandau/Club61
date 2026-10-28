<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\IsComingSoon;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use UnitEnum;

class KelolaClub extends Page
{
    use HasPageShield;
    use IsComingSoon;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Kelola Club';

    protected static string | UnitEnum | null $navigationGroup = 'Master Data';

    protected static ?string $title = 'Kelola Fasilitas Club';

    protected static ?int $navigationSort = 2;

    protected static function comingSoon(): array
    {
        return [
            'eyebrow' => 'Modul Fasilitas • Venue Operations',
            'sub' => 'Status operasional fasilitas venue: lapangan padel, wellness, dan lounge.',
            'planned' => [
                'Status tiap fasilitas (buka, penuh, tutup sementara).',
                'Jadwal maintenance & penutupan sementara per fasilitas.',
                'Kapasitas dan jam buka per fasilitas.',
            ],
            'links' => [
                ['label' => 'Master Data & Tarif', 'sub' => 'Lapangan padel, tarif per jam, dan alat sewa.', 'page' => MasterData::class],
                ['label' => 'Konten Website', 'sub' => 'Kartu fasilitas, jam buka, dan alamat di halaman depan.', 'page' => KelolaKontenWebsite::class],
            ],
        ];
    }
}
