<?php

namespace Tests\Feature;

use App\Jobs\GeocodeUserProfileJob;
use App\Models\Category;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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

    public function test_store_creates_user_and_redirects_to_verification_notice(): void
    {
        $this->post('/register', [
            'name' => 'João Silva',
            'email' => 'joao@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'accept_terms' => '1',
        ])->assertRedirect(route('verification.notice'));

        $this->assertDatabaseHas('users', [
            'name' => 'João Silva',
            'email' => 'joao@example.com',
        ]);
    }

    public function test_store_creates_user_with_unverified_email(): void
    {
        $this->post('/register', [
            'name' => 'João Silva',
            'email' => 'joao@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'accept_terms' => '1',
        ]);

        $user = User::where('email', 'joao@example.com')->firstOrFail();
        $this->assertFalse($user->hasVerifiedEmail());
    }

    public function test_store_sends_verification_email(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'João Silva',
            'email' => 'joao@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'accept_terms' => '1',
        ]);

        $user = User::where('email', 'joao@example.com')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_unverified_user_is_redirected_to_verification_notice_when_accessing_dashboard(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_store_logs_in_user_after_registration(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'accept_terms' => '1',
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
            'accept_terms' => '1',
        ])->assertSessionHasErrors(['email']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_store_rejects_password_without_confirmation(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'accept_terms' => '1',
        ])->assertSessionHasErrors(['password']);
    }

    public function test_store_rejects_password_shorter_than_8_characters(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => '123',
            'password_confirmation' => '123',
            'accept_terms' => '1',
        ])->assertSessionHasErrors(['password']);
    }

    public function test_store_rejects_name_shorter_than_2_characters(): void
    {
        $this->post('/register', [
            'name' => 'A',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'accept_terms' => '1',
        ])->assertSessionHasErrors(['name']);
    }

    public function test_store_rejects_invalid_email_format(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'not-an-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'accept_terms' => '1',
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
            'accept_terms' => '1',
        ])->assertRedirect(route('verification.notice'));

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
            'accept_terms' => '1',
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
            'accept_terms' => '1',
        ])->assertSessionHasErrors(['estado']);
    }

    public function test_store_creates_default_categories_for_user(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'categorias@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'accept_terms' => '1',
        ]);

        $user = User::where('email', 'categorias@example.com')->firstOrFail();
        $this->assertSame(11, Category::where('user_id', $user->id)->count());
    }

    public function test_store_rejects_registration_without_accept_terms(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'semtermos@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors(['accept_terms']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_store_rejects_registration_with_accept_terms_false(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'termosfalso@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'accept_terms' => '0',
        ])->assertSessionHasErrors(['accept_terms']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_store_records_terms_accepted_at_timestamp(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'aceitoutermos@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'accept_terms' => '1',
        ]);

        $user = User::where('email', 'aceitoutermos@example.com')->firstOrFail();
        $this->assertNotNull($user->terms_accepted_at);
    }

    public function test_register_page_has_links_to_legal_pages(): void
    {
        $this->get('/register')
            ->assertStatus(200)
            ->assertSee(route('legal.terms'))
            ->assertSee(route('legal.privacy'));
    }
}
