<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
    use HandlesAuthorization;

    // Slug di sini SENGAJA dicocokkan ke App\Services\Permission\Club61PermissionMatrix
    // (view_roles/create_roles/update_roles/delete_roles) — bukan gaya Filament Shield
    // ("ViewAny:Role" dkk) yang tidak pernah ke-sync ke database sama sekali, sama seperti
    // di UserPolicy.
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_roles');
    }

    public function view(AuthUser $authUser, Role $role): bool
    {
        return $authUser->can('view_roles');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_roles');
    }

    public function update(AuthUser $authUser, Role $role): bool
    {
        return $authUser->can('update_roles');
    }

    public function delete(AuthUser $authUser, Role $role): bool
    {
        return $authUser->can('delete_roles');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_roles');
    }

    public function restore(AuthUser $authUser, Role $role): bool
    {
        return $authUser->can('Restore:Role');
    }

    public function forceDelete(AuthUser $authUser, Role $role): bool
    {
        return $authUser->can('ForceDelete:Role');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Role');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Role');
    }

    public function replicate(AuthUser $authUser, Role $role): bool
    {
        return $authUser->can('Replicate:Role');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Role');
    }

}