<?php

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    // Slug di sini SENGAJA dicocokkan ke App\Services\Permission\Club61PermissionMatrix
    // (view_users/create_users/update_users/delete_users) — bukan gaya Filament Shield
    // ("ViewAny:User" dkk). Versi Shield yang lama tidak pernah ke-sync ke database sama
    // sekali (Club61PermissionMatrix tidak mendefinisikannya), jadi UserResource sebelumnya
    // cuma bisa diakses super_admin (lewat Gate::before bypass) walau role "admin" sudah
    // diberi permission view_users/dst lewat DatabaseSeeder — permission itu cuma tidak
    // pernah benar-benar dicek di sini.
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_users');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('view_users');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_users');
    }

    public function update(AuthUser $authUser, ?AuthUser $model = null): bool
    {
        return $authUser->can('update_users') && $this->canTouch($authUser, $model);
    }

    public function delete(AuthUser $authUser, ?AuthUser $model = null): bool
    {
        return $authUser->can('delete_users') && $this->canTouch($authUser, $model);
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_users');
    }

    public function restore(AuthUser $authUser, ?AuthUser $model = null): bool
    {
        return $authUser->can('Restore:User') && $this->canTouch($authUser, $model);
    }

    public function forceDelete(AuthUser $authUser, ?AuthUser $model = null): bool
    {
        return $authUser->can('ForceDelete:User') && $this->canTouch($authUser, $model);
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:User');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:User');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:User');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:User');
    }

    /**
     * Akun super_admin hanya boleh diubah/dihapus oleh super_admin. Tanpa ini, admin yang
     * punya update_users bisa mengganti sandi super_admin (ambil alih akun), dan form edit
     * yang men-strip role super_admin dari input non-super_admin malah men-demote-nya.
     */
    private function canTouch(AuthUser $authUser, ?AuthUser $model): bool
    {
        if (! $model || ! method_exists($model, 'hasRole') || ! $model->hasRole('super_admin')) {
            return true;
        }

        return method_exists($authUser, 'hasRole') && $authUser->hasRole('super_admin');
    }
}
