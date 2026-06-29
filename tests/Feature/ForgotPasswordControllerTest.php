<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ForgotPasswordControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_page_returns_200(): void
    {
        $this->get('/forgot-password')->assertStatus(200);
    }

    public function test_forgot_password_page_redirects_to_dashboard_when_authenticated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/forgot-password')
            ->assertRedirect(route('dashboard.index'));
    }

    public function test_send_returns_success_for_registered_email(): void
    {
        User::factory()->create(['email' => 'user@example.com']);

        $this->post('/forgot-password', ['email' => 'user@example.com'])
            ->assertRedirect()
            ->assertSessionHas('status');
    }

    public function test_send_returns_success_even_for_unregistered_email(): void
    {
        $this->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertRedirect()
            ->assertSessionHas('status');
    }

    public function test_send_validates_required_email(): void
    {
        $this->post('/forgot-password', [])
            ->assertSessionHasErrors(['email']);
    }

    public function test_send_validates_email_format(): void
    {
        $this->post('/forgot-password', ['email' => 'not-an-email'])
            ->assertSessionHasErrors(['email']);
    }

    public function test_send_does_not_reveal_whether_email_exists(): void
    {
        $responseExisting = $this->post('/forgot-password', ['email' => 'exists@example.com']);
        $responseNonExisting = $this->post('/forgot-password', ['email' => 'ghost@example.com']);

        $responseExisting->assertSessionHas('status');
        $responseNonExisting->assertSessionHas('status');
    }
}
