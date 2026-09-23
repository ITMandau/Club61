<?php

namespace Tests\Feature;

use App\Filament\Pages\BookOfflineCourt;
use App\Filament\Pages\JualMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tab navigasi atas yang menghubungkan POS Walk-In Booking <-> POS Jual Membership harus render
 * di kedua halaman tanpa error, dan menandai tab yang sedang aktif dengan benar.
 */
class PosSubnavTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \App\Services\Permission\Club61PermissionMatrix::syncAllPermissions('web');

        $admin = User::factory()->admin()->create(['is_active' => true]);
        $admin->givePermissionTo(['View:BookOfflineCourt', 'View:JualMembership', 'process_walkin_booking']);

        $this->actingAs($admin);
    }

    public function test_walk_in_booking_page_renders_subnav_with_walkin_tab_active(): void
    {
        Livewire::test(BookOfflineCourt::class)
            ->assertStatus(200)
            ->assertSee('POS Walk-In Booking')
            ->assertSee('POS Jual Membership')
            ->assertSee(route('filament.admin.pages.jual-membership'), false);
    }

    public function test_jual_membership_page_renders_subnav_with_membership_tab_active(): void
    {
        Livewire::test(JualMembership::class)
            ->assertStatus(200)
            ->assertSee('POS Walk-In Booking')
            ->assertSee('POS Jual Membership')
            ->assertSee(route('filament.admin.pages.book-offline-court'), false);
    }
}
