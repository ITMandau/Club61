<?php

namespace App\Filament\Resources\Sponsor\Pages;

use App\Filament\Resources\Sponsor\SponsorAccessScheduleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSponsorAccessSchedule extends CreateRecord
{
    protected static string $resource = SponsorAccessScheduleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_user_id'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
