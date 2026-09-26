<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialUser;
use Mockery;
use Tests\TestCase;

class SocialAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private function mockSocialiteUser(string $id, string $email, string $name = 'Social User', bool $emailVerified = true): SocialiteUser
    {
        return (new SocialUser)
            ->setRaw(['email_verified' => $emailVerified])
            ->map(['id' => $id, 'email' => $email, 'name' => $name, 'nickname' => null]);
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

        $user = User::where('email', 'newuser@example.com')->firstOrFail();
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertNull($user->terms_version);

        $this->get('/dashboard')->assertRedirect(route('terms.accept.form'));
    }

    public function test_callback_verifies_email_of_previously_unverified_existing_user(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'unverified@example.com']);

        $socialiteUser = $this->mockSocialiteUser('google-789', 'unverified@example.com');
        $driver = $this->mockSocialiteDriver($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->get(route('login.social.callback', 'google'))
            ->assertRedirect(route('dashboard.index'));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
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

    public function test_callback_refuses_to_link_an_account_when_the_provider_does_not_verify_the_email(): void
    {
        $user = User::factory()->create(['email' => 'vitima@example.com']);
        $socialiteUser = $this->mockSocialiteUser('google-evil', 'vitima@example.com', 'Atacante', emailVerified: false);
        Socialite::shouldReceive('driver')->with('google')->andReturn($this->mockSocialiteDriver($socialiteUser));

        $this->get(route('login.social.callback', 'google'))->assertRedirect(route('login.index'));

        $this->assertGuest();
        $this->assertNull($user->fresh()->provider_id);
    }

    public function test_callback_takeover_of_a_pre_registered_account_does_not_leave_the_attackers_password(): void
    {
        User::factory()->unverified()->create(['email' => 'vitima@example.com', 'password' => 'senha-do-atacante']);
        $socialiteUser = $this->mockSocialiteUser('google-real', 'vitima@example.com');
        Socialite::shouldReceive('driver')->with('google')->andReturn($this->mockSocialiteDriver($socialiteUser));

        $this->get(route('login.social.callback', 'google'))->assertRedirect(route('dashboard.index'));

        $this->assertNull(User::where('email', 'vitima@example.com')->firstOrFail()->password);
    }
}
