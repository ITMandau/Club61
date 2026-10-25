<?php

namespace App\Filament\Resources\Coaching\Pages;

use App\Filament\Resources\Coaching\CoachResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCoaches extends ManageRecords
{
    protected static string $resource = CoachResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Coach')->modalWidth('2xl')];
    }
}
