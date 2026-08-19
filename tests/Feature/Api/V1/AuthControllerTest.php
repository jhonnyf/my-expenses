<?php

namespace Tests\Feature\Api\V1;

use App\Jobs\GeocodeUserProfileJob;
use App\Models\Category;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_token_on_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'email']]]);
    }

    public function test_login_returns_401_on_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
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
            'name' => 'João Silva',
            'email' => 'joao@example.com',
            'password' => 'password123',
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
            'name' => 'Outro João',
            'email' => 'joao@example.com',
            'password' => 'password123',
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
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertStatus(200);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_register_creates_profile_with_cidade_estado_when_provided(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Maria Silva',
            'email' => 'maria@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'cidade' => 'Curitiba',
            'estado' => 'PR',
        ])->assertStatus(201);

        $user = User::where('email', 'maria@example.com')->firstOrFail();
        $this->assertDatabaseHas('users_profiles', [
            'user_id' => $user->id,
            'cidade' => 'Curitiba',
            'estado' => 'PR',
        ]);
        Queue::assertPushed(GeocodeUserProfileJob::class);
    }

    public function test_register_rejects_estado_with_invalid_length(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'estado' => 'PRR',
        ])->assertStatus(422)->assertJsonValidationErrors(['estado']);
    }

    public function test_register_creates_default_categories_for_user(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'categorias@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'categorias@example.com')->firstOrFail();
        $this->assertSame(11, Category::where('user_id', $user->id)->count());
    }

    public function test_register_creates_user_with_unverified_email(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'naoverificado@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'naoverificado@example.com')->firstOrFail();
        $this->assertFalse($user->hasVerifiedEmail());
    }

    public function test_register_sends_verification_email(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'naoverificado@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'naoverificado@example.com')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_unverified_user_is_blocked_from_protected_endpoints(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/dashboard')
            ->assertStatus(403);
    }

    public function test_unverified_user_can_still_call_me_and_logout(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200);
    }

    public function test_resend_verification_email_sends_notification(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/email/resend')
            ->assertStatus(200);

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_resend_verification_email_returns_conflict_for_verified_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/email/resend')
            ->assertStatus(409);
    }
}
