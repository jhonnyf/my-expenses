<?php

namespace Tests\Feature\Security;

use App\Actions\LogQrCodeReadAction;
use App\Models\File;
use App\Models\User;
use App\Services\NFCeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Endurecimentos menores: e-mail como dado de acesso, limites de tentativa, links de exportação,
 * log do QR Code, portais SEFAZ e documentação da API.
 */
class HardeningTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = '35260612345678000190650010000012341234567890';

    // ─── trocar o e-mail encerra os outros acessos ──────────────────────────

    public function test_api_email_change_revokes_other_tokens_but_keeps_the_current_one(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('atual')->plainTextToken;
        $user->createToken('outro-aparelho');
        $other = User::factory()->create();
        $otherToken = $other->createToken('outro-usuario');

        $this->withToken($current)->patchJson('/api/v1/account', [
            'name' => $user->name, 'email' => 'novo-email@example.com', 'current_password' => 'password',
        ])->assertOk();

        $this->assertSame(['atual'], $user->tokens()->pluck('name')->all());
        $this->assertModelExists($otherToken->accessToken);
    }

    public function test_api_update_without_email_change_keeps_every_token(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('atual')->plainTextToken;
        $user->createToken('outro-aparelho');

        $this->withToken($current)->patchJson('/api/v1/account', ['name' => 'Outro Nome', 'email' => $user->email])->assertOk();

        $this->assertSame(2, $user->tokens()->count());
    }

    public function test_web_email_change_revokes_tokens_and_remember_token(): void
    {
        $user = User::factory()->create(['remember_token' => 'antigo']);
        $user->createToken('app');

        $this->actingAs($user)->patch(route('account.update'), [
            'name' => $user->name, 'email' => 'novo-email@example.com', 'current_password' => 'password',
        ])->assertRedirect();

        $this->assertSame(0, $user->tokens()->count());
        $this->assertNotSame('antigo', $user->fresh()->remember_token);
    }

    public function test_web_update_without_email_change_keeps_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('app');

        $this->actingAs($user)->patch(route('account.update'), ['name' => 'Outro Nome', 'email' => $user->email])->assertRedirect();

        $this->assertSame(1, $user->tokens()->count());
    }

    // ─── limites de tentativa ────────────────────────────────────────────────

    public function test_web_auth_forms_are_rate_limited(): void
    {
        $forms = [
            ['login.execute', ['email' => 'a@example.com', 'password' => 'errada']],
            ['register.store', ['email' => 'nao-e-email']],
            ['password.email', ['email' => 'a@example.com']],
            ['password.update', ['token' => 'x', 'email' => 'a@example.com']],
        ];

        foreach ($forms as [$route, $payload]) {
            // O limite `throttle:5,1` é um balde só por IP, compartilhado entre estes formulários.
            app('cache')->flush();
            $statuses = collect(range(1, 6))->map(fn () => $this->post(route($route), $payload)->getStatusCode());

            $this->assertNotContains(429, $statuses->take(5)->all(), "{$route} bloqueou cedo demais");
            $this->assertSame(429, $statuses->last(), "{$route} não limitou a 6ª tentativa");
        }
    }

    public function test_api_auth_is_rate_limited_per_ip(): void
    {
        $statuses = collect(range(1, 11))->map(
            fn () => $this->postJson('/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'errada'])->getStatusCode()
        );

        $this->assertNotContains(429, $statuses->take(10)->all());
        $this->assertSame(429, $statuses->last());
    }

    public function test_sensitive_account_routes_are_rate_limited(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $statuses = collect(range(1, 6))->map(
            fn () => $this->patchJson(route('account.password'), ['current_password' => 'errada'])->getStatusCode()
        );

        $this->assertSame(429, $statuses->last());
    }

    // ─── exportação de dados: link assinado e expiração ─────────────────────

    private function exportFor(User $user): File
    {
        Storage::fake('local');
        $path = "exports/{$user->id}/x.json";
        Storage::disk('local')->put($path, '{}');

        return $user->files()->create([
            'collection' => 'personal-data-export', 'disk' => 'local', 'path' => $path,
            'original_name' => 'meus-dados.json', 'mime_type' => 'application/json', 'size' => 2,
        ]);
    }

    public function test_export_link_works_for_the_owner_only_while_valid(): void
    {
        $user = User::factory()->create();
        $file = $this->exportFor($user);

        $valid = URL::temporarySignedRoute('account.export.download', now()->addDay(), ['file' => $file->id]);
        $this->actingAs($user)->get($valid)->assertOk();

        $expired = URL::temporarySignedRoute('account.export.download', now()->subMinute(), ['file' => $file->id]);
        $this->actingAs($user)->get($expired)->assertForbidden();

        $unsigned = route('account.export.download', ['file' => $file->id]);
        $this->actingAs($user)->get($unsigned)->assertForbidden();

        // Outro id com a assinatura do primeiro: 403 (assinatura) ou 404 (id inexistente), nunca o arquivo.
        $tampered = str_replace("/{$file->id}?", '/'.($file->id + 1).'?', $valid);
        $this->assertContains($this->actingAs($user)->get($tampered)->getStatusCode(), [403, 404]);
    }

    public function test_export_link_requires_login(): void
    {
        $user = User::factory()->create();
        $file = $this->exportFor($user);

        $this->get(URL::temporarySignedRoute('account.export.download', now()->addDay(), ['file' => $file->id]))
            ->assertRedirect(route('login.index'));
    }

    public function test_old_exports_are_pruned_together_with_the_physical_file(): void
    {
        $user = User::factory()->create();
        $old = $this->exportFor($user);
        $old->forceFill(['created_at' => now()->subDays(8)])->save();
        Storage::disk('local')->put('exports/recente.json', '{}');
        $recent = $user->files()->create([
            'collection' => 'personal-data-export', 'disk' => 'local', 'path' => 'exports/recente.json',
            'original_name' => 'meus-dados.json', 'mime_type' => 'application/json', 'size' => 2,
        ]);
        $avatar = $user->files()->create([
            'collection' => 'avatar', 'disk' => 'local', 'path' => 'avatars/a.png',
            'original_name' => 'a.png', 'mime_type' => 'image/png', 'size' => 1,
        ]);
        $avatar->forceFill(['created_at' => now()->subYear()])->save();

        $this->artisan('model:prune', ['--model' => File::class])->assertSuccessful();

        $this->assertModelMissing($old);
        Storage::disk('local')->assertMissing($old->path);
        $this->assertModelExists($recent);
        Storage::disk('local')->assertExists('exports/recente.json');
        $this->assertModelExists($avatar, 'só exportações vencem; avatar antigo fica');
    }

    // ─── log do QR Code e portais SEFAZ ──────────────────────────────────────

    public function test_qr_log_never_stores_the_full_access_key(): void
    {
        $log = app(LogQrCodeReadAction::class)->execute(
            null,
            'https://nfce.fazenda.sp.gov.br/NFCeConsultaPublica/Paginas/ConsultaQRCode.aspx?p='.self::KEY.'|2|1|15|45.90|abcdef',
            success: true,
        );

        $stored = $log->fresh()->qrcode_url;

        $this->assertStringNotContainsString(self::KEY, $stored);
        $this->assertStringNotContainsString('abcdef', $stored);
        $this->assertStringContainsString('https://nfce.fazenda.sp.gov.br/NFCeConsultaPublica/Paginas/ConsultaQRCode.aspx', $stored);
        $this->assertStringContainsString(substr(self::KEY, 0, 20), $stored);
    }

    public function test_qr_log_handles_urls_without_a_key(): void
    {
        $log = app(LogQrCodeReadAction::class)->execute(null, 'nao é uma url ?x=1', success: false, errorMessage: 'erro');

        $this->assertStringNotContainsString('x=1', $log->fresh()->qrcode_url);
    }

    public function test_rejected_portal_host_is_logged_and_can_be_allowed_by_configuration(): void
    {
        Log::spy();
        $service = app(NFCeService::class);
        $url = 'https://nfe.portal-novo.gov.br/consulta?p='.self::KEY.'|2|1';

        try {
            $service->consultarPorQRCode($url);
            $this->fail('O host desconhecido devia ser rejeitado.');
        } catch (\InvalidArgumentException) {
            Log::shouldHaveReceived('warning')->with('Portal SEFAZ rejeitado', ['host' => 'nfe.portal-novo.gov.br', 'uf' => 'SP'])->once();
        }

        config(['nfe.portais_compartilhados' => ['portal-novo.gov.br']]);
        Http::fake(['*' => Http::response(
            file_get_contents(base_path('tests/fixtures/nfce_portal_direto.html')), 200
        )]);

        $this->assertCount(1, $service->consultarPorQRCode($url)['dados']['itens']);
    }

    // ─── documentação da API ─────────────────────────────────────────────────

    public function test_api_docs_are_forbidden_outside_local(): void
    {
        $this->assertFalse(app()->environment('local', 'staging'));

        $this->get('/docs/api')->assertForbidden();
        $this->get('/docs/api.json')->assertForbidden();
        $this->actingAs(User::factory()->pro()->create())->get('/docs/api')->assertForbidden();
    }
}
