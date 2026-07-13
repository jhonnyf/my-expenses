<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_returns_200(): void
    {
        $this->get('/login')->assertStatus(200);
    }

    public function test_login_page_redirects_to_dashboard_when_already_authenticated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/login')
            ->assertRedirect(route('dashboard.index'));
    }

    public function test_execute_logs_in_user_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);

        $this->post('/login/execute', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertRedirect(route('dashboard.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_execute_redirects_to_intended_url_after_login(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);

        $this->get('/dashboard');

        $this->post('/login/execute', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertRedirect(route('dashboard.index'));
    }

    public function test_execute_displays_error_message_on_invalid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        $response = $this->from('/login')->post('/login/execute', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->followRedirects($response)->assertSee(__('auth.failed'));
    }

    public function test_execute_returns_error_with_invalid_password(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        $this->post('/login/execute', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors(['email']);

        $this->assertGuest();
    }

    public function test_execute_returns_error_with_unregistered_email(): void
    {
        $this->post('/login/execute', [
            'email' => 'nobody@example.com',
            'password' => 'password123',
        ])->assertSessionHasErrors(['email']);

        $this->assertGuest();
    }

    public function test_execute_validates_required_fields(): void
    {
        $this->post('/login/execute', [])
            ->assertSessionHasErrors(['email', 'password']);
    }

    public function test_execute_validates_email_format(): void
    {
        $this->post('/login/execute', [
            'email' => 'not-an-email',
            'password' => 'password123',
        ])->assertSessionHasErrors(['email']);
    }

    public function test_logout_signs_out_user_and_redirects_to_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/login/logout')
            ->assertRedirect(route('login.index'));

        $this->assertGuest();
    }

    public function test_logout_invalidates_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/login/logout');

        $this->assertGuest();
    }
}
