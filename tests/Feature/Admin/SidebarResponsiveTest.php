<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Permission\Club61PermissionMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarResponsiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_sidebar_is_collapsible_and_auto_collapses_on_tablet_width(): void
    {
        Club61PermissionMatrix::syncAllPermissions('web');

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/admin')
            ->assertOk()
            ->assertSee('fi-body-has-sidebar-collapsible-on-desktop', false)
            ->assertSee('const DESKTOP_MIN = 1280;', false)
            ->assertSee("store?.('sidebar')", false);
    }
}
