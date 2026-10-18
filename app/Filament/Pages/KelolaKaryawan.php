<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\IsComingSoon;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Users\UserResource;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use UnitEnum;

class KelolaKaryawan extends Page
{
    use HasPageShield;
    use IsComingSoon;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Kelola Karyawan';

    protected static string | UnitEnum | null $navigationGroup = 'Karyawan & Akses';

    protected static ?string $title = 'Kelola Karyawan & Staff';

    protected static ?int $navigationSort = 1;

    protected static function comingSoon(): array
    {
        return [
            'eyebrow' => 'Modul Karyawan • Staff Directory',
            'sub' => 'Direktori karyawan dan pelatih padel Club 61.',
            'planned' => [
                'Direktori karyawan & pelatih (coach) beserta jabatannya.',
                'Tarif sesi dan komisi jasa pelatih.',
                'Jadwal shift staf.',
            ],
            'links' => [
                ['label' => 'Kelola Pengguna', 'sub' => 'Buat & atur akun login staf.', 'page' => UserResource::class],
                ['label' => 'Roles & Hak Akses', 'sub' => 'Atur menu yang boleh dibuka tiap jabatan.', 'page' => RoleResource::class],
            ],
        ];
    }
}
