<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_via_api(): void
    {
        $user = User::factory()->create([
            'email' => 'member@vantage.id',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'member@vantage.id',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Login berhasil.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email', 'phone', 'role'],
                    'token',
                ],
            ]);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'member@vantage.id',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'member@vantage.id',
            'password' => 'WrongPassword!',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Email/No HP atau password yang Anda masukkan salah.',
            ]);
    }

    public function test_user_cannot_login_if_account_is_inactive(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive@vantage.id',
            'password' => Hash::make('Password123!'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'inactive@vantage.id',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Akun Anda sedang dinonaktifkan.',
            ]);
    }

    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'email' => $user->email,
                ],
            ]);
    }

    public function test_user_can_logout_via_api(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logout berhasil. Token telah dicabut.',
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_user_can_register_via_api(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Budi Santoso',
            'email' => 'budi.baru@vantage.id',
            'phone' => '081299887766',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Registrasi akun berhasil.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email', 'phone', 'role'],
                    'token',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'budi.baru@vantage.id',
        ]);
        $registeredUser = \App\Models\User::where('email', 'budi.baru@vantage.id')->first();
        $this->assertNotNull($registeredUser);
        $this->assertTrue($registeredUser->hasRole('customer'));
        $this->assertEquals('CUSTOMER', $registeredUser->role);
    }

    public function test_register_normalizes_various_phone_formats_to_canonical_08_format(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Siti Rahma',
            'email' => 'siti.rahma@vantage.id',
            'phone' => '+62 812-3456-7890',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'siti.rahma@vantage.id',
            'phone' => '081234567890',
        ]);
    }

    public function test_register_rejects_duplicate_phone_in_different_formats(): void
    {
        User::factory()->create(['phone' => '081234567890']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Duplikat',
            'email' => 'duplikat@vantage.id',
            'phone' => '6281234567890',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    public function test_user_can_login_via_api_using_phone_number(): void
    {
        User::factory()->create([
            'phone' => '081234567890',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => '+62 812-3456-7890',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Login berhasil.',
            ]);
    }

    public function test_user_can_still_login_via_api_using_legacy_email_field(): void
    {
        User::factory()->create([
            'email' => 'legacy@vantage.id',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'legacy@vantage.id',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
    }
}