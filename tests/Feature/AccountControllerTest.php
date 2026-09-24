<?php

namespace Tests\Feature;

use App\Jobs\ExportPersonalDataJob;
use App\Jobs\GeocodeUserProfileJob;
use App\Models\Invoice;
use App\Models\Issuer;
use App\Models\ProductAlias;
use App\Models\RecurringDismissal;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_200_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/account')->assertStatus(200);
    }

    public function test_index_shows_delete_account_section(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/account')->assertStatus(200)->assertSee('Excluir minha conta');
    }

    public function test_index_shows_free_plan_badge_for_free_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/account')->assertStatus(200)->assertSee('Grátis');
    }

    public function test_index_shows_pro_plan_badge_for_pro_user(): void
    {
        $user = User::factory()->pro()->create();

        $this->actingAs($user)->get('/account')->assertStatus(200)->assertSee('Pro');
    }

    public function test_index_shows_location_suggestion_banner_when_applicable(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create(['city' => 'Curitiba', 'state' => 'PR']);
        Invoice::factory()->for($user)->for($issuer)->count(4)->create(['issued_at' => now()->subDays(5)]);

        $this->actingAs($user)
            ->get('/account')
            ->assertStatus(200)
            ->assertViewHas('locationSuggestion', ['city' => 'Curitiba', 'state' => 'PR'])
            ->assertSee('Curitiba/PR');
    }

    public function test_index_does_not_show_banner_without_suggestion(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/account')
            ->assertStatus(200)
            ->assertViewHas('locationSuggestion', null);
    }

    public function test_dismiss_location_suggestion_redirects_unauthenticated_user(): void
    {
        $this->post('/account/location-suggestion/dismiss')->assertRedirect('/login');
    }

    public function test_dismiss_location_suggestion_stores_timestamp(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/account/location-suggestion/dismiss')
            ->assertRedirect(route('account.index'));

        $this->assertNotNull($user->profile()->first()->location_suggestion_dismissed_at);
    }

    public function test_dismiss_location_suggestion_hides_banner_afterwards(): void
    {
        $user = User::factory()->create();
        $issuer = Issuer::factory()->create(['city' => 'Curitiba', 'state' => 'PR']);
        Invoice::factory()->for($user)->for($issuer)->count(4)->create(['issued_at' => now()->subDays(5)]);

        $this->actingAs($user)->post('/account/location-suggestion/dismiss');

        $this->actingAs($user)
            ->get('/account')
            ->assertViewHas('locationSuggestion', null);
    }

    public function test_capture_location_redirects_unauthenticated_user(): void
    {
        $this->post('/account/location/capture', ['latitude' => -25.4284, 'longitude' => -49.2733])
            ->assertRedirect('/login');
    }

    public function test_capture_location_saves_profile_from_reverse_geocoding(): void
    {
        Http::fake([
            '*nominatim*' => Http::response([
                'address' => ['city' => 'Curitiba', 'state' => 'Paraná'],
            ], 200),
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/account/location/capture', ['latitude' => -25.4284, 'longitude' => -49.2733]);

        $response->assertStatus(200)
            ->assertJson(['cidade' => 'Curitiba', 'estado' => 'PR']);

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

        $this->actingAs($user)
            ->postJson('/account/location/capture', ['latitude' => -25.4284, 'longitude' => -49.2733])
            ->assertStatus(422);
    }

    public function test_capture_location_validates_latitude_and_longitude_range(): void
    {
        // Rotas web (fora de api/*) sempre respondem falha de validação com
        // redirect + sessão (ver bootstrap/app.php shouldRenderJsonWhen), igual
        // ao restante do app — não é algo alcançável pelo fluxo real via
        // navegador (a Geolocation API nunca devolve lat/lng fora do intervalo),
        // só protege contra chamada direta ao endpoint.
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/account/location/capture', ['latitude' => 999, 'longitude' => -49.2733])
            ->assertSessionHasErrors(['latitude']);
    }

    public function test_update_redirects_unauthenticated_user(): void
    {
        $this->patch('/account', ['name' => 'X', 'email' => 'x@example.com'])->assertRedirect('/login');
    }

    public function test_update_saves_name_and_email(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/account', [
            'name' => 'Novo Nome',
            'email' => 'novo@example.com',
            'current_password' => 'password',
        ])->assertRedirect(route('account.index', ['tab' => 'settings']));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Novo Nome',
            'email' => 'novo@example.com',
        ]);
    }

    public function test_update_creates_profile_with_cidade_estado(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/account', [
            'name' => $user->name,
            'email' => $user->email,
            'cidade' => 'Belo Horizonte',
            'estado' => 'MG',
        ]);

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

        $this->actingAs($user)->patch('/account', [
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

        $this->actingAs($user)->patch('/account', [
            'name' => $user->name,
            'email' => $user->email,
            'estado' => 'PRR',
        ])->assertSessionHasErrors(['estado']);
    }

    public function test_update_avatar_redirects_unauthenticated_user(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('avatar.png', 100, 100);

        $this->post('/account/avatar', ['avatar' => $file])->assertRedirect('/login');
    }

    public function test_update_avatar_rejects_non_image_file(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $this->actingAs($user)
            ->post('/account/avatar', ['avatar' => $file])
            ->assertSessionHasErrors(['avatar']);
    }

    public function test_update_avatar_stores_file_and_associates_with_user(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('avatar.png', 200, 200);

        $response = $this->actingAs($user)->post('/account/avatar', ['avatar' => $file]);

        $response->assertRedirect(route('account.index'));

        $user->refresh();
        $this->assertNotNull($user->avatar);
        $this->assertSame('avatar', $user->avatar->collection);
        $this->assertSame(200, $user->avatar->width);
        $this->assertSame(200, $user->avatar->height);
        Storage::disk('public')->assertExists($user->avatar->path);
    }

    public function test_update_avatar_replaces_previous_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $firstFile = UploadedFile::fake()->image('first.png', 100, 100);
        $secondFile = UploadedFile::fake()->image('second.png', 100, 100);

        $this->actingAs($user)->post('/account/avatar', ['avatar' => $firstFile]);
        $firstPath = $user->refresh()->avatar->path;

        $this->actingAs($user)->post('/account/avatar', ['avatar' => $secondFile]);
        $user->refresh();

        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($user->avatar->path);
        $this->assertDatabaseCount('files', 1);
    }

    public function test_request_export_dispatches_job_and_redirects(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/account/export')
            ->assertRedirect(route('account.index', ['tab' => 'security']));

        Queue::assertPushed(ExportPersonalDataJob::class);
    }

    public function test_download_export_rejects_invalid_signature(): void
    {
        $user = User::factory()->create();
        $file = $user->files()->create([
            'collection' => 'personal-data-export',
            'disk' => 'local',
            'path' => 'exports/1/fake.json',
            'original_name' => 'meus-dados.json',
            'mime_type' => 'application/json',
            'size' => 10,
        ]);

        $this->actingAs($user)
            ->get("/account/export/download/{$file->id}")
            ->assertForbidden();
    }

    public function test_download_export_rejects_when_not_owner(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $other = User::factory()->create();
        Storage::disk('local')->put('exports/1/fake.json', '{}');
        $file = $owner->files()->create([
            'collection' => 'personal-data-export',
            'disk' => 'local',
            'path' => 'exports/1/fake.json',
            'original_name' => 'meus-dados.json',
            'mime_type' => 'application/json',
            'size' => 2,
        ]);

        $url = URL::temporarySignedRoute('account.export.download', now()->addDay(), ['file' => $file->id]);

        $this->actingAs($other)->get($url)->assertForbidden();
    }

    public function test_download_export_returns_file_for_owner(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        Storage::disk('local')->put('exports/1/fake.json', '{"foo":"bar"}');
        $file = $user->files()->create([
            'collection' => 'personal-data-export',
            'disk' => 'local',
            'path' => 'exports/1/fake.json',
            'original_name' => 'meus-dados.json',
            'mime_type' => 'application/json',
            'size' => 13,
        ]);

        $url = URL::temporarySignedRoute('account.export.download', now()->addDay(), ['file' => $file->id]);

        $this->actingAs($user)->get($url)->assertOk();
    }

    public function test_destroy_redirects_unauthenticated_user(): void
    {
        $this->delete('/account')->assertRedirect(route('login.index'));
    }

    public function test_destroy_rejects_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $this->actingAs($user)
            ->from('/account')
            ->delete('/account', ['current_password' => 'wrongpassword'])
            ->assertRedirect('/account')
            ->assertSessionHasErrors('current_password');

        $this->assertModelExists($user);
    }

    public function test_destroy_deletes_account_and_logs_out(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $response = $this->actingAs($user)
            ->delete('/account', ['current_password' => 'password']);

        $response->assertRedirect(route('login.index'));
        $this->assertModelMissing($user);
        $this->assertGuest();
    }

    public function test_page_masks_the_cpf_and_shows_registration_date(): void
    {
        $user = User::factory()->create(['created_at' => '2025-03-10 10:00:00']);
        UserProfile::factory()->for($user)->create(['cpf' => '12345678901']);

        $this->actingAs($user)->get('/account')
            ->assertOk()
            ->assertSee('123.***.***-01')
            ->assertDontSee('12345678901')
            ->assertSee('10/03/2025');
    }

    public function test_password_form_is_hidden_for_accounts_without_password(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->actingAs($user)->get('/account')
            ->assertOk()
            ->assertSee('Definir senha')
            ->assertDontSee('id="delete_current_password"', false);
    }

    public function test_social_account_can_delete_without_password(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->actingAs($user)->delete('/account')->assertRedirect(route('login.index'));

        $this->assertModelMissing($user);
    }

    public function test_destroy_removes_notifications(): void
    {
        $user = User::factory()->create();
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'x',
            'data' => ['a' => 1],
        ]);

        $this->actingAs($user)->delete('/account', ['current_password' => 'password']);

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_password_change_redirects_to_security_tab_and_revokes_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('app');

        $this->actingAs($user)->patch('/account/password', [
            'current_password' => 'password',
            'password' => 'nova-senha-123',
            'password_confirmation' => 'nova-senha-123',
        ])->assertRedirect(route('account.index', ['tab' => 'security']));

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_revoke_other_sessions_removes_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('app');

        $this->actingAs($user)->post('/account/sessions/revoke-others')
            ->assertRedirect(route('account.index', ['tab' => 'security']));

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_export_contains_the_new_sections(): void
    {
        Storage::fake('local');
        Notification::fake();
        $user = User::factory()->create();
        ProductAlias::create(['user_id' => $user->id, 'description' => 'LEITE INT', 'canonical_name' => 'LEITE']);
        RecurringDismissal::create(['user_id' => $user->id, 'description' => 'SAL']);

        (new ExportPersonalDataJob($user->id))->handle();

        $file = $user->files()->where('collection', 'personal-data-export')->firstOrFail();
        $data = json_decode(Storage::disk('local')->get($file->path), true);

        $this->assertSame('LEITE', $data['nomes_de_produtos'][0]['nome_padronizado']);
        $this->assertSame(['SAL'], $data['produtos_ocultados_das_compras_recorrentes']);
        $this->assertArrayHasKey('versao_dos_termos', $data['cadastro']);
    }
}
