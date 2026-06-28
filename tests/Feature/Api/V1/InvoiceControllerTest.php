<?php

namespace Tests\Feature\Api\V1;

use App\Models\Invoice;
use App\Models\Issuer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/invoices')->assertStatus(401);
    }

    public function test_index_returns_paginated_invoices_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        Invoice::factory()->count(3)->for($user)->create();
        Invoice::factory()->count(2)->create(); // de outro usuário

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/invoices')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonCount(3, 'data');
    }

    public function test_show_returns_401_when_unauthenticated(): void
    {
        $invoice = Invoice::factory()->create();

        $this->getJson("/api/v1/invoices/{$invoice->id}")->assertStatus(401);
    }

    public function test_show_returns_403_when_invoice_belongs_to_another_user(): void
    {
        $invoice = Invoice::factory()->create();
        $other   = User::factory()->create();

        $this->actingAs($other, 'sanctum')
            ->getJson("/api/v1/invoices/{$invoice->id}")
            ->assertStatus(403);
    }

    public function test_show_returns_invoice_for_owner(): void
    {
        $user    = User::factory()->create();
        $invoice = Invoice::factory()->for($user)->create();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/invoices/{$invoice->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $invoice->id)
            ->assertJsonMissingPath('data.raw_xml');
    }

    public function test_import_xml_returns_401_when_unauthenticated(): void
    {
        $file = UploadedFile::fake()->createWithContent('nfce.xml', file_get_contents(base_path('tests/fixtures/nfce.xml')));

        $this->postJson('/api/v1/invoices/import/xml', ['xml' => $file])->assertStatus(401);
    }

    public function test_import_xml_creates_invoice(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent('nfce.xml', file_get_contents(base_path('tests/fixtures/nfce.xml')));

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/invoices/import/xml', ['xml' => $file])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'access_key', 'total_amount']]);

        $this->assertDatabaseHas('invoices', ['user_id' => $user->id]);
        $this->assertDatabaseHas('issuers', ['cnpj' => '00000000000191']);
    }

    public function test_import_xml_returns_409_for_duplicate_invoice(): void
    {
        $user    = User::factory()->create();
        $issuer  = Issuer::factory()->create(['cnpj' => '00000000000191']);
        Invoice::factory()->for($user)->for($issuer)->create([
            'access_key' => '35260600000000000191650010000012341234567890',
        ]);

        $file = UploadedFile::fake()->createWithContent('nfce.xml', file_get_contents(base_path('tests/fixtures/nfce.xml')));

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/invoices/import/xml', ['xml' => $file])
            ->assertStatus(409);
    }
}
