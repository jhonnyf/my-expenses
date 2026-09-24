<?php

namespace Tests\Feature\Api\V1;

use App\Jobs\ExportPersonalDataJob;
use App\Jobs\GeocodeUserProfileJob;
use App\Models\Invoice;
use App\Models\Issuer;
use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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
                    'user' => ['id', 'name', 'email'],
                    'stats' => ['total_invoices', 'total_items', 'total_spent', 'member_since'],
                    'recent_invoices',
                    'location_suggestion',
                ],
            ])
            ->assertJsonPath('data.user.id', $user->id);
    }

    public function test_show_includes_subscription_plan(): void
    {
        $user = User::factory()->pro()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/account')
            ->assertStatus(200)
            ->assertJsonPath('data.user.subscription.plan', 'pro');
    }

    public function test_show_includes_location_suggestion_when_applicable(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create(['city' => 'Curitiba', 'state' => 'PR']);
        Invoice::factory()->for($user)->for($issuer)->count(4)->create(['issued_at' => now()->subDays(5)]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/account')
            ->assertStatus(200)
            ->assertJsonPath('data.location_suggestion.city', 'Curitiba')
            ->assertJsonPath('data.location_suggestion.state', 'PR');
    }

    public function test_dismiss_location_suggestion_returns_401_when_unauthenticated(): void
    {
        $this->postJson('/api/v1/account/location-suggestion/dismiss')->assertStatus(401);
    }

    public function test_dismiss_location_suggestion_stores_timestamp(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/account/location-suggestion/dismiss')
            ->assertStatus(200);

        $this->assertNotNull($user->profile()->first()->location_suggestion_dismissed_at);
    }

    public function test_capture_location_returns_401_when_unauthenticated(): void
    {
        $this->postJson('/api/v1/account/location/capture', ['latitude' => -25.4284, 'longitude' => -49.2733])
            ->assertStatus(401);
    }

    public function test_capture_location_saves_profile_from_reverse_geocoding(): void
    {
        Http::fake([
            '*nominatim*' => Http::response([
                'address' => ['city' => 'Curitiba', 'state' => 'Paraná'],
            ], 200),
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/account/location/capture', ['latitude' => -25.4284, 'longitude' => -49.2733]);

        $response->assertStatus(200)
            ->assertJsonPath('data.cidade', 'Curitiba')
            ->assertJsonPath('data.estado', 'PR');

        $this->assertDatabaseHas('users_profiles', [
            'user_id' => $user->id,
            'cidade' => 'Curitiba',
            'estado' => 'PR',
        ]);
    }

    public function test_capture_location_returns_422_when_reverse_geocoding_fails(): void
    {
        Http::fake(['*nominatim*' => Http::response([], 200)]);
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/account/location/capture', ['latitude' => -25.4284, 'longitude' => -49.2733])
            ->assertStatus(422);
    }

    public function test_capture_location_validates_latitude_and_longitude_range(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/account/location/capture', ['latitude' => -25.4284, 'longitude' => 999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['longitude']);
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
            ->patchJson('/api/v1/account', ['name' => 'Nome Novo', 'email' => 'new@example.com', 'current_password' => 'password'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Nome Novo');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Nome Novo', 'email' => 'new@example.com']);
    }

    public function test_update_creates_profile_with_cidade_estado(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/account', [
                'name' => $user->name,
                'email' => $user->email,
                'cidade' => 'Belo Horizonte',
                'estado' => 'MG',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.cidade', 'Belo Horizonte')
            ->assertJsonPath('data.estado', 'MG');

        $this->assertDatabaseHas('users_profiles', [
            'user_id' => $user->id,
            'cidade' => 'Belo Horizonte',
            'estado' => 'MG',
        ]);
        Queue::assertPushed(GeocodeUserProfileJob::class);
    }

    public function test_update_does_not_redispatch_geocode_job_when_location_unchanged(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        UserProfile::factory()->for($user)->create(['cidade' => 'Curitiba', 'estado' => 'PR']);

        $this->actingAs($user, 'sanctum')->patchJson('/api/v1/account', [
            'name' => $user->name,
            'email' => $user->email,
            'cidade' => 'Curitiba',
            'estado' => 'PR',
        ]);

        Queue::assertNotPushed(GeocodeUserProfileJob::class);
    }

    public function test_update_rejects_estado_with_invalid_length(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/account', [
                'name' => $user->name,
                'email' => $user->email,
                'estado' => 'PRR',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['estado']);
    }

    public function test_update_password_returns_401_when_unauthenticated(): void
    {
        $this->patchJson('/api/v1/account/password', [
            'current_password' => 'password',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ])->assertStatus(401);
    }

    public function test_update_password_rejects_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/account/password', [
                'current_password' => 'wrongpassword',
                'password' => 'newpassword1',
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
                'current_password' => 'password',
                'password' => 'newpassword1',
                'password_confirmation' => 'newpassword1',
            ])
            ->assertStatus(200);

        $this->assertTrue(Hash::check('newpassword1', $user->fresh()->password));
    }

    public function test_update_avatar_returns_401_when_unauthenticated(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('avatar.png', 100, 100);

        $this->postJson('/api/v1/account/avatar', ['avatar' => $file])->assertStatus(401);
    }

    public function test_update_avatar_rejects_non_image_file(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/account/avatar', ['avatar' => $file])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);
    }

    public function test_update_avatar_stores_file_and_returns_avatar_url(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('avatar.png', 200, 200);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/account/avatar', ['avatar' => $file])
            ->assertStatus(200)
            ->assertJsonPath('data.name', $user->name);

        $user->refresh();
        $this->assertNotNull($user->avatar);
        Storage::disk('public')->assertExists($user->avatar->path);
    }

    public function test_request_export_dispatches_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/account/export')
            ->assertStatus(202);

        Queue::assertPushed(ExportPersonalDataJob::class);
    }

    public function test_request_export_returns_401_when_unauthenticated(): void
    {
        $this->postJson('/api/v1/account/export')->assertStatus(401);
    }

    public function test_destroy_returns_401_when_unauthenticated(): void
    {
        $this->deleteJson('/api/v1/account', ['current_password' => 'password'])->assertStatus(401);
    }

    public function test_destroy_rejects_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/account', ['current_password' => 'wrongpassword'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);

        $this->assertModelExists($user);
    }

    public function test_destroy_deletes_account_and_revokes_token(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $token = $user->createToken('test-device');

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->deleteJson('/api/v1/account', ['current_password' => 'password'])
            ->assertStatus(200);

        $this->assertModelMissing($user);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_changing_email_requires_current_password_and_resets_verification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'old@example.com']);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/account', ['name' => 'X Y', 'email' => 'new@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/account', ['name' => 'X Y', 'email' => 'NEW@example.com', 'current_password' => 'password'])
            ->assertOk();

        $user->refresh();
        $this->assertSame('new@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_keeping_the_email_does_not_require_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/account', ['name' => 'Novo Nome', 'email' => $user->email])
            ->assertOk();

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_update_rejects_unknown_state_and_normalizes_case(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/account', ['name' => 'Fulano', 'email' => $user->email, 'cidade' => 'Curitiba', 'estado' => 'XX'])
            ->assertStatus(422)->assertJsonValidationErrors('estado');

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/account', ['name' => 'Fulano', 'email' => $user->email, 'cidade' => 'Curitiba', 'estado' => 'pr'])
            ->assertOk();

        $this->assertSame('PR', $user->fresh()->profile->estado);
    }

    public function test_clearing_city_and_state_removes_the_location(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        UserProfile::factory()->for($user)->create(['cidade' => 'Curitiba', 'estado' => 'PR', 'latitude' => -25.4, 'longitude' => -49.2]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/account', ['name' => 'Fulano', 'email' => $user->email, 'cidade' => null, 'estado' => null])
            ->assertOk();

        $profile = $user->profile()->first();
        $this->assertNull($profile->cidade);
        $this->assertNull($profile->latitude);
    }

    public function test_omitting_location_keeps_it(): void
    {
        $user = User::factory()->create();
        UserProfile::factory()->for($user)->create(['cidade' => 'Curitiba', 'estado' => 'PR']);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/account', ['name' => 'Fulano', 'email' => $user->email])
            ->assertOk();

        $this->assertSame('Curitiba', $user->profile()->first()->cidade);
    }

    public function test_social_account_can_set_a_password_without_current_one(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/account/password', ['password' => 'nova-senha-123', 'password_confirmation' => 'nova-senha-123'])
            ->assertOk();

        $this->assertTrue(Hash::check('nova-senha-123', $user->fresh()->password));
    }

    public function test_password_change_revokes_other_tokens_but_keeps_current(): void
    {
        $user = User::factory()->create();
        $user->createToken('outro-aparelho');
        $current = $user->createToken('este-aparelho');

        $this->withToken($current->plainTextToken)
            ->patchJson('/api/v1/account/password', ['current_password' => 'password', 'password' => 'nova-senha-123', 'password_confirmation' => 'nova-senha-123'])
            ->assertOk();

        $this->assertSame(['este-aparelho'], $user->tokens()->pluck('name')->all());
    }

    public function test_revoke_other_sessions_keeps_current_token(): void
    {
        $user = User::factory()->create();
        $user->createToken('outro-aparelho');
        $current = $user->createToken('este-aparelho');

        $this->withToken($current->plainTextToken)
            ->postJson('/api/v1/account/sessions/revoke-others')
            ->assertOk();

        $this->assertSame(['este-aparelho'], $user->tokens()->pluck('name')->all());
    }

    public function test_show_stats_use_registration_date_as_member_since(): void
    {
        $user = User::factory()->create(['created_at' => '2025-03-10 10:00:00']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/account')
            ->assertOk()
            ->assertJsonPath('data.stats.total_invoices', 0)
            ->assertJson(fn ($json) => $json->has('data.stats.member_since')->etc());
    }
}
