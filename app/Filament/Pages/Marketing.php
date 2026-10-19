<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\IsComingSoon;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use UnitEnum;

/**
 * Marketing digital (iklan, materi promosi) — belum dibangun. Kode promo & voucher dikelola di Daftar Voucher.
 */
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
            'eyebrow' => 'Modul Marketing • Iklan Digital',
            'sub' => 'Materi promosi dan iklan digital Club 61.',
            'planned' => [
                'Upload materi promosi (banner, poster, video) untuk sosmed & layar venue.',
                'Kampanye iklan digital dan jadwal tayangnya.',
                'Pengumuman / notifikasi promo ke member.',
            ],
            'links' => [
                ['label' => 'Daftar Voucher', 'sub' => 'Buat & kelola kode promo, serta voucher saldo customer.', 'page' => DaftarVoucher::class],
            ],
        ];
    }
}
