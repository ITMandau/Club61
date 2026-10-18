<?php

namespace App\Filament\Resources\Sponsor\Pages;

use App\Filament\Resources\Sponsor\SponsorAccessScheduleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSponsorAccessSchedules extends ListRecords
{
    protected static string $resource = SponsorAccessScheduleResource::class;

    public function getSubheading(): ?string
    {
        return SponsorAccessScheduleResource::IN_PROGRESS_NOTE;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Jadwal Akses'),
        ];
    }
}
