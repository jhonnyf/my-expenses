<?php

namespace App\Jobs;

use App\Models\UserProfile;
use App\Services\GeocodingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;

class GeocodeUserProfileJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $userProfileId) {}

    public function middleware(): array
    {
        return [new RateLimited('geocoding')];
    }

    public function handle(GeocodingService $geocodingService): void
    {
        $profile = UserProfile::find($this->userProfileId);

        if (! $profile || empty($profile->cidade) || empty($profile->estado)) {
            return;
        }

        $coordinates = $geocodingService->geocode($profile->cidade, $profile->estado);

        if ($coordinates === null) {
            return;
        }

        $profile->update([
            'latitude' => $coordinates['lat'],
            'longitude' => $coordinates['lng'],
        ]);
    }
}
