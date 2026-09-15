<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\Models\Permission;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected array $matrixPermissions = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => ! in_array($this->record->name, ['super_admin', 'admin'])),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $collected = [];
        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'permissions_') && is_array($value)) {
                foreach ($value as $perm) {
                    if (! empty($perm)) {
                        $collected[] = $perm;
                    }
                }
            }
        }
        $this->matrixPermissions = array_values(array_unique($collected));

        return [
            'name' => $data['name'],
            'guard_name' => $data['guard_name'] ?? 'web',
            'description' => $data['description'] ?? null,
            'home_route' => $data['home_route'] ?? '/admin',
        ];
    }

    protected function afterSave(): void
    {
        $guardName = $this->record->guard_name ?? 'web';

        foreach ($this->matrixPermissions as $permName) {
            Permission::firstOrCreate([
                'name' => $permName,
                'guard_name' => $guardName,
            ]);
        }

        $this->record->syncPermissions($this->matrixPermissions);

        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }
}
