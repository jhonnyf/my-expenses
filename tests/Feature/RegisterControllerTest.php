<?php

namespace Tests\Feature;

use App\Jobs\GeocodeUserProfileJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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
            'name' => 'João Silva',
            'email' => 'joao@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('dashboard.index'));

        $this->assertDatabaseHas('users', [
            'name' => 'João Silva',
            'email' => 'joao@example.com',
        ]);
    }

    public function test_store_logs_in_user_after_registration(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
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
            'name' => 'Other User',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors(['email']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_store_rejects_password_without_confirmation(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ])->assertSessionHasErrors(['password']);
    }

    public function test_store_rejects_password_shorter_than_8_characters(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => '123',
            'password_confirmation' => '123',
        ])->assertSessionHasErrors(['password']);
    }

    public function test_store_rejects_name_shorter_than_2_characters(): void
    {
        $this->post('/register', [
            'name' => 'A',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors(['name']);
    }

    public function test_store_rejects_invalid_email_format(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'not-an-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors(['email']);
    }

    public function test_login_page_has_link_to_register(): void
    {
        $this->get('/login')
            ->assertStatus(200)
            ->assertSee(route('register.index'));
    }

    public function test_store_creates_profile_with_cidade_estado_when_provided(): void
    {
        Queue::fake();

        $this->post('/register', [
            'name' => 'Maria Silva',
            'email' => 'maria@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'cidade' => 'Curitiba',
            'estado' => 'PR',
        ])->assertRedirect(route('dashboard.index'));

        $user = User::where('email', 'maria@example.com')->firstOrFail();
        $this->assertDatabaseHas('users_profiles', [
            'user_id' => $user->id,
            'cidade' => 'Curitiba',
            'estado' => 'PR',
        ]);
        Queue::assertPushed(GeocodeUserProfileJob::class);
    }

    public function test_store_without_cidade_estado_does_not_create_profile(): void
    {
        Queue::fake();

        $this->post('/register', [
            'name' => 'Sem Cidade',
            'email' => 'semcidade@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'semcidade@example.com')->firstOrFail();
        $this->assertDatabaseMissing('users_profiles', ['user_id' => $user->id]);
        Queue::assertNotPushed(GeocodeUserProfileJob::class);
    }

    public function test_store_rejects_estado_with_invalid_length(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'estado' => 'PRR',
        ])->assertSessionHasErrors(['estado']);
    }
}
