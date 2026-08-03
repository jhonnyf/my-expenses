<?php

namespace Tests\Feature\Services;

use App\Models\Invoice;
use App\Models\Issuer;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\LocationSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationSuggestionServiceTest extends TestCase
{
    use RefreshDatabase;

    private LocationSuggestionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LocationSuggestionService::class);
    }

    public function test_returns_null_when_user_has_no_invoices(): void
    {
        $user = User::factory()->create();

        $this->assertNull($this->service->suggestionFor($user));
    }

    public function test_suggests_most_frequent_city_when_above_threshold(): void
    {
        $user = User::factory()->create();
        $saoPaulo = Issuer::factory()->create(['city' => 'São Paulo', 'state' => 'SP']);
        $curitiba = Issuer::factory()->create(['city' => 'Curitiba', 'state' => 'PR']);

        Invoice::factory()->for($user)->for($saoPaulo)->count(4)->create(['issued_at' => now()->subDays(5)]);
        Invoice::factory()->for($user)->for($curitiba)->create(['issued_at' => now()->subDays(5)]);

        $suggestion = $this->service->suggestionFor($user);

        $this->assertEquals(['city' => 'São Paulo', 'state' => 'SP'], $suggestion);
    }

    public function test_returns_null_when_no_single_city_reaches_threshold(): void
    {
        $user = User::factory()->create();
        $saoPaulo = Issuer::factory()->create(['city' => 'São Paulo', 'state' => 'SP']);
        $curitiba = Issuer::factory()->create(['city' => 'Curitiba', 'state' => 'PR']);

        Invoice::factory()->for($user)->for($saoPaulo)->count(2)->create(['issued_at' => now()->subDays(5)]);
        Invoice::factory()->for($user)->for($curitiba)->count(2)->create(['issued_at' => now()->subDays(5)]);

        $this->assertNull($this->service->suggestionFor($user));
    }

    public function test_returns_null_when_suggested_city_matches_profile(): void
    {
        $user = User::factory()->create();
        UserProfile::factory()->for($user)->create(['cidade' => 'São Paulo', 'estado' => 'SP']);
        $saoPaulo = Issuer::factory()->create(['city' => 'São Paulo', 'state' => 'SP']);

        Invoice::factory()->for($user)->for($saoPaulo)->count(4)->create(['issued_at' => now()->subDays(5)]);

        $this->assertNull($this->service->suggestionFor($user));
    }

    public function test_ignores_invoices_outside_lookback_window(): void
    {
        $user = User::factory()->create();
        $saoPaulo = Issuer::factory()->create(['city' => 'São Paulo', 'state' => 'SP']);

        Invoice::factory()->for($user)->for($saoPaulo)->count(4)->create(['issued_at' => now()->subDays(200)]);

        $this->assertNull($this->service->suggestionFor($user));
    }

    public function test_returns_null_when_recently_dismissed(): void
    {
        $user = User::factory()->create();
        UserProfile::factory()->for($user)->create([
            'cidade' => null,
            'estado' => null,
            'location_suggestion_dismissed_at' => now()->subDays(5),
        ]);
        $saoPaulo = Issuer::factory()->create(['city' => 'São Paulo', 'state' => 'SP']);
        Invoice::factory()->for($user)->for($saoPaulo)->count(4)->create(['issued_at' => now()->subDays(5)]);

        $this->assertNull($this->service->suggestionFor($user));
    }

    public function test_suggests_again_after_dismiss_cooldown_expires(): void
    {
        $user = User::factory()->create();
        UserProfile::factory()->for($user)->create([
            'cidade' => null,
            'estado' => null,
            'location_suggestion_dismissed_at' => now()->subDays(45),
        ]);
        $saoPaulo = Issuer::factory()->create(['city' => 'São Paulo', 'state' => 'SP']);
        Invoice::factory()->for($user)->for($saoPaulo)->count(4)->create(['issued_at' => now()->subDays(5)]);

        $this->assertEquals(['city' => 'São Paulo', 'state' => 'SP'], $this->service->suggestionFor($user));
    }
}
