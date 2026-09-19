<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ResetPasswordControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_page_returns_200(): void
    {
        $this->get('/reset-password?token=sometoken&email=user@example.com')
            ->assertStatus(200);
    }

    public function test_open_app_page_links_to_deep_link_with_fixed_scheme(): void
    {
        $this->get('/reset-password/app?token=abc123&email=user@example.com')
            ->assertStatus(200)
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSee('cestazen://reset-password?token=abc123&amp;email=user%40example.com', false)
            ->assertSee(e(route('password.reset', ['token' => 'abc123', 'email' => 'user@example.com'])), false);
    }

    public function test_open_app_page_does_not_allow_overriding_the_destination(): void
    {
        $this->get('/reset-password/app?token=abc&email=a@b.com&url=https://evil.example')
            ->assertStatus(200)
            ->assertDontSee('evil.example');
    }

    public function test_open_app_page_returns_404_without_token_or_email(): void
    {
        $this->get('/reset-password/app')->assertStatus(404);
        $this->get('/reset-password/app?token=abc')->assertStatus(404);
    }

    public function test_reset_email_from_api_points_to_open_app_page(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertStatus(200);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            return str_contains($notification->toMail($user)->actionUrl, '/reset-password/app?token=');
        });
    }

    public function test_reset_email_from_web_keeps_pointing_to_web_page(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;

            return str_contains($url, '/reset-password?token=') && ! str_contains($url, '/app');
        });
    }

    public function test_reset_password_page_passes_token_and_email_to_view(): void
    {
        $this->get('/reset-password?token=abc123&email=user@example.com')
            ->assertStatus(200)
            ->assertViewHas('token', 'abc123')
            ->assertViewHas('email', 'user@example.com');
    }

    public function test_reset_with_valid_token_updates_password_and_logs_in_user(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'user@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertRedirect(route('dashboard.index'));

        $this->assertAuthenticated();
        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    public function test_reset_with_invalid_token_returns_error(): void
    {
        User::factory()->create(['email' => 'user@example.com']);

        $this->post('/reset-password', [
            'token' => 'invalid-token',
            'email' => 'user@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertSessionHasErrors(['email']);

        $this->assertGuest();
    }

    public function test_reset_validates_required_fields(): void
    {
        $this->post('/reset-password', [])
            ->assertSessionHasErrors(['token', 'email', 'password']);
    }

    public function test_reset_rejects_password_shorter_than_8_characters(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'user@example.com',
            'password' => '123',
            'password_confirmation' => '123',
        ])->assertSessionHasErrors(['password']);
    }

    public function test_reset_rejects_mismatched_password_confirmation(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'user@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'differentpassword',
        ])->assertSessionHasErrors(['password']);
    }
}
