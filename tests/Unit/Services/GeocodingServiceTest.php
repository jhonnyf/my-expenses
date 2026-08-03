<?php

namespace Tests\Unit\Services;

use App\Services\GeocodingService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeocodingServiceTest extends TestCase
{
    private GeocodingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GeocodingService::class);
        Cache::flush();
    }

    public function test_geocode_returns_coordinates_on_success(): void
    {
        Http::fake([
            '*nominatim*' => Http::response([
                ['lat' => '-25.4284', 'lon' => '-49.2733'],
            ], 200),
        ]);

        $result = $this->service->geocode('Curitiba', 'PR');

        $this->assertSame(['lat' => -25.4284, 'lng' => -49.2733], $result);
    }

    public function test_geocode_returns_null_on_empty_response(): void
    {
        Http::fake(['*nominatim*' => Http::response([], 200)]);

        $this->assertNull($this->service->geocode('Cidade Inexistente', 'XX'));
    }

    public function test_geocode_returns_null_on_failed_response(): void
    {
        Http::fake(['*nominatim*' => Http::response(null, 500)]);

        $this->assertNull($this->service->geocode('Curitiba', 'PR'));
    }

    public function test_geocode_returns_null_on_connection_exception(): void
    {
        Http::fake(function () {
            throw new ConnectionException('timeout');
        });

        $this->assertNull($this->service->geocode('Curitiba', 'PR'));
    }

    public function test_geocode_returns_null_when_city_or_state_is_empty(): void
    {
        Http::fake();

        $this->assertNull($this->service->geocode('', 'PR'));
        $this->assertNull($this->service->geocode('Curitiba', ''));
        Http::assertNothingSent();
    }

    public function test_geocode_caches_result_and_avoids_second_http_call(): void
    {
        Http::fake([
            '*nominatim*' => Http::response([
                ['lat' => '-25.4284', 'lon' => '-49.2733'],
            ], 200),
        ]);

        $this->service->geocode('Curitiba', 'PR');
        $this->service->geocode('Curitiba', 'PR');

        Http::assertSentCount(1);
    }

    public function test_reverse_geocode_resolves_city_and_uf_from_full_state_name(): void
    {
        Http::fake([
            '*nominatim*' => Http::response([
                'address' => ['city' => 'Curitiba', 'state' => 'Paraná'],
            ], 200),
        ]);

        $result = $this->service->reverseGeocode(-25.4284, -49.2733);

        $this->assertSame(['city' => 'Curitiba', 'state' => 'PR'], $result);
    }

    public function test_reverse_geocode_falls_back_to_town_or_village_field(): void
    {
        Http::fake([
            '*nominatim*' => Http::response([
                'address' => ['town' => 'Piraquara', 'state' => 'Paraná'],
            ], 200),
        ]);

        $result = $this->service->reverseGeocode(-25.44, -49.06);

        $this->assertSame(['city' => 'Piraquara', 'state' => 'PR'], $result);
    }

    public function test_reverse_geocode_returns_null_when_city_missing(): void
    {
        Http::fake([
            '*nominatim*' => Http::response(['address' => ['state' => 'Paraná']], 200),
        ]);

        $this->assertNull($this->service->reverseGeocode(-25.4284, -49.2733));
    }

    public function test_reverse_geocode_returns_null_when_state_name_not_recognized(): void
    {
        Http::fake([
            '*nominatim*' => Http::response([
                'address' => ['city' => 'Curitiba', 'state' => 'Estado Inexistente'],
            ], 200),
        ]);

        $this->assertNull($this->service->reverseGeocode(-25.4284, -49.2733));
    }

    public function test_reverse_geocode_returns_null_on_failed_response(): void
    {
        Http::fake(['*nominatim*' => Http::response(null, 500)]);

        $this->assertNull($this->service->reverseGeocode(-25.4284, -49.2733));
    }

    public function test_reverse_geocode_returns_null_on_connection_exception(): void
    {
        Http::fake(function () {
            throw new ConnectionException('timeout');
        });

        $this->assertNull($this->service->reverseGeocode(-25.4284, -49.2733));
    }

    public function test_reverse_geocode_caches_result_and_avoids_second_http_call(): void
    {
        Http::fake([
            '*nominatim*' => Http::response([
                'address' => ['city' => 'Curitiba', 'state' => 'Paraná'],
            ], 200),
        ]);

        $this->service->reverseGeocode(-25.4284, -49.2733);
        $this->service->reverseGeocode(-25.4284, -49.2733);

        Http::assertSentCount(1);
    }
}
