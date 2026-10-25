<?php

namespace App\Filament\Resources\Marketing\Pages;

use App\Filament\Resources\Marketing\LinkBioLinkResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLinkBioLinks extends ManageRecords
{
    protected static string $resource = LinkBioLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open')->label('Buka Halaman Link Bio')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
                ->url(route('link-bio'), shouldOpenInNewTab: true),
            CreateAction::make()->label('Tambah Tombol')->modalWidth('2xl'),
        ];
    }
}
