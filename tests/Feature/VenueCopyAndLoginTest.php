<?php

namespace Tests\Feature;

use App\Models\Padel\PadelCourt;
use App\Models\Setting\CompanyProfileFacility;
use App\Models\Setting\CompanyProfileSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Data venue bawaan mengikuti sandbox (tanpa "panoramic" / "AC") & tombol kembali di halaman login. */
class VenueCopyAndLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_venue_matches_the_sandbox_without_panoramic_or_ac(): void
    {
        $this->seed();

        $this->assertSame(
            [['Court BCA', 'INDOOR'], ['Court IM3 Platinum', 'INDOOR'], ['Court Protelindo', 'INDOOR']],
            PadelCourt::orderBy('name')->get()->map(fn ($c) => [$c->name, $c->type])->all()
        );
        $this->assertTrue(PadelCourt::all()->every(fn ($c) => (float) $c->hourly_rate_regular === 200000.0 && (float) $c->hourly_rate_prime === 300000.0 && $c->open_time === '06:00' && $c->close_time === '23:00'));

        $profile = CompanyProfileSetting::current();
        $this->assertSame(['Welcome to Club61', '-', 'Your New Home on the court.'], [$profile->hero_headline_line1, $profile->hero_headline_highlight, $profile->hero_headline_line2]);
        $this->assertSame('Lapangan padel, standar internasional.', CompanyProfileFacility::where('title', 'Padel Arena')->value('description'));

        foreach (['id', 'en'] as $locale) {
            $html = $this->withSession(['locale' => $locale])->get('/')->assertOk()->getContent();
            $this->assertDoesNotMatchRegularExpression('/panoramic|ber-AC|Central AC|Air-Conditioned/i', $html);
        }
        $this->assertStringContainsString('3 Lapangan Padel.', $this->withSession(['locale' => 'id'])->get('/')->getContent());
    }

    public function test_login_page_has_a_small_back_link_to_the_home_page(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Kembali ke beranda')
            ->assertSee('href="'.url('/').'"', false);
    }
}
