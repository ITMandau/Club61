<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit keamanan R1: SEC-013 (batas umum api/*) & SEC-006 (daftar akun web dibatasi). Webhook gateway tidak ikut
 * batas umum (PRD RL-03).
 */
class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_api_is_limited_to_120_per_minute_per_ip(): void
    {
        for ($i = 0; $i < 120; $i++) {
            $this->getJson('/api/v1/padel/courts')->assertOk();
        }

        $this->getJson('/api/v1/padel/courts')
            ->assertStatus(429)
            ->assertJson(['success' => false]);
    }

    public function test_logged_in_users_get_their_own_quota_not_the_shared_ip(): void
    {
        $budi = User::factory()->customer()->create();
        $sari = User::factory()->customer()->create();

        for ($i = 0; $i < 120; $i++) {
            $this->actingAs($budi, 'web')->getJson('/api/v1/padel/my-bookings')->assertOk();
        }
        $this->actingAs($budi, 'web')->getJson('/api/v1/padel/my-bookings')->assertStatus(429);

        // Pelanggan lain di WiFi venue yang sama tidak ikut terkunci. (Di test semua request satu proses: guard yang
        // menyimpan user sebelumnya dikosongkan, seperti request baru di server.)
        $this->app['auth']->forgetGuards();
        $this->actingAs($sari, 'web')->getJson('/api/v1/padel/my-bookings')->assertOk();
    }

    public function test_payment_webhooks_are_not_counted_by_the_general_api_limit(): void
    {
        // Habiskan kuota umum IP ini.
        for ($i = 0; $i < 120; $i++) {
            $this->getJson('/api/v1/padel/courts');
        }
        $this->getJson('/api/v1/padel/courts')->assertStatus(429);

        // Notifikasi gateway dari IP yang sama tetap diproses oleh controller-nya (hasilnya apa pun, bukan 429).
        $this->assertNotSame(429, $this->postJson('/api/v1/padel/webhook/midtrans', ['order_id' => 'X', 'status_code' => '200', 'gross_amount' => '1', 'signature_key' => 'salah'])->status());
        $this->assertNotSame(429, $this->postJson('/api/v1/payments/doku/notification', [])->status());
    }

    public function test_web_registration_is_limited_to_5_per_minute(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', ['name' => 'Bot', 'email' => "bot{$i}@contoh.test", 'password' => 'x', 'password_confirmation' => 'y']);
            auth()->logout();
        }

        $this->from('/register')
            ->post('/register', ['name' => 'Bot', 'email' => 'bot9@contoh.test', 'password' => 'x', 'password_confirmation' => 'y'])
            ->assertRedirect('/register')
            ->assertSessionHasErrors(['email' => 'Terlalu banyak percobaan daftar dari jaringan ini. Coba lagi dalam 1 menit.']);
    }
}
