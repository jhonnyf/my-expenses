<?php

namespace Tests\Feature\Console;

use App\Models\FavoriteProduct;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use App\Models\User;
use App\Notifications\FavoriteProductPriceDropped;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CheckFavoriteProductPriceDropsTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifies_when_price_dropped_enough(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $issuer = Issuer::factory()->create(['city' => 'Curitiba', 'state' => 'PR']);
        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($issuer)->create())
            ->create(['description' => 'ARROZ BRANCO 5KG', 'unit_price' => 18.00]);

        FavoriteProduct::factory()->for($user)->create([
            'canonical_name' => 'ARROZ BRANCO 5KG',
            'last_notified_price' => 20.00,
        ]);

        $this->artisan('prices:check-favorite-drops')->assertExitCode(0);

        Notification::assertSentTo($user, FavoriteProductPriceDropped::class);
        $this->assertDatabaseHas('favorite_products', [
            'canonical_name' => 'ARROZ BRANCO 5KG',
            'last_notified_price' => 18.00,
        ]);
    }

    public function test_does_not_notify_when_price_drop_is_below_threshold(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $issuer = Issuer::factory()->create(['city' => 'Curitiba', 'state' => 'PR']);
        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($issuer)->create())
            ->create(['description' => 'ARROZ BRANCO 5KG', 'unit_price' => 19.50]);

        FavoriteProduct::factory()->for($user)->create([
            'canonical_name' => 'ARROZ BRANCO 5KG',
            'last_notified_price' => 20.00,
        ]);

        $this->artisan('prices:check-favorite-drops');

        Notification::assertNothingSent();
    }

    public function test_does_not_notify_on_first_check_without_baseline_price(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $issuer = Issuer::factory()->create(['city' => 'Curitiba', 'state' => 'PR']);
        InvoiceItem::factory()->for(Invoice::factory()->for($user)->for($issuer)->create())
            ->create(['description' => 'ARROZ BRANCO 5KG', 'unit_price' => 18.00]);

        FavoriteProduct::factory()->for($user)->create([
            'canonical_name' => 'ARROZ BRANCO 5KG',
            'last_notified_price' => null,
        ]);

        $this->artisan('prices:check-favorite-drops');

        Notification::assertNothingSent();
        $this->assertDatabaseHas('favorite_products', [
            'canonical_name' => 'ARROZ BRANCO 5KG',
            'last_notified_price' => 18.00,
        ]);
    }

    public function test_skips_favorites_with_no_matching_offer(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        FavoriteProduct::factory()->for($user)->create(['canonical_name' => 'PRODUTO INEXISTENTE XYZ']);

        $this->artisan('prices:check-favorite-drops')->assertExitCode(0);

        Notification::assertNothingSent();
    }
}
