<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'guard_name',
        'description',
        'home_route',
    ];

    /**
     * Pilihan home route resmi yang tersedia untuk mendarat setelah login.
     *
     * @return array<string, string>
     */
    public static function getHomeRouteOptions(): array
    {
        return [
            '/admin' => 'Admin Backoffice (/admin)',
            '/pos' => 'Terminal Kasir Frontdesk (/pos)',
            '/kitchen' => 'Layar Monitor Dapur KDS (/kitchen)',
            '/dashboard' => 'Portal Member Customer (/dashboard)',
        ];
    }
}
