<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Halaman error yang menjelaskan penyebab + langkah, dan penanganan form kedaluwarsa (419) yang tidak membingungkan. */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    /** Di test, cek CSRF bawaan dilewati — nyalakan supaya kasus 419 asli bisa diuji. */
    protected function enableCsrf(): void
    {
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }

    public function test_expired_login_form_returns_to_login_with_message_and_email_kept(): void
    {
        $this->enableCsrf();

        $response = $this->withCookie(config('session.cookie'), 'sesi-lama')
            ->from('/login')
            ->post('/login', ['_token' => 'token-basi', 'email' => 'budi@example.com', 'password' => 'rahasia123']);

        $response->assertRedirect('/login')->assertSessionHas('session_expired');
        $this->assertSame('budi@example.com', session()->getOldInput('email'));
        $this->assertNull(session()->getOldInput('password'), 'kata sandi tidak boleh ikut disimpan');

        $this->followRedirects($response)->assertOk()->assertSee('Sesi halaman sudah habis');
    }

    public function test_expired_logout_still_logs_the_user_out(): void
    {
        $this->enableCsrf();
        $user = User::factory()->customer()->create();

        $this->actingAs($user)->withCookie(config('session.cookie'), 'sesi-lama')
            ->post('/logout', ['_token' => 'token-basi'])
            ->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_without_session_cookie_shows_explanation_page_instead_of_redirect_loop(): void
    {
        $this->enableCsrf();

        $this->post('/login', ['_token' => 'token-basi', 'email' => 'budi@example.com'])
            ->assertStatus(419)
            ->assertSee('Sesi halaman sudah habis')
            ->assertSee('cookie tidak diblokir');
    }

    public function test_login_page_is_never_cached_by_the_browser(): void
    {
        $cache = $this->get('/login')->assertOk()->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', $cache);
    }

    public function test_error_pages_explain_cause_and_next_steps_in_indonesian(): void
    {
        $this->get('/halaman-yang-tidak-ada')
            ->assertNotFound()
            ->assertSee('Halaman tidak ditemukan')
            ->assertSee('Yang bisa Anda lakukan')
            ->assertSee('Ke Beranda');

        $this->get('/logout')->assertStatus(405)->assertSee('Cara membuka halaman ini tidak didukung');

        foreach ([401 => 'Anda perlu masuk dulu', 403 => 'Akses ditolak', 413 => 'File terlalu besar', 429 => 'Terlalu banyak percobaan', 500 => 'Terjadi kesalahan di sistem', 503 => 'Sedang dalam perbaikan'] as $code => $title) {
            $html = view("errors.{$code}")->render();
            $this->assertStringContainsString($title, $html, "halaman {$code}");
            $this->assertStringContainsString('Yang bisa Anda lakukan', $html, "halaman {$code}");
        }

        $this->assertStringContainsString('Permintaan tidak bisa diproses', view('errors.4xx')->render());
        $this->assertStringContainsString('Server sedang bermasalah', view('errors.5xx')->render());
    }

    public function test_api_errors_stay_json(): void
    {
        $this->getJson('/api/v1/tidak-ada')->assertNotFound()->assertJson(['success' => false]);
    }
}
