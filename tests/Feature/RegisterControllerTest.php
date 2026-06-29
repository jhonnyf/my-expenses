<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_page_returns_200(): void
    {
        $this->get('/register')->assertStatus(200);
    }

    public function test_register_page_redirects_to_dashboard_when_already_authenticated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/register')
            ->assertRedirect(route('dashboard.index'));
    }

    public function test_store_creates_user_and_redirects_to_dashboard(): void
    {
        $this->post('/register', [
            'name'                  => 'João Silva',
            'email'                 => 'joao@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('dashboard.index'));

        $this->assertDatabaseHas('users', [
            'name'  => 'João Silva',
            'email' => 'joao@example.com',
        ]);
    }

    public function test_store_logs_in_user_after_registration(): void
    {
        $this->post('/register', [
            'name'                  => 'Test User',
            'email'                 => 'test@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertAuthenticated();
    }

    public function test_store_validates_required_fields(): void
    {
        $this->post('/register', [])
            ->assertSessionHasErrors(['name', 'email', 'password']);
    }

    public function test_store_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $this->post('/register', [
            'name'                  => 'Other User',
            'email'                 => 'existing@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors(['email']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_store_rejects_password_without_confirmation(): void
    {
        $this->post('/register', [
            'name'     => 'Test User',
            'email'    => 'test@example.com',
            'password' => 'password123',
        ])->assertSessionHasErrors(['password']);
    }

    public function test_store_rejects_password_shorter_than_8_characters(): void
    {
        $this->post('/register', [
            'name'                  => 'Test User',
            'email'                 => 'test@example.com',
            'password'              => '123',
            'password_confirmation' => '123',
        ])->assertSessionHasErrors(['password']);
    }

    public function test_store_rejects_name_shorter_than_2_characters(): void
    {
        $this->post('/register', [
            'name'                  => 'A',
            'email'                 => 'test@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors(['name']);
    }

    public function test_store_rejects_invalid_email_format(): void
    {
        $this->post('/register', [
            'name'                  => 'Test User',
            'email'                 => 'not-an-email',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors(['email']);
    }

    public function test_login_page_has_link_to_register(): void
    {
        $this->get('/login')
            ->assertStatus(200)
            ->assertSee(route('register.index'));
    }
}
