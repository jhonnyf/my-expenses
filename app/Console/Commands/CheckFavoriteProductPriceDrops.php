<?php

namespace App\Console\Commands;

use App\Models\FavoriteProduct;
use App\Notifications\FavoriteProductPriceDropped;
use App\Services\PriceComparisonService;
use Illuminate\Console\Command;

class CheckFavoriteProductPriceDrops extends Command
{
    private const DROP_THRESHOLD_PERCENT = 5.0;

    protected $signature = 'prices:check-favorite-drops';

    protected $description = 'Verifica queda de preço nos produtos favoritados e notifica os usuários';

    public function handle(PriceComparisonService $service): int
    {
        $notified = 0;

        FavoriteProduct::with('user')->chunkById(200, function ($favorites) use ($service, &$notified) {
            foreach ($favorites as $favorite) {
                $offer = $service->cheapestOffer($favorite->canonical_name, $favorite->user_id);

                if ($offer === null) {
                    continue;
                }

                if ($this->droppedEnough($favorite->last_notified_price, $offer['price'])) {
                    $favorite->user->notify(new FavoriteProductPriceDropped(
                        $favorite->canonical_name,
                        $offer['price'],
                        $offer['issuer_name'],
                        $offer['city'],
                        $offer['state'],
                    ));
                    $notified++;
                }

                $favorite->update([
                    'last_notified_price' => $offer['price'],
                    'last_notified_at' => now(),
                ]);
            }
        });

        $this->info("{$notified} notificação(ões) de queda de preço enviada(s).");

        return self::SUCCESS;
    }

    private function droppedEnough(?float $previousPrice, float $currentPrice): bool
    {
        if ($previousPrice === null || $previousPrice <= 0) {
            return false;
        }

        $dropPercent = (($previousPrice - $currentPrice) / $previousPrice) * 100;

        return $dropPercent >= self::DROP_THRESHOLD_PERCENT;
    }
}
