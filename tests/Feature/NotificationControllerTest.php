<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\FavoriteProductPriceDropped;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
