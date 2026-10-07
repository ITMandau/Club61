<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Sesi web di perangkat lain harus keluar setelah password diganti / direset (AuthenticateSession di grup web).
 * Dulu hanya panel admin yang begini — HP customer yang hilang tetap login walau password sudah direset lewat email.
 */
class SessionInvalidationTest extends TestCase
{
    use RefreshDatabase;

    /** Sesi "perangkat lain": sudah login dengan hash password saat itu. */
    private function otherDevice(User $user, string $passwordHashAtLogin): self
    {
        return $this->withSession([
            'login_web_'.sha1(SessionGuard::class) => $user->getAuthIdentifier(),
            'password_hash_web' => $passwordHashAtLogin,
        ]);
    }

    public function test_session_stays_valid_while_the_password_is_unchanged(): void
    {
        $user = User::factory()->customer()->create();

        $this->otherDevice($user, $user->password)->get('/my-club')->assertOk();
    }

    public function test_reset_via_email_logs_out_the_other_device(): void
    {
        Notification::fake();
        $user = User::factory()->customer()->create();
        $hashAtLogin = $user->password;

        $this->post('/forgot-password', ['email' => $user->email]);
        Notification::assertSentTo($user, \App\Notifications\Auth\ResetPasswordNotification::class, function ($notification) use ($user) {
            $this->post('/reset-password', [
                'token' => $notification->token, 'email' => $user->email,
                'password' => 'password-baru-123', 'password_confirmation' => 'password-baru-123',
            ])->assertSessionHasNoErrors();

            return true;
        });
        $this->app['auth']->forgetGuards();

        $this->otherDevice($user->fresh(), $hashAtLogin)->get('/my-club')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_session_based_api_calls_are_also_rejected_after_reset(): void
    {
        $user = User::factory()->customer()->create();
        $hashAtLogin = $user->password;
        $user->forceFill(['password' => Hash::make('password-baru-123')])->save();

        $this->otherDevice($user, $hashAtLogin)
            ->withHeaders(['Referer' => config('app.url'), 'Origin' => config('app.url')])
            ->getJson('/api/v1/membership/my-purchases')
            ->assertUnauthorized();
    }

    public function test_changing_own_password_keeps_the_current_session(): void
    {
        $user = User::factory()->customer()->create();

        $this->otherDevice($user, $user->password)
            ->from('/profile')
            ->put('/password', ['current_password' => 'password', 'password' => 'password-baru-123', 'password_confirmation' => 'password-baru-123'])
            ->assertSessionHasNoErrors();

        $this->get('/my-club')->assertOk();
    }
}
