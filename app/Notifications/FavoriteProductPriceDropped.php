<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class FavoriteProductPriceDropped extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $productName,
        private readonly float $newPrice,
        private readonly string $issuerName,
        private readonly string $city,
        private readonly string $state,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'product_name' => $this->productName,
            'new_price' => $this->newPrice,
            'issuer_name' => $this->issuerName,
            'city' => $this->city,
            'state' => $this->state,
        ];
    }
}
