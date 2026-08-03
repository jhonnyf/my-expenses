<?php

namespace App\Jobs;

use App\Models\Issuer;
use App\Services\GeocodingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;

class GeocodeIssuerJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $issuerId) {}

    public function middleware(): array
    {
        return [new RateLimited('geocoding')];
    }

    public function handle(GeocodingService $geocodingService): void
    {
        $issuer = Issuer::find($this->issuerId);

        if (! $issuer || $issuer->latitude !== null || empty($issuer->city) || empty($issuer->state)) {
            return;
        }

        $coordinates = $geocodingService->geocode($issuer->city, $issuer->state);

        if ($coordinates === null) {
            return;
        }

        $issuer->update([
            'latitude' => $coordinates['lat'],
            'longitude' => $coordinates['lng'],
        ]);
    }
}
