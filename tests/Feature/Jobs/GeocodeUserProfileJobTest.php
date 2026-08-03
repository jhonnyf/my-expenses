<?php

namespace Tests\Feature\Jobs;

use App\Jobs\GeocodeUserProfileJob;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\GeocodingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeocodeUserProfileJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_saves_coordinates_on_profile(): void
    {
        Http::fake([
            '*nominatim*' => Http::response([
                ['lat' => '-25.4284', 'lon' => '-49.2733'],
            ], 200),
        ]);

        $user = User::factory()->create();
        $profile = UserProfile::factory()->for($user)->create(['cidade' => 'Curitiba', 'estado' => 'PR']);

        (new GeocodeUserProfileJob($profile->id))->handle(app(GeocodingService::class));

        $profile->refresh();
        $this->assertEqualsWithDelta(-25.4284, $profile->latitude, 0.0001);
        $this->assertEqualsWithDelta(-49.2733, $profile->longitude, 0.0001);
    }

    public function test_handle_skips_when_profile_has_no_city_or_state(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $profile = UserProfile::factory()->for($user)->create(['cidade' => null, 'estado' => null]);

        (new GeocodeUserProfileJob($profile->id))->handle(app(GeocodingService::class));

        Http::assertNothingSent();
    }
}
