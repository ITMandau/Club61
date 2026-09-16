<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, HasRoles, HasUlids, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'avatar_url',
        'is_active',
        'registration_source',
    ];

    protected $appends = [
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected ?string $pendingRole = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function setRoleAttribute($value): void
    {
        if ($value) {
            $this->pendingRole = strtolower($value);
        }
    }

    public function getRoleAttribute(): string
    {
        $firstRole = $this->roles->first()?->name;
        return $firstRole ? strtoupper($firstRole) : 'CUSTOMER';
    }

    protected static function booted(): void
    {
        static::saved(function (User $user) {
            if ($user->pendingRole) {
                $role = Role::findOrCreate($user->pendingRole, 'web');
                if (strtolower($user->pendingRole) === 'admin' && $role->permissions()->count() === 0 && class_exists(\App\Services\Permission\Club61PermissionMatrix::class)) {
                    \App\Services\Permission\Club61PermissionMatrix::syncAllPermissions('web');
                    $role->syncPermissions(\App\Services\Permission\Club61PermissionMatrix::getAllPermissionSlugs());
                }
                if ($role->wasRecentlyCreated && strtolower($user->pendingRole) === 'customer') {
                    $perm = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'cancel_padel_booking', 'guard_name' => 'web']);
                    $role->givePermissionTo($perm);
                }
                $user->syncRoles([$role]);
                $user->pendingRole = null;
                $user->unsetRelation('roles');
            }
        });
    }

    public function canCancelBooking(): bool
    {
        return $this->can('cancel_padel_booking') || $this->can('cancel_refund_padel') || $this->isAdmin();
    }

    public function isCustomer(): bool
    {
        $roleNames = $this->getRoleNames()->map(fn ($r) => strtolower($r));
        if ($roleNames->isEmpty()) {
            return true;
        }

        return $roleNames->count() === 1 && $roleNames->first() === 'customer';
    }

    public function isStaff(): bool
    {
        return $this->hasRole('cashier') || $this->isAdmin();
    }

    public function isAdmin(): bool
    {
        if ($this->hasAnyRole(['super_admin', 'admin'])) {
            return true;
        }

        $nonAdminRoles = ['customer', 'cashier', 'kitchen'];
        return $this->roles->contains(fn ($role) => ! in_array(strtolower($role->name), $nonAdminRoles, true));
    }

    public function isCashier(): bool
    {
        return $this->hasRole('cashier');
    }

    public function isKitchen(): bool
    {
        return $this->hasRole('kitchen');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->is_active === false) {
            return false;
        }

        return ! $this->isCustomer() || $this->can('access_admin_panel');
    }

    public function staffProfile()
    {
        return $this->hasOne(\App\Models\Staff\StaffProfile::class, 'user_id');
    }
}
