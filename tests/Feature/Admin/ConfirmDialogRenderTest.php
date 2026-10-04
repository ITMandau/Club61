<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Dialog konfirmasi Club61 (pengganti popup browser) terpasang di halaman panel admin dengan modal Filament. */
class ConfirmDialogRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_dialog_is_rendered_on_admin_pages_using_filament_modals(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $html = $this->get('/admin/master-data')->assertOk()->getContent();

        $this->assertStringContainsString('club61-confirm.window', $html);
        $this->assertStringContainsString('club61-confirm-danger', $html);
        $this->assertStringContainsString('club61-confirm-default', $html);
        $this->assertStringContainsString('fi-modal', $html);
        $this->assertStringNotContainsString('wire:confirm', $html);
    }
}
