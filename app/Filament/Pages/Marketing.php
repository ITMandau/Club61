<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\IsComingSoon;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use UnitEnum;

class Marketing extends Page
{
    use HasPageShield;
    use IsComingSoon;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationLabel = 'Marketing';

    protected static string | UnitEnum | null $navigationGroup = 'Marketing & Event';

    protected static ?string $title = 'Marketing & Promosi';

    protected static ?int $navigationSort = 1;

    protected static function comingSoon(): array
    {
        return [
            'eyebrow' => 'Modul Marketing • Campaign & Promo',
            'sub' => 'Kode promo, kampanye diskon, dan pengumuman ke member.',
            'planned' => [
                'Kode promo & voucher diskon (Modul 22 — rancangan sudah ada).',
                'Kampanye promo dengan periode dan kuota pemakaian.',
                'Pengumuman / notifikasi promo ke member.',
            ],
            'links' => [
                ['label' => 'Daftar Voucher', 'sub' => 'Voucher saldo customer yang sudah terbit.', 'page' => DaftarVoucher::class],
            ],
        ];
    }
}
