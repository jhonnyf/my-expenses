<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Notifications\FavoriteProductPriceDropped;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/notifications')->assertStatus(401);
    }

    public function test_index_returns_unread_count_and_notifications(): void
    {
        $user = User::factory()->create();
        $user->notify(new FavoriteProductPriceDropped('ARROZ BRANCO 5KG', 18.00, 'MERCADO X', 'Curitiba', 'PR'));

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/notifications');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('data.unread_count'));
    }

    public function test_mark_as_read_marks_notification(): void
    {
        $user = User::factory()->create();
        $user->notify(new FavoriteProductPriceDropped('ARROZ BRANCO 5KG', 18.00, 'MERCADO X', 'Curitiba', 'PR'));
        $notificationId = $user->notifications()->first()->id;

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/notifications/{$notificationId}/read")
            ->assertStatus(200);

        $this->assertNotNull($user->notifications()->first()->read_at);
    }

    public function test_index_includes_kind_message_and_keeps_legacy_fields(): void
    {
        $user = User::factory()->create();
        $user->notify(new FavoriteProductPriceDropped('ARROZ', 18.0, 'MERCADO X', 'Curitiba', 'PR'));

        $item = $this->actingAs($user, 'sanctum')->getJson('/api/v1/notifications')->json('data.notifications.0');

        $this->assertSame('price_drop', $item['kind']);
        $this->assertSame('ARROZ caiu para R$ 18,00 em MERCADO X (Curitiba/PR).', $item['message']);
        $this->assertSame(FavoriteProductPriceDropped::class, $item['type']);
        $this->assertSame('ARROZ', $item['data']['product_name']);
    }

    public function test_unread_count_and_read_all(): void
    {
        $user = User::factory()->create();
        $user->notify(new FavoriteProductPriceDropped('ARROZ', 18.0, 'X', 'Curitiba', 'PR'));

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.unread_count', 1);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/notifications/read-all')->assertOk();
        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_read_all_requires_authentication(): void
    {
        $this->postJson('/api/v1/notifications/read-all')->assertStatus(401);
    }
}
