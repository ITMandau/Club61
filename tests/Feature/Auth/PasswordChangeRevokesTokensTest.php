<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Audit go-live G12: password berganti (diganti sendiri, reset lewat email, atau diganti admin) → semua token aplikasi
 * HP dicabut. Sesi web di perangkat lain keluar lewat middleware AuthenticateSession; sesi yang sedang dipakai tetap.
 */
class PasswordChangeRevokesTokensTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_PASSWORD = 'Kata-Sandi-Baru-2026!';

    private function appToken(User $user): string
    {
        return $user->createToken('flutter-mobile-app')->plainTextToken;
    }

    private function apiMe(string $token): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $response = $this->withToken($token)->getJson('/api/v1/auth/me');
        // auth:sanctum memindah guard bawaan ke "sanctum" untuk sisa test — kembalikan ke web untuk request berikutnya.
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        $this->flushHeaders();

        return $response;
    }

    public function test_changing_own_password_signs_out_the_app_but_keeps_this_browser(): void
    {
        $user = User::factory()->customer()->create();
        $token = $this->appToken($user);
        $this->apiMe($token)->assertOk();

        $this->actingAs($user)->from('/profile')->put('/password', [
            'current_password' => 'password',
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertSessionHasNoErrors()->assertRedirect('/profile');

        $this->assertSame(0, $user->tokens()->count());
        $this->apiMe($token)->assertUnauthorized();
        $this->actingAs($user->fresh())->get('/profile')->assertOk();
    }

    public function test_reset_by_email_signs_out_the_app(): void
    {
        $user = User::factory()->customer()->create();
        $token = $this->appToken($user);

        $this->post('/reset-password', [
            'token' => Password::createToken($user),
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));
        $this->apiMe($token)->assertUnauthorized();
    }

    public function test_admin_setting_a_new_password_signs_out_the_app(): void
    {
        // Form admin (UserResource) menyimpan Hash::make(sandi baru) lewat model — jalur yang sama dengan ini.
        $customer = User::factory()->customer()->create();
        $first = $this->appToken($customer);
        $second = $this->appToken($customer);

        $customer->update(['password' => Hash::make(self::NEW_PASSWORD)]);

        $this->assertSame(0, $customer->tokens()->count(), 'semua perangkat, bukan hanya satu');
        $this->apiMe($first)->assertUnauthorized();
        $this->apiMe($second)->assertUnauthorized();
    }

    public function test_other_profile_changes_keep_the_app_signed_in(): void
    {
        $user = User::factory()->customer()->create();
        $token = $this->appToken($user);

        $user->update(['name' => 'Nama Baru', 'phone' => '081299988877']);

        $this->assertSame(1, $user->tokens()->count());
        $this->apiMe($token)->assertOk();
    }
}
