<?php

namespace Tests\Feature\Actions;

use App\Actions\CaptureUserLocationFromBrowserAction;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CaptureUserLocationFromBrowserActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_saves_exact_coordinates_and_reverse_geocoded_city(): void
    {
        Http::fake([
            '*nominatim*' => Http::response([
                'address' => ['city' => 'Curitiba', 'state' => 'Paraná'],
            ], 200),
        ]);

        $user = User::factory()->create();
        $action = app(CaptureUserLocationFromBrowserAction::class);

        $profile = $action->execute($user, -25.4284, -49.2733);

        $this->assertNotNull($profile);
        $this->assertSame('Curitiba', $profile->cidade);
        $this->assertSame('PR', $profile->estado);
        $this->assertEqualsWithDelta(-25.4284, $profile->latitude, 0.0001);
        $this->assertEqualsWithDelta(-49.2733, $profile->longitude, 0.0001);
    }

    public function test_execute_returns_null_when_reverse_geocoding_fails(): void
    {
        Http::fake(['*nominatim*' => Http::response([], 200)]);

        $user = User::factory()->create();
        $action = app(CaptureUserLocationFromBrowserAction::class);

        $profile = $action->execute($user, -25.4284, -49.2733);

        $this->assertNull($profile);
        $this->assertDatabaseMissing('users_profiles', ['user_id' => $user->id]);
    }

    public function test_execute_updates_existing_profile(): void
    {
        Http::fake([
            '*nominatim*' => Http::response([
                'address' => ['city' => 'São Paulo', 'state' => 'São Paulo'],
            ], 200),
        ]);

        $user = User::factory()->create();
        UserProfile::factory()->for($user)->create(['cidade' => 'Curitiba', 'estado' => 'PR']);
        $action = app(CaptureUserLocationFromBrowserAction::class);

        $profile = $action->execute($user, -23.5505, -46.6333);

        $this->assertSame('São Paulo', $profile->cidade);
        $this->assertSame('SP', $profile->estado);
        $this->assertDatabaseCount('users_profiles', 1);
    }
}
