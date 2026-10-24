<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'admin@example.com')
            ->assertJsonPath('data.user.role', 'admin')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email', 'role', 'created_at'],
                    'token',
                ],
            ]);

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_user_cannot_login_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('correct_password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid credentials.',
            ]);
    }

    public function test_authenticated_user_can_retrieve_profile(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_unauthenticated_user_cannot_retrieve_profile(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
                'errors' => null,
            ]);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully.',
                'data' => null,
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_revokes_old_tokens(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        // Issue 2 initial tokens
        $user->createToken('device-1');
        $user->createToken('device-2');
        $this->assertCount(2, $user->tokens);

        // Login afresh
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertStatus(200);

        // All previous tokens revoked, only the brand-new login token remains
        $this->assertCount(1, $user->fresh()->tokens);
    }

    public function test_admin_can_register_new_user(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/auth/register', [
                'name' => 'John Staff',
                'email' => 'john@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'staff',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'john@example.com')
            ->assertJsonPath('data.role', 'staff');

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'role' => 'staff',
        ]);
    }

    public function test_staff_cannot_register_users_and_does_not_leak_existing_emails(): void
    {
        $staff = User::factory()->staff()->create();
        $existingAdmin = User::factory()->admin()->create(['email' => 'ceo@example.com']);

        // Staff tries to submit an email that already exists
        $response = $this->actingAs($staff, 'sanctum')
            ->postJson('/api/v1/auth/register', [
                'name' => 'Any Name',
                'email' => $existingAdmin->email,
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

        // Must return 403 Forbidden (authorization), NOT 422 Unprocessable (validation leakage)
        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_guest_cannot_register_user(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Guest User',
            'email' => 'guest@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_rate_limiting_blocks_after_5_attempts(): void
    {
        $email = 'bruteforce@example.com';

        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $email,
                'password' => 'wrong',
            ])->assertStatus(401);
        }

        // 6th attempt from the same IP targeting the same email must be throttled
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'wrong',
        ]);

        $response->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Too many requests. Please slow down.');
    }
}
