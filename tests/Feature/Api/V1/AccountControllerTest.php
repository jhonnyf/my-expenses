<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/account')->assertStatus(401);
    }

    public function test_show_returns_account_data(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/account')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'user'  => ['id', 'name', 'email'],
                    'stats' => ['total_invoices', 'total_items', 'total_spent', 'member_since'],
                    'recent_invoices',
                ],
            ])
            ->assertJsonPath('data.user.id', $user->id);
    }

    public function test_update_returns_401_when_unauthenticated(): void
    {
        $this->patchJson('/api/v1/account', ['name' => 'Novo Nome', 'email' => 'novo@example.com'])
            ->assertStatus(401);
    }

    public function test_update_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/account', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email']);
    }

    public function test_update_modifies_name_and_email(): void
    {
        $user = User::factory()->create(['name' => 'Nome Antigo', 'email' => 'old@example.com']);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/account', ['name' => 'Nome Novo', 'email' => 'new@example.com'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Nome Novo');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Nome Novo', 'email' => 'new@example.com']);
    }

    public function test_update_password_returns_401_when_unauthenticated(): void
    {
        $this->patchJson('/api/v1/account/password', [
            'current_password'      => 'password',
            'password'              => 'newpassword',
            'password_confirmation' => 'newpassword',
        ])->assertStatus(401);
    }

    public function test_update_password_rejects_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/account/password', [
                'current_password'      => 'wrongpassword',
                'password'              => 'newpassword1',
                'password_confirmation' => 'newpassword1',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_update_password_changes_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/account/password', [
                'current_password'      => 'password',
                'password'              => 'newpassword1',
                'password_confirmation' => 'newpassword1',
            ])
            ->assertStatus(200);

        $this->assertTrue(Hash::check('newpassword1', $user->fresh()->password));
    }
}
