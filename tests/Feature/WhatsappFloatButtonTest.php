<?php

namespace Tests\Feature;

use App\Models\Setting\CompanyProfileSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Satu tombol WhatsApp melayang (pojok kanan bawah) di halaman depan, login, & semua halaman customer — nomor dari
 * Konten Website. Tombol / nomor WhatsApp yang dulu tertulis di halaman (6281261617233, "0812-6161-PADEL") dihapus.
 */
class WhatsappFloatButtonTest extends TestCase
{
    use RefreshDatabase;

    private function setNumber(?string $number): void
    {
        CompanyProfileSetting::current()->update(['whatsapp_number' => $number]);
    }

    public function test_number_from_website_content_is_normalised_for_wa_me(): void
    {
        foreach (['0812-6161-7233' => '6281261617233', '+62 812 6161 7233' => '6281261617233', '81261617233' => '6281261617233', '6281261617233' => '6281261617233'] as $input => $digits) {
            $this->setNumber($input);
            $this->assertSame($digits, CompanyProfileSetting::whatsappDigits(), $input);
        }
        foreach (['', '12345', 'hubungi admin'] as $invalid) {
            $this->setNumber($invalid);
            $this->assertNull(CompanyProfileSetting::whatsappDigits(), $invalid);
        }
    }

    public function test_floating_button_shows_on_public_login_and_customer_pages(): void
    {
        $this->setNumber('0811-2233-4455');
        $wa = 'https://wa.me/6281122334455?text=';

        $this->get('/')->assertOk()->assertSee('id="wa-float"', false)->assertSee($wa, false);
        $this->get('/login')->assertOk()->assertSee('id="wa-float"', false);

        $this->actingAs(User::factory()->customer()->create());
        foreach (['/dashboard', '/my-club', '/membership', '/booking', '/invoice', '/profile', '/profile/vouchers'] as $url) {
            $this->get($url)->assertOk()->assertSee('id="wa-float"', false)->assertSee($wa, false)->assertSee('wa-float-nav', false);
        }
    }

    public function test_no_button_when_admin_left_the_number_empty(): void
    {
        $this->setNumber(null);

        $this->get('/')->assertOk()->assertDontSee('id="wa-float"', false);
        $this->actingAs(User::factory()->customer()->create())->get('/my-club')->assertOk()->assertDontSee('id="wa-float"', false);
    }

    public function test_old_contact_buttons_and_hardcoded_numbers_are_gone(): void
    {
        $this->setNumber('0811-2233-4455');
        $this->actingAs(User::factory()->customer()->create());

        foreach (['/dashboard', '/my-club', '/invoice'] as $url) {
            $page = $this->get($url)->assertOk();
            $page->assertDontSee('6281261617233')->assertDontSee('0812-6161-PADEL')
                ->assertDontSee('Contact WhatsApp Concierge')->assertDontSee('Contact Concierge via WhatsApp')
                ->assertDontSee('Tanya Concierge WA')->assertDontSee('Need help with check-in?');
        }
        // Nomor venue di Home / My Club = nomor dari Konten Website.
        $this->get('/dashboard')->assertSee('0811-2233-4455');
    }
}
