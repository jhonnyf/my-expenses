<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_token_on_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'password',
        ])
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'email']]]);
    }

    public function test_login_returns_401_on_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'test@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(401);
    }

    public function test_login_validates_required_fields(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_register_creates_user_and_returns_token(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name'                  => 'João Silva',
            'email'                 => 'joao@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'email']]]);

        $this->assertDatabaseHas('users', ['email' => 'joao@example.com']);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'joao@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'name'                  => 'Outro João',
            'email'                 => 'joao@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_me_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_logout_revokes_token(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertStatus(200);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
