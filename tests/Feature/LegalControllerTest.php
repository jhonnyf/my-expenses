<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_terms_page_returns_200_and_contains_key_clause(): void
    {
        $this->get('/termos-de-uso')
            ->assertStatus(200)
            ->assertSee('Termos de Uso')
            ->assertSee('produto, preço pago, emitente');
    }

    public function test_privacy_page_returns_200_and_contains_key_clause(): void
    {
        $this->get('/politica-de-privacidade')
            ->assertStatus(200)
            ->assertSee('Política de Privacidade')
            ->assertSee('LGPD');
    }

    public function test_privacy_page_discloses_dpo_third_parties_and_cookies(): void
    {
        $this->get('/politica-de-privacidade')
            ->assertStatus(200)
            ->assertSee('Encarregado de Dados')
            ->assertSee('Nominatim')
            ->assertSee('Google Gemini')
            ->assertSee('Cookies')
            ->assertSee('Excluir minha conta')
            ->assertSee('Exportar meus dados');
    }

    public function test_terms_page_accessible_to_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/termos-de-uso')
            ->assertStatus(200);
    }

    public function test_privacy_page_accessible_to_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/politica-de-privacidade')
            ->assertStatus(200);
    }

    public function test_footer_has_links_to_legal_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertStatus(200)
            ->assertSee(route('legal.terms'))
            ->assertSee(route('legal.privacy'));
    }
}
