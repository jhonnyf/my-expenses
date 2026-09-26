<?php

namespace Tests\Feature\Security;

use App\Actions\DeleteUserAccountAction;
use App\Actions\ImportInvoiceAction;
use App\Enums\InvoiceStatus;
use App\Enums\ReportFrequency;
use App\Jobs\ExportPersonalDataJob;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ReportSchedule;
use App\Models\ShoppingList;
use App\Models\User;
use App\Notifications\PersonalDataExportReady;
use App\Notifications\ReportByEmail;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Relatórios, importação da mesma nota por duas contas e ciclo de vida da conta (senha, tokens,
 * exportação, exclusão): o que A faz nunca altera nem lê o que é de B.
 */
class AccountReportsIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const SHARED_KEY = '35260912345678000190650010000001239123456783';

    private User $attacker;

    private User $victim;

    protected function setUp(): void
    {
        parent::setUp();

        $this->attacker = User::factory()->pro()->create();
        $this->victim = User::factory()->pro()->create();
    }

    private function itemOf(User $user, string $description, array $invoice = []): InvoiceItem
    {
        return InvoiceItem::factory()
            ->for(Invoice::factory()->for($user)->create(['issued_at' => now()] + $invoice))
            ->create(['description' => $description]);
    }

    private function parsedNote(float $total, string $description = 'ARROZ'): array
    {
        return [
            'chave' => self::SHARED_KEY,
            'numero' => '123',
            'serie' => '1',
            'emitido_em' => now()->toIso8601String(),
            'emitente' => ['cnpj' => '12345678000190', 'nome' => 'LOJA EXEMPLO'],
            'itens' => [[
                'numero_item' => 1, 'descricao' => $description, 'unidade' => 'UN',
                'quantidade' => 1, 'valor_unitario' => $total, 'valor_total' => $total,
            ]],
            'pagamento' => [],
            'total' => ['valor_nota' => $total, 'valor_produtos' => $total],
        ];
    }

    // ─── relatórios ──────────────────────────────────────────────────────────

    public function test_report_page_and_filters_never_show_another_users_items(): void
    {
        $victimItem = $this->itemOf($this->victim, 'ITEM RELATORIO SECRETO');
        $this->itemOf($this->attacker, 'ITEM RELATORIO PROPRIO');
        $victimIssuerId = $victimItem->invoice->issuer_id;

        $this->actingAs($this->attacker);

        $this->get(route('reports.index', ['start_date' => '2000-01-01']))
            ->assertOk()->assertSee('ITEM RELATORIO PROPRIO')->assertDontSee('ITEM RELATORIO SECRETO');
        // Filtrar pelo emissor da vítima não abre nada dela.
        $this->get(route('reports.index', ['start_date' => '2000-01-01', 'issuer_id' => $victimIssuerId]))
            ->assertOk()->assertDontSee('ITEM RELATORIO SECRETO')->assertDontSee($victimItem->invoice->issuer->name);
        $this->get(route('reports.index', ['start_date' => '2000-01-01', 'q' => 'SECRETO']))
            ->assertOk()->assertDontSee('ITEM RELATORIO SECRETO');
    }

    public function test_report_exports_only_contain_own_data(): void
    {
        $this->itemOf($this->victim, 'ITEM RELATORIO SECRETO');
        $this->itemOf($this->attacker, 'ITEM RELATORIO PROPRIO');
        $filters = ['start_date' => '2000-01-01', 'end_date' => now()->toDateString()];

        $csv = $this->actingAs($this->attacker)->get(route('reports.csv', $filters))->streamedContent();
        $this->assertStringContainsString('ITEM RELATORIO PROPRIO', $csv);
        $this->assertStringNotContainsString('ITEM RELATORIO SECRETO', $csv);

        $apiCsv = $this->actingAs($this->attacker, 'sanctum')->get('/api/v1/reports/csv?'.http_build_query($filters))->streamedContent();
        $this->assertStringNotContainsString('ITEM RELATORIO SECRETO', $apiCsv);

        $data = app(ReportService::class)->buildReportData($this->attacker->id, $filters + ['q' => 'RELATORIO']);
        $this->assertStringNotContainsString('SECRETO', json_encode($data));

        $this->actingAs($this->attacker, 'sanctum')->getJson('/api/v1/reports?'.http_build_query($filters))
            ->assertOk()->assertDontSee('ITEM RELATORIO SECRETO');
    }

    public function test_emailed_report_goes_only_to_the_requester(): void
    {
        Notification::fake();
        $this->itemOf($this->victim, 'ITEM RELATORIO SECRETO');

        $this->actingAs($this->attacker)->postJson(route('reports.email'), ['format' => 'csv', 'start_date' => '2000-01-01'])->assertOk();

        Notification::assertSentTo($this->attacker, ReportByEmail::class);
        Notification::assertNotSentTo($this->victim, ReportByEmail::class);
    }

    public function test_report_schedule_is_per_user(): void
    {
        $victimSchedule = ReportSchedule::create(['user_id' => $this->victim->id, 'frequency' => ReportFrequency::Weekly, 'format' => 'pdf']);

        $this->actingAs($this->attacker)
            ->putJson(route('reports.schedule.save'), ['frequency' => ReportFrequency::Monthly->value, 'format' => 'csv'])
            ->assertOk();
        $this->actingAs($this->attacker, 'sanctum')->getJson('/api/v1/reports/schedule')->assertOk()->assertJsonMissing(['format' => 'pdf']);

        $this->assertSame(ReportFrequency::Weekly, $victimSchedule->fresh()->frequency);
        $this->assertSame('pdf', $victimSchedule->fresh()->format);

        $this->actingAs($this->attacker)->deleteJson(route('reports.schedule.delete'))->assertOk();

        $this->assertModelExists($victimSchedule);
        $this->assertSame(0, ReportSchedule::where('user_id', $this->attacker->id)->count());
    }

    // ─── mesma nota em duas contas ───────────────────────────────────────────

    public function test_same_access_key_in_two_accounts_never_touches_the_other_invoice(): void
    {
        $import = app(ImportInvoiceAction::class);

        $victimInvoice = $import->execute($this->parsedNote(10.00, 'ARROZ DA VITIMA'), '', $this->victim->id);
        $attackerInvoice = $import->execute($this->parsedNote(9999.00, 'ARROZ FORJADO'), '', $this->attacker->id);

        $this->assertNotSame($victimInvoice->id, $attackerInvoice->id);
        $this->assertSame(2, Invoice::where('access_key', self::SHARED_KEY)->count());

        $victimInvoice = $victimInvoice->fresh();
        $this->assertSame($this->victim->id, $victimInvoice->user_id);
        $this->assertEquals(10.00, $victimInvoice->total_amount);
        $this->assertSame(['ARROZ DA VITIMA'], $victimInvoice->items()->pluck('description')->all());
        $this->assertSame(['ARROZ FORJADO'], $attackerInvoice->items()->pluck('description')->all());
    }

    public function test_reconcile_of_one_users_pending_note_does_not_touch_the_other_users_copy(): void
    {
        $authorized = Invoice::factory()->for($this->victim)->create(['access_key' => self::SHARED_KEY, 'total_amount' => 10.00]);
        $pending = Invoice::factory()->pending()->for($this->attacker)->create([
            'access_key' => self::SHARED_KEY,
            'qrcode_url' => 'https://nfce.fazenda.sp.gov.br/consulta?p='.self::SHARED_KEY.'|2|1|15|45.90|abcdef|000001|HASH',
        ]);
        $html = preg_replace(
            '/(class="chave">)[^<]*</',
            '${1}'.implode(' ', str_split(self::SHARED_KEY, 4)).'<',
            file_get_contents(base_path('tests/fixtures/nfce_portal_direto.html'))
        );
        Http::fake(['nfce.fazenda.sp.gov.br/*' => Http::response($html, 200)]);

        $this->artisan('invoices:reconcile-pending')->assertSuccessful();

        $this->assertSame(InvoiceStatus::Authorized, Invoice::includingUnauthorized()->findOrFail($pending->id)->status);
        $this->assertSame($this->attacker->id, Invoice::includingUnauthorized()->findOrFail($pending->id)->user_id);
        $this->assertSame($this->victim->id, $authorized->fresh()->user_id);
        $this->assertEquals(10.00, $authorized->fresh()->total_amount);
    }

    // ─── conta: dados, senha, tokens ─────────────────────────────────────────

    public function test_account_update_ignores_identity_fields_and_rejects_taken_email(): void
    {
        $verifiedAt = $this->victim->email_verified_at;

        $this->actingAs($this->attacker)->patch(route('account.update'), [
            'name' => 'Novo Nome', 'email' => $this->victim->email,
        ])->assertSessionHasErrors('email');
        $this->assertSame($this->attacker->name, $this->attacker->fresh()->name);

        $this->actingAs($this->attacker)->patch(route('account.update'), [
            'name' => 'Novo Nome', 'email' => $this->attacker->email,
            'id' => $this->victim->id, 'user_id' => $this->victim->id, 'email_verified_at' => null, 'provider_id' => 'x',
        ]);

        $this->assertSame('Novo Nome', $this->attacker->fresh()->name);
        $this->assertNotNull($this->attacker->fresh()->email_verified_at);
        $this->assertNull($this->attacker->fresh()->provider_id);
        $this->assertSame($this->victim->name, $this->victim->fresh()->name);
        $this->assertEquals($verifiedAt, $this->victim->fresh()->email_verified_at);
    }

    public function test_registration_ignores_privileged_fields(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Novo Usuario', 'email' => 'novo@example.com',
            'password' => 'senha-forte-123', 'password_confirmation' => 'senha-forte-123',
            'accept_terms' => true, 'email_verified_at' => now()->toDateTimeString(), 'provider' => 'google', 'provider_id' => '1',
        ]);

        $user = User::where('email', 'novo@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->provider);
        $this->assertFalse($user->isPro());
    }

    public function test_password_change_revokes_only_own_other_tokens(): void
    {
        $current = $this->attacker->createToken('atual')->plainTextToken;
        $this->attacker->createToken('outro-aparelho');
        $victimToken = $this->victim->createToken('vitima');

        $this->withToken($current)->patchJson('/api/v1/account/password', [
            'current_password' => 'password', 'password' => 'nova-senha-123', 'password_confirmation' => 'nova-senha-123',
        ])->assertOk();

        $this->assertSame(1, $this->attacker->tokens()->count());
        $this->assertSame('atual', $this->attacker->tokens()->first()->name);
        $this->assertModelExists($victimToken->accessToken);
    }

    public function test_revoke_other_sessions_keeps_current_token_and_victims_tokens(): void
    {
        $current = $this->attacker->createToken('atual')->plainTextToken;
        $this->attacker->createToken('outro');
        $victimToken = $this->victim->createToken('vitima');

        $this->withToken($current)->postJson('/api/v1/account/sessions/revoke-others')->assertOk();

        $this->assertSame(['atual'], $this->attacker->tokens()->pluck('name')->all());
        $this->assertModelExists($victimToken->accessToken);
    }

    public function test_logout_only_revokes_the_calling_token(): void
    {
        $current = $this->attacker->createToken('atual')->plainTextToken;
        $victimToken = $this->victim->createToken('vitima');

        $this->withToken($current)->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertSame(0, $this->attacker->tokens()->count());
        $this->assertModelExists($victimToken->accessToken);
    }

    public function test_password_reset_revokes_every_token_of_that_user_only(): void
    {
        $this->attacker->createToken('roubado');
        $victimToken = $this->victim->createToken('vitima');
        $token = Password::broker()->createToken($this->attacker);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token, 'email' => $this->attacker->email,
            'password' => 'nova-senha-123', 'password_confirmation' => 'nova-senha-123',
        ])->assertOk();

        $this->assertSame(0, $this->attacker->tokens()->count());
        $this->assertModelExists($victimToken->accessToken);
    }

    public function test_web_password_reset_revokes_tokens_and_remember_token(): void
    {
        $this->attacker->createToken('roubado');
        $this->attacker->forceFill(['remember_token' => 'antigo'])->save();
        $victimToken = $this->victim->createToken('vitima');
        $token = Password::broker()->createToken($this->attacker);

        $this->post(route('password.update'), [
            'token' => $token, 'email' => $this->attacker->email,
            'password' => 'nova-senha-123', 'password_confirmation' => 'nova-senha-123',
        ])->assertRedirect(route('dashboard.index'));

        $this->assertSame(0, $this->attacker->tokens()->count());
        $this->assertNotSame('antigo', $this->attacker->fresh()->remember_token);
        $this->assertModelExists($victimToken->accessToken);
    }

    public function test_avatar_upload_never_replaces_another_users_avatar(): void
    {
        Storage::fake('public');
        $this->actingAs($this->victim, 'sanctum')->postJson('/api/v1/account/avatar', ['avatar' => UploadedFile::fake()->image('v.png', 100, 100)])->assertOk();
        $victimAvatarId = $this->victim->fresh()->avatar->id;

        $this->actingAs($this->attacker, 'sanctum')->postJson('/api/v1/account/avatar', ['avatar' => UploadedFile::fake()->image('a.png', 100, 100)])->assertOk();

        $this->assertSame($victimAvatarId, $this->victim->fresh()->avatar->id);
        $this->assertNotSame($victimAvatarId, $this->attacker->fresh()->avatar->id);
        Storage::disk('public')->assertExists($this->victim->fresh()->avatar->path);
    }

    // ─── exportação e exclusão ───────────────────────────────────────────────

    public function test_data_export_only_contains_own_data(): void
    {
        Storage::fake('local');
        Notification::fake();
        $this->itemOf($this->victim, 'ITEM SECRETO DA VITIMA');
        $this->itemOf($this->attacker, 'ITEM DO ATACANTE');
        Category::factory()->create(['user_id' => $this->victim->id, 'name' => 'CATEGORIA SECRETA']);
        Budget::factory()->create(['user_id' => $this->victim->id, 'category_id' => null]);
        ShoppingList::factory()->create(['user_id' => $this->victim->id, 'name' => 'LISTA SECRETA']);

        (new ExportPersonalDataJob($this->attacker->id))->handle();

        $file = $this->attacker->files()->where('collection', 'personal-data-export')->firstOrFail();
        $json = Storage::disk('local')->get($file->path);

        $this->assertStringContainsString('ITEM DO ATACANTE', $json);
        foreach (['ITEM SECRETO DA VITIMA', 'CATEGORIA SECRETA', 'LISTA SECRETA', $this->victim->email, $this->victim->name] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
        Notification::assertSentTo($this->attacker, PersonalDataExportReady::class);
        Notification::assertNotSentTo($this->victim, PersonalDataExportReady::class);
        $this->assertSame(0, $this->victim->files()->count());
    }

    public function test_deleting_an_account_leaves_every_other_users_data_intact(): void
    {
        $victimItem = $this->itemOf($this->victim, 'ITEM DA VITIMA');
        $victimCategory = Category::factory()->create(['user_id' => $this->victim->id]);
        $victimBudget = Budget::factory()->create(['user_id' => $this->victim->id]);
        $victimList = ShoppingList::factory()->create(['user_id' => $this->victim->id]);
        $victimToken = $this->victim->createToken('vitima');
        $attackerInvoice = $this->itemOf($this->attacker, 'ITEM DO ATACANTE')->invoice;
        $attackerCategory = Category::factory()->create(['user_id' => $this->attacker->id]);
        $attackerList = ShoppingList::factory()->create(['user_id' => $this->attacker->id]);
        $this->attacker->createToken('atacante');

        app(DeleteUserAccountAction::class)->execute($this->attacker);

        $this->assertModelMissing($this->attacker);
        $this->assertModelMissing($attackerCategory);
        $this->assertModelMissing($attackerList);
        $this->assertNull($attackerInvoice->fresh()->user_id, 'a nota é anonimizada, não fica com o dono');

        foreach ([$this->victim, $victimItem, $victimItem->invoice, $victimCategory, $victimBudget, $victimList, $victimToken->accessToken] as $model) {
            $this->assertModelExists($model);
        }
        $this->assertSame($this->victim->id, $victimItem->invoice->fresh()->user_id);
    }
}
