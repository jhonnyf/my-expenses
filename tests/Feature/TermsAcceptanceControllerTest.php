<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TermsAcceptanceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_redirects_unauthenticated_user(): void
    {
        $this->get('/aceitar-termos')->assertRedirect(route('login.index'));
    }

    public function test_form_shows_page_when_pending(): void
    {
        $user = User::factory()->withPendingTermsAcceptance()->create();

        $this->actingAs($user)->get('/aceitar-termos')->assertStatus(200)->assertSee('Termos');
    }

    public function test_form_redirects_to_dashboard_when_already_accepted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/aceitar-termos')->assertRedirect(route('dashboard.index'));
    }

    public function test_store_records_acceptance_and_redirects_to_dashboard(): void
    {
        $user = User::factory()->withPendingTermsAcceptance()->create();

        $this->actingAs($user)
            ->post('/aceitar-termos')
            ->assertRedirect(route('dashboard.index'));

        $user->refresh();
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertSame(config('legal.current_terms_version'), $user->terms_version);
    }

    public function test_dashboard_is_accessible_after_accepting(): void
    {
        $user = User::factory()->withPendingTermsAcceptance()->create();

        $this->actingAs($user)->post('/aceitar-termos');

        $this->actingAs($user)->get('/dashboard')->assertStatus(200);
    }
}
