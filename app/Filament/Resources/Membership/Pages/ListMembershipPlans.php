<?php

namespace App\Filament\Resources\Membership\Pages;

use App\Filament\Resources\Membership\MembershipPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMembershipPlans extends ListRecords
{
    protected static string $resource = MembershipPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Paket Membership'),
        ];
    }
}
