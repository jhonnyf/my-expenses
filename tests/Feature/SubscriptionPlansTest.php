<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionPlansTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_requires_authentication(): void
    {
        $this->get(route('subscription.upgrade'))->assertRedirect(route('login.index'));
    }

    public function test_free_user_sees_every_configured_feature_and_the_disabled_cta(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('subscription.upgrade'))->assertOk();

        foreach (config('plans.features') as $feature) {
            $response->assertSee($feature['label']);
        }
        $response->assertSee('Desbloqueie o plano Pro')->assertSee('Assinar Pro (em breve)')->assertSee('Não incluído no plano Grátis');
    }

    public function test_pro_user_sees_the_pro_state_instead_of_the_sales_pitch(): void
    {
        $this->actingAs(User::factory()->pro()->create())->get(route('subscription.upgrade'))
            ->assertOk()
            ->assertSee('Você já é Pro')
            ->assertDontSee('Assinar Pro (em breve)');
    }

    public function test_reports_email_and_schedule_are_listed_as_pro(): void
    {
        $this->actingAs(User::factory()->create())->get(route('subscription.upgrade'))
            ->assertSee('Envio de relatórios por e-mail e agendamento recorrente');
    }

    public function test_paywall_names_the_blocked_feature_and_highlights_its_row(): void
    {
        $free = User::factory()->create();

        $this->actingAs($free)->get('/reports/csv')->assertRedirect(route('subscription.upgrade'));

        $this->actingAs($free)->get(route('subscription.upgrade'))->assertOk()
            ->assertSee('exportação de relatórios em CSV', false)
            ->assertSee('aria-current="true"', false);
    }

    public function test_each_pro_route_group_reports_its_own_feature(): void
    {
        $free = User::factory()->create();

        $this->actingAs($free)->getJson('/prices')->assertStatus(402)->assertJsonPath('feature', 'price_comparison');
        $this->actingAs($free)->getJson('/recurring-purchases')->assertStatus(402)->assertJsonPath('feature', 'recurring_purchases');
        $this->actingAs($free)->postJson('/reports/email')->assertStatus(402)->assertJsonPath('feature', 'report_email');
        $this->actingAs($free)->postJson('/categories/suggest-keywords')->assertStatus(402)->assertJsonPath('feature', 'ai_suggestions');
    }

    public function test_every_route_feature_key_exists_in_the_plans_config(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'pro:')) {
                    $this->assertArrayHasKey(substr($middleware, 4), config('plans.features'), $route->uri());
                }
            }
        }
    }
}
