<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Situs customer dua bahasa (ID default, EN): setiap kunci terjemahan wajib ada di kedua bahasa, dan pilihan bahasa
 * diingat lewat session + cookie (tanpa kolom database).
 */
class LocaleParityTest extends TestCase
{
    use RefreshDatabase;

    /** File bawaan Laravel yang hanya ada versi Indonesianya (Inggris memakai bawaan framework). */
    private const FRAMEWORK_FILES = ['auth.php', 'passwords.php', 'validation.php', 'pagination.php'];

    public function test_every_translation_key_exists_in_both_languages(): void
    {
        $files = collect(glob(lang_path('id/*.php')))->merge(glob(lang_path('en/*.php')))
            ->map(fn ($p) => basename($p))->unique()->reject(fn ($f) => in_array($f, self::FRAMEWORK_FILES, true));

        foreach ($files as $file) {
            $this->assertFileExists(lang_path("id/{$file}"), "lang/id/{$file} belum ada");
            $this->assertFileExists(lang_path("en/{$file}"), "lang/en/{$file} belum ada");
            $id = $this->keys(require lang_path("id/{$file}"));
            $en = $this->keys(require lang_path("en/{$file}"));
            $this->assertSame([], array_values(array_diff($id, $en)), "Kunci di lang/id/{$file} belum ada di lang/en/{$file}");
            $this->assertSame([], array_values(array_diff($en, $id)), "Kunci di lang/en/{$file} belum ada di lang/id/{$file}");
        }
    }

    public function test_language_choice_is_remembered_by_cookie_and_defaults_to_indonesian(): void
    {
        $this->get('/')->assertOk();
        $this->assertSame('id', app()->getLocale(), 'default Bahasa Indonesia');

        $this->from('/')->get('/lang/en')->assertRedirect('/')->assertCookie(SetLocale::COOKIE, 'en');

        // Session baru (mis. sesi habis) — cookie tetap membawa pilihan bahasa.
        $this->flushSession();
        $this->withCookie(SetLocale::COOKIE, 'en')->get('/')->assertOk();
        $this->assertSame('en', app()->getLocale());
        $this->assertSame('en', \Carbon\Carbon::getLocale());

        $this->get('/lang/fr')->assertNotFound();
    }

    public function test_staff_surfaces_stay_indonesian_even_when_the_browser_chose_english(): void
    {
        // Update Livewire (admin & kasir) lewat grup web juga — cookie EN dari situs customer tidak boleh ikut.
        $this->withCookie(SetLocale::COOKIE, 'en')->withHeader('X-Livewire', 'true')->get('/');
        $this->assertSame('id', app()->getLocale());
        $this->assertSame('id', \Carbon\Carbon::getLocale());
    }

    public function test_language_switch_never_redirects_to_another_site(): void
    {
        $this->withHeader('Referer', 'https://evil.example/phish')->get('/lang/en')->assertRedirect(url('/'));
        $this->from(url('/booking'))->get('/lang/id')->assertRedirect(url('/booking'));
    }

    public function test_english_password_reset_does_not_reveal_whether_an_email_is_registered(): void
    {
        \App\Models\User::factory()->create(['email' => 'terdaftar@club61.test']);

        foreach (['terdaftar@club61.test', 'tidak-ada@club61.test'] as $email) {
            $this->withCookie(SetLocale::COOKIE, 'en')
                ->post('/reset-password', ['token' => 'token-asal', 'email' => $email, 'password' => 'RahasiaBaru123!', 'password_confirmation' => 'RahasiaBaru123!'])
                ->assertSessionHasErrors(['email' => __('passwords.token', [], 'en')]);
        }
        $this->assertSame(__('passwords.token', [], 'en'), __('passwords.user', [], 'en'));
    }

    /** @return list<string> kunci bertitik, mis. "checkout.notice.title" */
    private function keys(array $array, string $prefix = ''): array
    {
        $keys = [];
        foreach ($array as $key => $value) {
            $keys = is_array($value) ? [...$keys, ...$this->keys($value, "{$prefix}{$key}.")] : [...$keys, $prefix.$key];
        }

        return $keys;
    }
}
