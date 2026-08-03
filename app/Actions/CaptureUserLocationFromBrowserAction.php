<?php

namespace App\Actions;

use App\Models\User;
use App\Models\UserProfile;
use App\Services\GeocodingService;

/**
 * Salva cidade/estado/latitude/longitude a partir da posição real informada
 * pelo navegador do usuário (Geolocation API) — mais precisa que geocodar o
 * nome da cidade depois, então grava a coordenada exata direto, sem passar
 * pelo job assíncrono de geocoding usado no fluxo manual (UpdateUserLocationAction).
 */
class CaptureUserLocationFromBrowserAction
{
    public function __construct(private readonly GeocodingService $geocodingService) {}

    public function execute(User $user, float $latitude, float $longitude): ?UserProfile
    {
        $location = $this->geocodingService->reverseGeocode($latitude, $longitude);

        if ($location === null) {
            return null;
        }

        return $user->profile()->updateOrCreate([], [
            'cidade' => $location['city'],
            'estado' => $location['state'],
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }
}
