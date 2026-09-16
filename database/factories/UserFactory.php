<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (\App\Models\User $user) {
            if ($user->roles()->count() === 0) {
                $role = \Spatie\Permission\Models\Role::findOrCreate('customer', 'web');
                $user->assignRole($role);
            }
        });
    }

    public function role(string $role): static
    {
        return $this->afterCreating(function (\App\Models\User $user) use ($role) {
            $r = \Spatie\Permission\Models\Role::findOrCreate(strtolower($role), 'web');
            if (strtolower($role) === 'admin' && $r->permissions()->count() === 0 && class_exists(\App\Services\Permission\Club61PermissionMatrix::class)) {
                \App\Services\Permission\Club61PermissionMatrix::syncAllPermissions('web');
                $r->syncPermissions(\App\Services\Permission\Club61PermissionMatrix::getAllPermissionSlugs());
            }
            $user->syncRoles([$r]);
        });
    }

    public function superAdmin(): static
    {
        return $this->role('super_admin');
    }

    public function admin(): static
    {
        return $this->role('admin');
    }

    public function cashier(): static
    {
        return $this->role('cashier');
    }

    public function kitchen(): static
    {
        return $this->role('kitchen');
    }

    public function customer(): static
    {
        return $this->role('customer');
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
