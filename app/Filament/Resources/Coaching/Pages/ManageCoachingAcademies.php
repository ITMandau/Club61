<?php

namespace App\Filament\Resources\Coaching\Pages;

use App\Filament\Resources\Coaching\CoachingAcademyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCoachingAcademies extends ManageRecords
{
    protected static string $resource = CoachingAcademyResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Academy')->modalWidth('2xl')];
    }
}
