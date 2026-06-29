<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class SocialAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private function mockSocialiteUser(string $id, string $email, string $name = 'Social User'): SocialiteUser
    {
        $user = Mockery::mock(SocialiteUser::class);
        $user->shouldReceive('getId')->andReturn($id);
        $user->shouldReceive('getEmail')->andReturn($email);
        $user->shouldReceive('getName')->andReturn($name);
        $user->shouldReceive('getNickname')->andReturn(null);

        return $user;
    }

    private function mockSocialiteDriver(?SocialiteUser $socialiteUser = null): Provider
    {
        $driver = Mockery::mock(Provider::class);

        if ($socialiteUser) {
            $driver->shouldReceive('user')->andReturn($socialiteUser);
        }

        return $driver;
    }

    public function test_redirect_returns_redirect_response_for_valid_provider(): void
    {
        $driver = $this->mockSocialiteDriver();
        $driver->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->get(route('login.social.redirect', 'google'))
            ->assertRedirect();
    }

    public function test_redirect_returns_404_for_invalid_provider(): void
    {
        $this->get(route('login.social.redirect', 'tiktok'))
            ->assertNotFound();
    }

    public function test_callback_returns_404_for_invalid_provider(): void
    {
        $this->get(route('login.social.callback', 'tiktok'))
            ->assertNotFound();
    }

    public function test_callback_logs_in_existing_user_and_redirects_to_dashboard(): void
    {
        $user = User::factory()->create([
            'provider' => 'google',
            'provider_id' => 'google-123',
        ]);

        $socialiteUser = $this->mockSocialiteUser('google-123', $user->email);
        $driver = $this->mockSocialiteDriver($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->get(route('login.social.callback', 'google'))
            ->assertRedirect(route('dashboard.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_callback_creates_new_user_on_first_login(): void
    {
        $socialiteUser = $this->mockSocialiteUser('google-new', 'newuser@example.com', 'New User');
        $driver = $this->mockSocialiteDriver($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->get(route('login.social.callback', 'google'))
            ->assertRedirect(route('dashboard.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'provider' => 'google',
            'provider_id' => 'google-new',
        ]);
        $this->assertAuthenticated();
    }

    public function test_callback_links_provider_to_existing_email_user(): void
    {
        $user = User::factory()->create(['email' => 'existing@example.com']);

        $socialiteUser = $this->mockSocialiteUser('google-456', 'existing@example.com');
        $driver = $this->mockSocialiteDriver($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->get(route('login.social.callback', 'google'))
            ->assertRedirect(route('dashboard.index'));

        $this->assertDatabaseCount('users', 1);
        $this->assertAuthenticatedAs($user);
    }

    public function test_callback_redirects_to_login_with_error_on_socialite_exception(): void
    {
        $driver = $this->mockSocialiteDriver();
        $driver->shouldReceive('user')->andThrow(new \Exception('OAuth error'));

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->get(route('login.social.callback', 'google'))
            ->assertRedirect(route('login.index'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_callback_works_with_facebook_provider(): void
    {
        $socialiteUser = $this->mockSocialiteUser('fb-123', 'fbuser@example.com', 'Facebook User');
        $driver = $this->mockSocialiteDriver($socialiteUser);

        Socialite::shouldReceive('driver')->with('facebook')->andReturn($driver);

        $this->get(route('login.social.callback', 'facebook'))
            ->assertRedirect(route('dashboard.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'fbuser@example.com',
            'provider' => 'facebook',
        ]);
    }

    public function test_callback_works_with_apple_provider(): void
    {
        $socialiteUser = $this->mockSocialiteUser('apple-001', 'appleuser@example.com', 'Apple User');
        $driver = $this->mockSocialiteDriver($socialiteUser);

        Socialite::shouldReceive('driver')->with('apple')->andReturn($driver);

        $this->get(route('login.social.callback', 'apple'))
            ->assertRedirect(route('dashboard.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'appleuser@example.com',
            'provider' => 'apple',
        ]);
    }
}
