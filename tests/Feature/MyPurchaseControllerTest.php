<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Issuer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MyPurchaseControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── index ───────────────────────────────────────────────────────────────

    public function test_index_redirects_unauthenticated_user(): void
    {
        $this->get('/my-purchases')->assertRedirect('/login');
    }

    public function test_index_returns_200_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/my-purchases')
            ->assertStatus(200);
    }

    public function test_index_lists_only_current_user_invoices(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $issuer = Issuer::factory()->create();

        Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id]);
        Invoice::factory()->create(['user_id' => $other->id, 'issuer_id' => $issuer->id]);

        $this->actingAs($user)
            ->get('/my-purchases')
            ->assertStatus(200)
            ->assertViewHas('records', fn ($records) => $records->total() === 1);
    }

    // ─── uploadForm ──────────────────────────────────────────────────────────

    public function test_upload_form_redirects_unauthenticated_user(): void
    {
        $this->get('/my-purchases/upload')->assertRedirect('/login');
    }

    public function test_upload_form_returns_200(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/my-purchases/upload')
            ->assertStatus(200);
    }

    // ─── detail ──────────────────────────────────────────────────────────────

    public function test_detail_redirects_unauthenticated_user(): void
    {
        $issuer  = Issuer::factory()->create();
        $invoice = Invoice::factory()->create(['issuer_id' => $issuer->id]);

        $this->get("/my-purchases/detail/{$invoice->id}")->assertRedirect('/login');
    }

    public function test_detail_returns_403_for_invoice_belonging_to_another_user(): void
    {
        $user    = User::factory()->create();
        $other   = User::factory()->create();
        $issuer  = Issuer::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $other->id, 'issuer_id' => $issuer->id]);

        $this->actingAs($user)
            ->get("/my-purchases/detail/{$invoice->id}")
            ->assertStatus(403);
    }

    public function test_detail_returns_200_for_own_invoice(): void
    {
        $user    = User::factory()->create();
        $issuer  = Issuer::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $user->id, 'issuer_id' => $issuer->id]);

        $this->actingAs($user)
            ->get("/my-purchases/detail/{$invoice->id}")
            ->assertStatus(200)
            ->assertViewHas('invoice');
    }

    // ─── upload (XML) ────────────────────────────────────────────────────────

    public function test_upload_redirects_unauthenticated_user(): void
    {
        $file = new UploadedFile(
            base_path('tests/fixtures/nfce.xml'),
            'nfce.xml',
            'text/xml',
            null,
            true
        );

        $this->post('/my-purchases/upload', ['xml' => $file])->assertRedirect('/login');
    }

    public function test_upload_xml_creates_invoice_and_redirects_to_detail(): void
    {
        $user = User::factory()->create();
        $file = new UploadedFile(
            base_path('tests/fixtures/nfce.xml'),
            'nfce.xml',
            'text/xml',
            null,
            true
        );

        $response = $this->actingAs($user)
            ->post('/my-purchases/upload', ['xml' => $file]);

        $invoice = Invoice::where('user_id', $user->id)->first();
        $this->assertNotNull($invoice);
        $response->assertRedirect(route('my-purchases.detail', $invoice->id));
    }

    public function test_upload_xml_rejects_duplicate_invoice(): void
    {
        $user   = User::factory()->create();
        $issuer = Issuer::factory()->create();

        Invoice::factory()->create([
            'user_id'    => $user->id,
            'issuer_id'  => $issuer->id,
            'access_key' => '35260600000000000191650010000012341234567890',
        ]);

        $file = new UploadedFile(
            base_path('tests/fixtures/nfce.xml'),
            'nfce.xml',
            'text/xml',
            null,
            true
        );

        $this->actingAs($user)
            ->post('/my-purchases/upload', ['xml' => $file])
            ->assertSessionHasErrors(['xml']);

        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_upload_validates_file_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/my-purchases/upload', [])
            ->assertSessionHasErrors(['xml']);
    }
}
