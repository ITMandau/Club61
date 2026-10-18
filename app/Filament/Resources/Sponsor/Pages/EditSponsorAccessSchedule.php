<?php

namespace App\Filament\Resources\Sponsor\Pages;

use App\Filament\Resources\Sponsor\SponsorAccessScheduleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSponsorAccessSchedule extends EditRecord
{
    protected static string $resource = SponsorAccessScheduleResource::class;

    public function getSubheading(): ?string
    {
        return SponsorAccessScheduleResource::IN_PROGRESS_NOTE;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
