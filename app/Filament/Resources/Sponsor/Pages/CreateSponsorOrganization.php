<?php

namespace App\Filament\Resources\Sponsor\Pages;

use App\Filament\Resources\Sponsor\SponsorOrganizationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSponsorOrganization extends CreateRecord
{
    protected static string $resource = SponsorOrganizationResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
