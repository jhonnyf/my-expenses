<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
            'token'                 => $token,
            'email'                 => 'user@example.com',
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertRedirect(route('dashboard.index'));

        $this->assertAuthenticated();
        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    public function test_reset_with_invalid_token_returns_error(): void
    {
        User::factory()->create(['email' => 'user@example.com']);

        $this->post('/reset-password', [
            'token'                 => 'invalid-token',
            'email'                 => 'user@example.com',
            'password'              => 'newpassword123',
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
            'token'                 => $token,
            'email'                 => 'user@example.com',
            'password'              => '123',
            'password_confirmation' => '123',
        ])->assertSessionHasErrors(['password']);
    }

    public function test_reset_rejects_mismatched_password_confirmation(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token'                 => $token,
            'email'                 => 'user@example.com',
            'password'              => 'newpassword123',
            'password_confirmation' => 'differentpassword',
        ])->assertSessionHasErrors(['password']);
    }
}
