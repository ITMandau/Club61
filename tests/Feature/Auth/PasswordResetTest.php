<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Models\Audit\ActivityLog;
use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Support\PlaceholderEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_second_request_within_a_minute_does_not_reveal_the_account_exists(): void
    {
        Notification::fake();
        User::factory()->create(["phone" => "081355556666"]);

        $this->post("/forgot-password", ["email" => "081355556666"])->assertSessionHasNoErrors();
        $this->post("/forgot-password", ["email" => "081355556666"])
            ->assertSessionHasNoErrors()
            ->assertSessionHas("status", PasswordResetLinkController::GENERIC_STATUS);
    }

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $this->get('/forgot-password')
            ->assertStatus(200)
            ->assertSee('Lupa Kata Sandi')
            ->assertSee('Email atau Nomor HP');
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('status', PasswordResetLinkController::GENERIC_STATUS);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_reset_link_can_be_requested_with_phone_number(): void
    {
        Notification::fake();

        $user = User::factory()->create(['phone' => '081234567890']);

        $this->post('/forgot-password', ['email' => '+62 812-3456-7890'])
            ->assertSessionHas('status', PasswordResetLinkController::GENERIC_STATUS);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_unknown_account_gets_the_same_generic_message(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'tidak-ada@example.com'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', PasswordResetLinkController::GENERIC_STATUS);
        $this->post('/forgot-password', ['email' => '089999999999'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', PasswordResetLinkController::GENERIC_STATUS);

        Notification::assertNothingSent();
    }

    public function test_walk_in_account_with_placeholder_email_is_never_emailed(): void
    {
        Notification::fake();

        $user = User::factory()->create(['phone' => '081311112222', 'email' => 'walkin-01abc@walkin.club61.internal']);

        $this->post('/forgot-password', ['email' => '081311112222'])
            ->assertSessionHas('status', PasswordResetLinkController::GENERIC_STATUS);

        Notification::assertNotSentTo($user, ResetPasswordNotification::class);
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
    }

    public function test_placeholder_email_patterns(): void
    {
        foreach (['walkin-01h@walkin.club61.internal', 'mbr_081234@club61.id', 'corp_6281234@club61.id', '', 'x@host.local'] as $email) {
            $this->assertTrue(PlaceholderEmail::is($email), $email);
        }
        foreach (['budi@gmail.com', 'admin@club61.id', 'mbr.budi@club61.id'] as $email) {
            $this->assertFalse(PlaceholderEmail::is($email), $email);
        }
    }

    public function test_reset_link_uses_app_url_not_the_request_host(): void
    {
        Notification::fake();
        config(['app.url' => 'https://sandbox.club61.id']);

        $user = User::factory()->create();

        $this->withHeaders(['Host' => 'evil.example', 'X-Forwarded-Host' => 'evil.example'])
            ->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
            $mail = $notification->toMail($user);
            $this->assertStringStartsWith('https://sandbox.club61.id/reset-password/', $mail->viewData['url']);
            $this->assertStringNotContainsString('evil.example', $mail->viewData['url']);

            return true;
        });
    }

    public function test_reset_email_is_in_indonesian(): void
    {
        $user = User::factory()->create(['name' => 'Budi Santoso']);

        $mail = (new ResetPasswordNotification('token-abc'))->toMail($user);
        $html = (string) $mail->render();

        $this->assertSame('Atur Ulang Kata Sandi Akun Club 61', $mail->subject);
        $this->assertStringContainsString('Halo Budi Santoso', $html);
        $this->assertStringContainsString('Buat Kata Sandi Baru', $html);
        $this->assertStringContainsString('60 menit', $html);
        $this->assertStringContainsString('/reset-password/token-abc', $html);
    }

    public function test_mail_failure_shows_error_logs_it_and_allows_immediate_retry(): void
    {
        $user = User::factory()->create();
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 1]);

        $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect('/forgot-password')
            ->assertSessionHasErrors(['email' => PasswordResetLinkController::MAIL_FAILED]);

        $this->assertSame(0, DB::table('password_reset_tokens')->count());
        $this->assertTrue(ActivityLog::where('event', 'auth.password_reset_mail_failed')->exists());

        // Setelah SMTP beres, permintaan berikutnya tidak tertahan jeda 1 menit.
        Notification::fake();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors();
        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_forgot_password_is_rate_limited_per_ip(): void
    {
        Notification::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password', ['email' => "orang{$i}@example.com"])->assertStatus(302);
        }

        $this->post('/forgot-password', ['email' => 'orang6@example.com'])->assertStatus(429);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) {
            $this->get('/reset-password/'.$notification->token)
                ->assertStatus(200)
                ->assertSee('Buat Kata Sandi Baru');

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $user->createToken('mobile');

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password-baru-123',
                'password_confirmation' => 'password-baru-123',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'))
                ->assertSessionHas('status', __('passwords.reset'));

            return true;
        });

        $this->assertSame(0, $user->tokens()->count());
        $this->assertTrue(ActivityLog::where('event', 'auth.password_reset')->exists());
    }

    public function test_invalid_token_shows_indonesian_message(): void
    {
        $user = User::factory()->create();

        $this->from('/reset-password/salah')->post('/reset-password', [
            'token' => 'salah',
            'email' => $user->email,
            'password' => 'password-baru-123',
            'password_confirmation' => 'password-baru-123',
        ])->assertSessionHasErrors(['email' => __('passwords.token')]);

        $this->assertStringContainsString('kedaluwarsa', __('passwords.token'));
    }

    public function test_mail_test_command_reports_log_mailer(): void
    {
        config(['mail.default' => 'array']);

        $this->artisan('mail:test', ['to' => 'cek@example.com'])
            ->expectsOutputToContain('TIDAK benar-benar dikirim')
            ->expectsOutputToContain('Email tes terkirim')
            ->assertSuccessful();

        $this->artisan('mail:test', ['to' => 'bukan-email'])->assertFailed();
    }
}
