<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageUsers extends ManageRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateFormDataUsing(function (array $data): array {
                    if (! auth()->user()?->hasRole('super_admin') && isset($data['roles'])) {
                        $superAdminRole = \App\Models\Role::findByName('super_admin', 'web');
                        if ($superAdminRole) {
                            $data['roles'] = array_values(array_filter(
                                (array) $data['roles'],
                                fn ($r) => (string) $r !== (string) $superAdminRole->id && $r !== 'super_admin'
                            ));
                        }
                    }
                    return $data;
                }),
        ];
    }
}
