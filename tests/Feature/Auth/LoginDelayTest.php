<?php

namespace Tests\Feature\Auth;

use App\Models\Audit\ActivityLog;
use App\Models\User;
use App\Services\Auth\LoginThrottle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Audit go-live G13: jeda login bertahap per akun (web & API), bukan kunci total. Sandi diganti / direset → langsung
 * bisa masuk lagi. Penundaan dicatat di Log Aktivitas lengkap dengan IP.
 */
class LoginDelayTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Rahasia#2026';

    private User $user;

    private int $ip = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        $this->user = User::factory()->customer()->create(['email' => 'pemain@club61.test', 'phone' => '081277766655', 'password' => Hash::make(self::PASSWORD)]);
    }

    /** Login web dari IP berbeda tiap kali (penyerang berganti-ganti IP). */
    private function webLogin(string $password, string $login = 'pemain@club61.test'): void
    {
        $this->app['auth']->forgetGuards();
        $this->withServerVariables(['REMOTE_ADDR' => '10.9.0.'.(++$this->ip)])->post('/login', ['email' => $login, 'password' => $password]);
    }

    private function failTimes(int $times): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->webLogin('salah-sandi');
        }
    }

    public function test_delay_grows_with_each_wrong_password_and_is_capped_at_one_hour(): void
    {
        $this->assertSame([0, 0, 30, 60, 120, 240, 480, 960, 1920, 3600, 3600], array_map(
            fn (int $n) => LoginThrottle::delayFor($n), [1, 5, 6, 7, 8, 9, 10, 11, 12, 13, 500]
        ));
    }

    public function test_after_five_wrong_passwords_from_any_ip_even_the_right_password_waits_then_works(): void
    {
        $this->failTimes(6);   // ke-6 → tunggu 30 detik
        $this->assertSame(30, LoginThrottle::waitSeconds($this->user, 'pemain@club61.test'));

        // Masih menunggu: sandi benar pun ditolak, dan percobaan yang ditolak tidak memperpanjang jeda.
        $this->webLogin(self::PASSWORD);
        $this->assertGuest();
        $this->assertStringContainsString('Terlalu banyak percobaan', session('errors')->first('email'));
        $this->webLogin('salah-sandi');
        $this->assertLessThanOrEqual(30, LoginThrottle::waitSeconds($this->user, 'pemain@club61.test'));

        $this->travel(31)->seconds();
        $this->webLogin(self::PASSWORD);
        $this->assertAuthenticatedAs($this->user);
        $this->assertSame(0, LoginThrottle::waitSeconds($this->user, 'pemain@club61.test'), 'login berhasil menghapus hitungan');
    }

    public function test_email_and_phone_variants_count_as_one_account(): void
    {
        // (Email huruf besar juga ke akun yang sama di MySQL — collation *_ci; SQLite test membandingkan peka huruf.)
        foreach (['pemain@club61.test', '081277766655', '+6281277766655', '81277766655', '0812-7776-6655', '6281277766655'] as $variant) {
            $this->webLogin('salah-sandi', $variant);
        }

        $this->assertGreaterThan(0, LoginThrottle::waitSeconds($this->user, 'pemain@club61.test'));
    }

    public function test_password_reset_or_change_lets_the_owner_in_immediately(): void
    {
        $this->failTimes(12);
        $this->assertGreaterThan(0, LoginThrottle::waitSeconds($this->user, 'pemain@club61.test'));

        // Customer reset lewat email / admin mengganti sandi staf → sama-sama lewat model User.
        $this->user->update(['password' => Hash::make('Sandi-Baru#2026')]);

        $this->webLogin('Sandi-Baru#2026');
        $this->assertAuthenticatedAs($this->user);
    }

    public function test_api_login_shares_the_same_delay(): void
    {
        $this->failTimes(6);

        $this->postJson('/api/v1/auth/login', ['login' => '+62 812-7776-6655', 'password' => self::PASSWORD])
            ->assertStatus(429)->assertHeader('Retry-After')->assertJsonPath('success', false);

        // Salah lewat API juga menambah hitungan akun yang sama.
        $this->travel(31)->seconds();
        $this->postJson('/api/v1/auth/login', ['login' => '081277766655', 'password' => 'salah-sandi'])->assertStatus(429);
        $this->assertSame(60, LoginThrottle::waitSeconds($this->user, 'pemain@club61.test'));
    }

    public function test_unknown_accounts_are_delayed_the_same_way(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->webLogin('salah-sandi', 'tidak-ada@club61.test');
        }

        $this->assertSame(30, LoginThrottle::waitSeconds(null, 'tidak-ada@club61.test'));
    }

    public function test_each_delay_is_recorded_in_the_activity_log_with_the_ip(): void
    {
        $this->failTimes(6);

        $log = ActivityLog::where('event', 'auth.login_delayed')->sole();
        $this->assertSame('10.9.0.6', $log->ip_address);
        $this->assertSame($this->user->id, $log->subject_id);
        $this->assertSame(6, $log->meta['gagal_berturut']);
        $this->assertSame('WARNING', $log->severity);
    }
}
