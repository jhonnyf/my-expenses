<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialUser;
use Mockery;
use Tests\TestCase;

class SocialAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private function mockSocialiteUser(string $id, ?string $email, string $name = 'Social User', bool $emailVerified = true): SocialiteUser
    {
        return (new SocialUser)
            ->setRaw(['email_verified' => $emailVerified])
            ->map(['id' => $id, 'email' => $email, 'name' => $name, 'nickname' => null]);
    }

    private function mockSocialiteDriver(SocialiteUser $socialiteUser, string $token = 'valid-token'): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('userFromToken')->with($token)->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->andReturn($driver);
    }

    public function test_login_returns_token_for_valid_google_provider_and_token(): void
    {
        $socialiteUser = $this->mockSocialiteUser('google-123', 'test@example.com');
        $this->mockSocialiteDriver($socialiteUser);

        $this->postJson('/api/v1/auth/social/google', ['token' => 'valid-token'])
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'email']]]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'provider' => 'google',
            'provider_id' => 'google-123',
        ]);
    }

    public function test_login_returns_token_for_facebook_provider(): void
    {
        $socialiteUser = $this->mockSocialiteUser('fb-456', 'fbuser@example.com');
        $this->mockSocialiteDriver($socialiteUser);

        $this->postJson('/api/v1/auth/social/facebook', ['token' => 'valid-token'])
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_login_returns_token_for_apple_provider(): void
    {
        $socialiteUser = $this->mockSocialiteUser('apple-001', 'appleuser@example.com');
        $this->mockSocialiteDriver($socialiteUser);

        $this->postJson('/api/v1/auth/social/apple', ['token' => 'valid-token'])
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_login_returns_404_for_invalid_provider(): void
    {
        $this->postJson('/api/v1/auth/social/tiktok', ['token' => 'valid-token'])
            ->assertNotFound();
    }

    public function test_login_returns_422_when_token_is_missing(): void
    {
        $this->postJson('/api/v1/auth/social/google', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }

    public function test_login_returns_422_on_socialite_exception(): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('userFromToken')->andThrow(new \Exception('Invalid token'));

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->postJson('/api/v1/auth/social/google', ['token' => 'bad-token'])
            ->assertStatus(422);
    }

    public function test_login_links_provider_to_existing_user_by_email(): void
    {
        $user = User::factory()->create(['email' => 'existing@example.com']);

        $socialiteUser = $this->mockSocialiteUser('google-999', 'existing@example.com');
        $this->mockSocialiteDriver($socialiteUser);

        $this->postJson('/api/v1/auth/social/google', ['token' => 'valid-token'])
            ->assertStatus(200)
            ->assertJsonPath('data.user.id', $user->id);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'email' => 'existing@example.com',
            'provider' => 'google',
            'provider_id' => 'google-999',
        ]);
    }

    public function test_login_creates_sanctum_token_with_device_name(): void
    {
        $socialiteUser = $this->mockSocialiteUser('google-111', 'device@example.com');
        $this->mockSocialiteDriver($socialiteUser);

        $this->postJson('/api/v1/auth/social/google', [
            'token' => 'valid-token',
            'device_name' => 'iPhone 15',
        ])->assertStatus(200);

        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'iPhone 15',
        ]);
    }

    public function test_login_uses_provider_name_as_token_name_when_device_name_is_omitted(): void
    {
        $socialiteUser = $this->mockSocialiteUser('google-222', 'nodevice@example.com');
        $this->mockSocialiteDriver($socialiteUser);

        $this->postJson('/api/v1/auth/social/google', ['token' => 'valid-token'])
            ->assertStatus(200);

        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'google',
        ]);
    }

    public function test_login_refuses_to_link_an_account_when_the_provider_does_not_verify_the_email(): void
    {
        $user = User::factory()->create(['email' => 'vitima@example.com']);
        $this->mockSocialiteDriver($this->mockSocialiteUser('google-evil', 'vitima@example.com', 'Atacante', emailVerified: false));

        $this->postJson('/api/v1/auth/social/google', ['token' => 'valid-token'])->assertUnprocessable();

        $this->assertNull($user->fresh()->provider_id);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_login_takeover_of_a_pre_registered_account_revokes_the_attackers_password_and_tokens(): void
    {
        $account = User::factory()->unverified()->create(['email' => 'vitima@example.com', 'password' => 'senha-do-atacante']);
        $attackerToken = $account->createToken('atacante')->plainTextToken;
        $this->mockSocialiteDriver($this->mockSocialiteUser('google-real', 'vitima@example.com'));

        $this->postJson('/api/v1/auth/social/google', ['token' => 'valid-token'])->assertOk();

        $this->assertNull($account->fresh()->password);
        $this->assertSame(1, $account->fresh()->tokens()->count(), 'só o token da nova sessão social');
        $this->assertFalse($account->fresh()->tokens()->where('name', 'atacante')->exists());
        $this->postJson('/api/v1/auth/login', ['email' => 'vitima@example.com', 'password' => 'senha-do-atacante'])->assertUnauthorized();
    }
}
