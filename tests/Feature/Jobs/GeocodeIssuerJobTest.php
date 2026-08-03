<?php

namespace Tests\Feature\Jobs;

use App\Jobs\GeocodeIssuerJob;
use App\Models\Issuer;
use App\Services\GeocodingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeocodeIssuerJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_saves_coordinates_on_issuer(): void
    {
        Http::fake([
            '*nominatim*' => Http::response([
                ['lat' => '-25.4284', 'lon' => '-49.2733'],
            ], 200),
        ]);

        $issuer = Issuer::factory()->create(['city' => 'Curitiba', 'state' => 'PR', 'latitude' => null]);

        (new GeocodeIssuerJob($issuer->id))->handle(app(GeocodingService::class));

        $issuer->refresh();
        $this->assertEqualsWithDelta(-25.4284, $issuer->latitude, 0.0001);
        $this->assertEqualsWithDelta(-49.2733, $issuer->longitude, 0.0001);
    }

    public function test_handle_skips_when_issuer_already_has_coordinates(): void
    {
        Http::fake();

        $issuer = Issuer::factory()->create([
            'city' => 'Curitiba',
            'state' => 'PR',
            'latitude' => -25.0,
            'longitude' => -49.0,
        ]);

        (new GeocodeIssuerJob($issuer->id))->handle(app(GeocodingService::class));

        Http::assertNothingSent();
    }

    public function test_handle_skips_when_issuer_has_no_city_or_state(): void
    {
        Http::fake();

        $issuer = Issuer::factory()->create(['city' => null, 'state' => null, 'latitude' => null]);

        (new GeocodeIssuerJob($issuer->id))->handle(app(GeocodingService::class));

        Http::assertNothingSent();
    }
}
