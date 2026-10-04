<?php

namespace App\Filament\Resources\Membership\Facilities\Pages;

use App\Filament\Resources\Membership\Facilities\MembershipFacilityResource;
use App\Models\Membership\MembershipFacility;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageMembershipFacilities extends ManageRecords
{
    protected static string $resource = MembershipFacilityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Fasilitas')
                // Fasilitas buatan admin tidak pernah jadi fasilitas sistem & hanya boleh mode CHECK_IN / INFO.
                ->mutateFormDataUsing(function (array $data): array {
                    $data['is_system'] = false;
                    if (! array_key_exists($data['usage_mode'] ?? '', MembershipFacility::CUSTOM_MODES)) {
                        $data['usage_mode'] = MembershipFacility::MODE_INFO;
                    }

                    return $data;
                }),
        ];
    }
}
