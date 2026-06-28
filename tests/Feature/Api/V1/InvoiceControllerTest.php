<?php

namespace Tests\Feature\Api\V1;

use App\Models\Invoice;
use App\Models\User;
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
}
