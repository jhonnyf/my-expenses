<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class SocialAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private function mockSocialiteUser(string $id, ?string $email, string $name = 'Social User'): SocialiteUser
    {
        $user = Mockery::mock(SocialiteUser::class);
        $user->shouldReceive('getId')->andReturn($id);
        $user->shouldReceive('getEmail')->andReturn($email);
        $user->shouldReceive('getName')->andReturn($name);
        $user->shouldReceive('getNickname')->andReturn(null);

        return $user;
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
}
