<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Identitas brand "Club 61 Padel & Wellness" (logo baru 21 Okt 2026): tagline, ikon tab, dan logo dipakai seragam di
 * halaman publik, login, portal customer, dan halaman error.
 */
class BrandIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Judul tab = APP_NAME (.env, diubah di server) — yang diuji di sini teks brand di kode.
        config(['app.name' => 'Club 61 Padel & Wellness']);
    }

    public function test_public_pages_use_the_new_brand_name_and_tab_icon(): void
    {
        foreach (['/', '/login', '/register'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('images/identity/favicon.png', false)
                ->assertSee('Padel &amp; Wellness', false)
                ->assertDontSee('PADEL COURT')
                ->assertDontSee('Club 61 Padel Court');
        }

        $this->get('/')->assertSee('images/club61-logo-with-text.png', false)->assertSee('PADEL &amp; WELLNESS', false);
        $this->get('/login')->assertSee('images/identity/logo-cream.png', false);
    }

    public function test_customer_home_and_error_pages_use_the_new_brand_name(): void
    {
        $this->actingAs(User::factory()->customer()->create());

        $this->get('/dashboard')->assertOk()
            ->assertSee('PADEL &amp; WELLNESS', false)   // tagline di samping logo navbar
            ->assertSee('Padel &amp; Wellness', false)   // hero "CLUB 61 Padel & Wellness"
            ->assertDontSee('PADEL COURT');

        $this->get('/halaman-yang-tidak-ada')->assertNotFound()
            ->assertSee('Club 61 Padel &amp; Wellness', false)
            ->assertSee('images/identity/favicon.png', false);
    }

    public function test_new_logo_files_are_transparent_pngs(): void
    {
        foreach (['identity/logo-cream.png', 'identity/logo-terracotta.png', 'identity/favicon.png', 'club61-logo-with-text.png'] as $file) {
            $info = getimagesize(public_path('images/'.$file));
            $this->assertSame('image/png', $info['mime'], $file);
        }
        $this->assertSame([1200, 630], array_slice(getimagesize(public_path('images/club61-logo-with-text.png')), 0, 2), 'ukuran standar pratinjau link');
        $logo = imagecreatefrompng(public_path('images/identity/logo-cream.png'));
        $this->assertSame(127, (imagecolorat($logo, 0, 0) >> 24) & 127, 'latar logo transparan');
    }
}
