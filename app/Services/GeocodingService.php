<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Resolve cidade/estado (Brasil) em latitude/longitude via Nominatim (OpenStreetMap).
 * Falha silenciosamente (retorna null) em vez de lançar exceção — cidade/estado
 * continuam salvos mesmo sem coordenadas, e o filtro de raio na Lista de Compras
 * simplesmente é ignorado até o geocoding ser resolvido.
 */
class GeocodingService
{
    private const CACHE_TTL_DAYS = 30;

    /**
     * @return array{lat: float, lng: float}|null
     */
    public function geocode(string $city, string $state): ?array
    {
        $city = trim($city);
        $state = trim($state);

        if ($city === '' || $state === '') {
            return null;
        }

        $cacheKey = 'geocode:'.Str::slug("{$city}-{$state}");

        return Cache::remember($cacheKey, now()->addDays(self::CACHE_TTL_DAYS), function () use ($city, $state) {
            return $this->fetch($city, $state);
        });
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private function fetch(string $city, string $state): ?array
    {
        try {
            $response = Http::withHeaders(['User-Agent' => config('services.nominatim.user_agent')])
                ->timeout(5)
                ->get(config('services.nominatim.url').'/search', [
                    'city' => $city,
                    'state' => $state,
                    'country' => 'Brazil',
                    'format' => 'json',
                    'limit' => 1,
                ]);

            if (! $response->successful()) {
                Log::warning('Geocoding: resposta não bem-sucedida do Nominatim', [
                    'city' => $city,
                    'state' => $state,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $result = $response->json(0);

            if (! is_array($result) || ! isset($result['lat'], $result['lon'])) {
                return null;
            }

            return [
                'lat' => (float) $result['lat'],
                'lng' => (float) $result['lon'],
            ];
        } catch (\Throwable $e) {
            Log::warning('Geocoding: falha ao consultar Nominatim', [
                'city' => $city,
                'state' => $state,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Sentido oposto de geocode(): a partir de coordenadas (ex: as que o
     * navegador do usuário retorna via Geolocation API), resolve cidade/estado.
     * O nome do estado que o Nominatim devolve é por extenso (ex: "Paraná") —
     * convertido pra UF via a mesma lista usada no select de estado
     * (config/brazilian-states.php).
     *
     * @return array{city: string, state: string}|null
     */
    public function reverseGeocode(float $lat, float $lng): ?array
    {
        $cacheKey = 'reverse-geocode:'.round($lat, 3).','.round($lng, 3);

        return Cache::remember($cacheKey, now()->addDays(self::CACHE_TTL_DAYS), function () use ($lat, $lng) {
            return $this->fetchReverse($lat, $lng);
        });
    }

    /**
     * @return array{city: string, state: string}|null
     */
    private function fetchReverse(float $lat, float $lng): ?array
    {
        try {
            $response = Http::withHeaders(['User-Agent' => config('services.nominatim.user_agent')])
                ->timeout(5)
                ->get(config('services.nominatim.url').'/reverse', [
                    'lat' => $lat,
                    'lon' => $lng,
                    'format' => 'json',
                    'addressdetails' => 1,
                    'zoom' => 10,
                ]);

            if (! $response->successful()) {
                Log::warning('Reverse geocoding: resposta não bem-sucedida do Nominatim', [
                    'lat' => $lat,
                    'lng' => $lng,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $address = $response->json('address');

            if (! is_array($address)) {
                return null;
            }

            $city = $address['city'] ?? $address['town'] ?? $address['village'] ?? $address['municipality'] ?? null;
            $stateName = $address['state'] ?? null;

            if (! $city || ! $stateName) {
                return null;
            }

            $uf = $this->ufFromStateName($stateName);

            if ($uf === null) {
                return null;
            }

            return ['city' => $city, 'state' => $uf];
        } catch (\Throwable $e) {
            Log::warning('Reverse geocoding: falha ao consultar Nominatim', [
                'lat' => $lat,
                'lng' => $lng,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function ufFromStateName(string $stateName): ?string
    {
        static $namesToUf = null;
        $namesToUf ??= array_flip(config('brazilian-states'));

        return $namesToUf[$stateName] ?? null;
    }
}
