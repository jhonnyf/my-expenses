<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\BudgetThresholdReached;
use App\Notifications\FavoriteProductPriceDropped;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_redirects_unauthenticated_user(): void
    {
        $this->get('/notifications')->assertRedirect('/login');
    }

    public function test_index_returns_unread_count_and_notifications(): void
    {
        $user = User::factory()->create();
        $user->notify(new FavoriteProductPriceDropped('ARROZ BRANCO 5KG', 18.00, 'MERCADO X', 'Curitiba', 'PR'));

        $response = $this->actingAs($user)->getJson('/notifications');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('unread_count'));
        $this->assertCount(1, $response->json('notifications'));
    }

    public function test_mark_as_read_marks_notification(): void
    {
        $user = User::factory()->create();
        $user->notify(new FavoriteProductPriceDropped('ARROZ BRANCO 5KG', 18.00, 'MERCADO X', 'Curitiba', 'PR'));
        $notificationId = $user->notifications()->first()->id;

        $this->actingAs($user)
            ->post("/notifications/{$notificationId}/read")
            ->assertStatus(200);

        $this->assertNotNull($user->notifications()->first()->read_at);
    }

    public function test_mark_as_read_returns_404_for_other_users_notification(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $other->notify(new FavoriteProductPriceDropped('ARROZ BRANCO 5KG', 18.00, 'MERCADO X', 'Curitiba', 'PR'));
        $notificationId = $other->notifications()->first()->id;

        $this->actingAs($user)
            ->post("/notifications/{$notificationId}/read")
            ->assertStatus(404);
    }

    public function test_index_presents_kind_message_level_and_url(): void
    {
        $user = User::factory()->create();
        $user->notify(new FavoriteProductPriceDropped('ARROZ BRANCO 5KG', 18.5, 'MERCADO X', 'Curitiba', 'PR'));
        $user->notify(new BudgetThresholdReached('Mercado', 100, 550.0, 500.0, '2026-09'));
        $user->notify(new BudgetThresholdReached(null, 80, 400.0, 500.0, '2026-09'));

        $items = collect($this->actingAs($user)->getJson('/notifications')->json('notifications'))->keyBy('kind');

        $this->assertSame('info', $items['price_drop']['level']);
        $this->assertSame('ARROZ BRANCO 5KG caiu para R$ 18,50 em MERCADO X (Curitiba/PR).', $items['price_drop']['message']);
        $this->assertSame(route('prices.index', ['product' => 'ARROZ BRANCO 5KG']), $items['price_drop']['url']);

        $this->assertSame('danger', $items['budget_exceeded']['level']);
        $this->assertStringContainsString('orçamento Mercado foi excedido em setembro/2026 (R$ 550,00 de R$ 500,00)', $items['budget_exceeded']['message']);
        $this->assertSame(route('budgets.index', ['month' => '2026-09']), $items['budget_exceeded']['url']);

        $this->assertSame('warning', $items['budget_warning']['level']);
        $this->assertStringContainsString('orçamento Geral chegou a 80%', $items['budget_warning']['message']);
    }

    public function test_index_does_not_expose_notifiable_internals_but_keeps_type_and_data(): void
    {
        $user = User::factory()->create();
        $user->notify(new FavoriteProductPriceDropped('ARROZ', 18.0, 'X', 'Curitiba', 'PR'));

        $item = $this->actingAs($user)->getJson('/notifications')->json('notifications.0');

        $this->assertArrayNotHasKey('notifiable_id', $item);
        $this->assertSame(FavoriteProductPriceDropped::class, $item['type']);
        $this->assertSame('ARROZ', $item['data']['product_name']);
    }

    public function test_unknown_notification_type_falls_back_to_generic_message(): void
    {
        $user = User::factory()->create();
        $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'App\\Notifications\\Removida', 'data' => []]);

        $item = $this->actingAs($user)->getJson('/notifications')->json('notifications.0');

        $this->assertSame('generic', $item['kind']);
        $this->assertNull($item['url']);
    }

    public function test_unread_count_endpoint(): void
    {
        $user = User::factory()->create();
        $user->notify(new FavoriteProductPriceDropped('ARROZ', 18.0, 'X', 'Curitiba', 'PR'));

        $this->actingAs($user)->getJson('/notifications/unread-count')->assertOk()->assertExactJson(['unread_count' => 1]);
    }

    public function test_mark_all_as_read_only_affects_the_current_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $user->notify(new FavoriteProductPriceDropped('ARROZ', 18.0, 'X', 'Curitiba', 'PR'));
        $user->notify(new FavoriteProductPriceDropped('FEIJAO', 8.0, 'X', 'Curitiba', 'PR'));
        $other->notify(new FavoriteProductPriceDropped('ARROZ', 18.0, 'X', 'Curitiba', 'PR'));

        $this->actingAs($user)->postJson('/notifications/read-all')->assertOk();

        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertSame(1, $other->unreadNotifications()->count());
    }

    public function test_prune_command_removes_only_old_notifications(): void
    {
        $user = User::factory()->create();
        $user->notify(new FavoriteProductPriceDropped('VELHA', 18.0, 'X', 'Curitiba', 'PR'));
        $user->notify(new FavoriteProductPriceDropped('NOVA', 18.0, 'X', 'Curitiba', 'PR'));
        DatabaseNotification::where('data->product_name', 'VELHA')->update(['created_at' => now()->subDays(91)]);

        $this->artisan('notifications:prune')->assertSuccessful();

        $this->assertSame(['NOVA'], $user->notifications()->get()->pluck('data.product_name')->all());
    }
}
