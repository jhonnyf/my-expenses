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
}
