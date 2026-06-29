<?php

namespace Tests\Feature\Actions;

use App\Actions\FindOrCreateSocialUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class FindOrCreateSocialUserTest extends TestCase
{
    use RefreshDatabase;

    private function makeSocialiteUser(string $id, ?string $email, ?string $name = 'Social User'): SocialiteUser
    {
        $mock = Mockery::mock(SocialiteUser::class);
        $mock->shouldReceive('getId')->andReturn($id);
        $mock->shouldReceive('getEmail')->andReturn($email);
        $mock->shouldReceive('getName')->andReturn($name);
        $mock->shouldReceive('getNickname')->andReturn(null);

        return $mock;
    }

    public function test_returns_existing_user_by_provider_and_provider_id(): void
    {
        $user = User::factory()->create([
            'provider' => 'google',
            'provider_id' => 'google-123',
        ]);

        $socialiteUser = $this->makeSocialiteUser('google-123', $user->email);

        $result = app(FindOrCreateSocialUser::class)->handle($socialiteUser, 'google');

        $this->assertTrue($user->is($result));
        $this->assertDatabaseCount('users', 1);
    }

    public function test_links_provider_to_existing_user_by_email(): void
    {
        $user = User::factory()->create(['email' => 'existing@example.com']);

        $socialiteUser = $this->makeSocialiteUser('google-456', 'existing@example.com');

        $result = app(FindOrCreateSocialUser::class)->handle($socialiteUser, 'google');

        $this->assertTrue($user->is($result));
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'email' => 'existing@example.com',
            'provider' => 'google',
            'provider_id' => 'google-456',
        ]);
    }

    public function test_creates_new_user_when_no_match_found(): void
    {
        $socialiteUser = $this->makeSocialiteUser('google-789', 'new@example.com', 'New User');

        $result = app(FindOrCreateSocialUser::class)->handle($socialiteUser, 'google');

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'email' => 'new@example.com',
            'name' => 'New User',
            'provider' => 'google',
            'provider_id' => 'google-789',
        ]);
        $this->assertInstanceOf(User::class, $result);
    }

    public function test_creates_user_with_null_email_when_provider_does_not_return_email(): void
    {
        $socialiteUser = $this->makeSocialiteUser('apple-001', null, 'Apple User');

        $result = app(FindOrCreateSocialUser::class)->handle($socialiteUser, 'apple');

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'name' => 'Apple User',
            'email' => null,
            'provider' => 'apple',
            'provider_id' => 'apple-001',
        ]);
        $this->assertInstanceOf(User::class, $result);
    }

    public function test_does_not_link_provider_to_user_of_different_provider(): void
    {
        User::factory()->create([
            'provider' => 'facebook',
            'provider_id' => 'fb-123',
        ]);

        $socialiteUser = $this->makeSocialiteUser('google-123', 'other@example.com');

        $result = app(FindOrCreateSocialUser::class)->handle($socialiteUser, 'google');

        $this->assertDatabaseCount('users', 2);
        $this->assertSame('google', $result->provider);
        $this->assertSame('google-123', $result->provider_id);
    }

    public function test_uses_nickname_when_name_is_null(): void
    {
        $mock = Mockery::mock(SocialiteUser::class);
        $mock->shouldReceive('getId')->andReturn('google-000');
        $mock->shouldReceive('getEmail')->andReturn('nick@example.com');
        $mock->shouldReceive('getName')->andReturn(null);
        $mock->shouldReceive('getNickname')->andReturn('mynickname');

        $result = app(FindOrCreateSocialUser::class)->handle($mock, 'google');

        $this->assertSame('mynickname', $result->name);
    }
}
