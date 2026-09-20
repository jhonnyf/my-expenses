<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ExportPersonalDataJob;
use App\Models\Invoice;
use App\Models\Issuer;
use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\PersonalDataExportReady;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExportPersonalDataJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_generates_export_file_and_notifies_user(): void
    {
        Storage::fake('local');
        Notification::fake();

        $user = User::factory()->create(['name' => 'Maria Teste']);
        UserProfile::factory()->for($user)->create(['cpf' => '11122233344']);
        Invoice::factory()->for($user)->for(Issuer::factory()->create(['name' => 'Mercado Fixture']))->create(['total_amount' => 42.5]);

        (new ExportPersonalDataJob($user->id))->handle();

        $user->refresh();
        $this->assertNotNull($user->files()->where('collection', 'personal-data-export')->first());

        $file = $user->files()->where('collection', 'personal-data-export')->first();
        Storage::disk('local')->assertExists($file->path);

        $content = json_decode(Storage::disk('local')->get($file->path), true);
        $this->assertSame('Maria Teste', $content['cadastro']['nome']);
        $this->assertSame('11122233344', $content['cadastro']['cpf']);
        $this->assertSame('42.50', $content['notas_fiscais'][0]['total']);

        Notification::assertSentTo($user, PersonalDataExportReady::class);
    }

    public function test_handle_includes_pending_invoices_in_export(): void
    {
        Storage::fake('local');
        Notification::fake();

        $user = User::factory()->create();
        Invoice::factory()->for($user)->pending()->create(['total_amount' => 45.9]);

        (new ExportPersonalDataJob($user->id))->handle();

        $file = $user->files()->where('collection', 'personal-data-export')->first();
        $content = json_decode(Storage::disk('local')->get($file->path), true);

        $this->assertCount(1, $content['notas_fiscais']);
        $this->assertSame('pending', $content['notas_fiscais'][0]['status']);
    }

    public function test_handle_does_nothing_when_user_no_longer_exists(): void
    {
        Storage::fake('local');
        Notification::fake();

        (new ExportPersonalDataJob(999999))->handle();

        Notification::assertNothingSent();
    }
}
