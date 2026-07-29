<?php

namespace Tests\Feature\Services;

use App\Services\ProductNameAiSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ProductNameAiSuggestionServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductNameAiSuggestionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.gemini.api_key' => 'test-key',
            'ai.gemini.model' => 'gemini-2.0-flash',
            'ai.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'ai.gemini.timeout' => 10,
        ]);

        $this->service = app(ProductNameAiSuggestionService::class);
    }

    private function fakeGeminiResponse(array $payload, int $status = 200): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => json_encode($payload)]]]],
                ],
            ], $status),
        ]);
    }

    public function test_returns_not_confident_when_api_key_is_not_configured(): void
    {
        config(['ai.gemini.api_key' => null]);
        Http::fake();

        $result = $this->service->suggestName(['REFRIG COCA COLA 350ML LAT']);

        $this->assertFalse($result->confident);
        $this->assertNull($result->suggestedName);
        Http::assertNothingSent();
    }

    public function test_returns_not_confident_when_descriptions_are_empty(): void
    {
        Http::fake();

        $result = $this->service->suggestName(['', '   ']);

        $this->assertFalse($result->confident);
        Http::assertNothingSent();
    }

    public function test_returns_confident_suggestion_on_successful_response(): void
    {
        $this->fakeGeminiResponse(['confident' => true, 'suggested_name' => 'Coca-Cola 350ml']);

        $result = $this->service->suggestName(['REFRIG COCA COLA 350ML LAT', 'COCA-COLA LATA 350ML']);

        $this->assertTrue($result->confident);
        $this->assertEquals('Coca-Cola 350ml', $result->suggestedName);
    }

    public function test_returns_not_confident_when_gemini_reports_low_confidence(): void
    {
        $this->fakeGeminiResponse(['confident' => false, 'suggested_name' => null]);

        $result = $this->service->suggestName(['BANANA PRATA KG', 'BANANA NANICA KG']);

        $this->assertFalse($result->confident);
        $this->assertNull($result->suggestedName);
    }

    public function test_returns_not_confident_on_http_error_response(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'quota exceeded'], 429),
        ]);
        Log::shouldReceive('warning')->once();

        $result = $this->service->suggestName(['ARROZ 5KG']);

        $this->assertFalse($result->confident);
    }

    public function test_returns_not_confident_on_malformed_json_in_text_field(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'isso não é json']]]],
                ],
            ], 200),
        ]);
        Log::shouldReceive('warning')->once();

        $result = $this->service->suggestName(['ARROZ 5KG']);

        $this->assertFalse($result->confident);
    }

    public function test_returns_not_confident_on_connection_exception(): void
    {
        Http::fake(function () {
            throw new ConnectionException('timeout');
        });
        Log::shouldReceive('warning')->once();

        $result = $this->service->suggestName(['ARROZ 5KG']);

        $this->assertFalse($result->confident);
    }

    public function test_caches_successful_response_and_does_not_call_api_twice(): void
    {
        $this->fakeGeminiResponse(['confident' => true, 'suggested_name' => 'Arroz Branco 5kg']);

        $this->service->suggestName(['ARROZ 5KG']);
        $this->service->suggestName(['ARROZ 5KG']);

        Http::assertSentCount(1);
    }

    public function test_cache_key_ignores_order_and_casing_of_descriptions(): void
    {
        $this->fakeGeminiResponse(['confident' => true, 'suggested_name' => 'Coca-Cola 350ml']);

        $this->service->suggestName(['A', 'B']);
        $this->service->suggestName(['b', ' a ']);

        Http::assertSentCount(1);
    }

    public function test_sends_expected_request_payload_with_response_schema(): void
    {
        $this->fakeGeminiResponse(['confident' => true, 'suggested_name' => 'Coca-Cola 350ml']);

        $this->service->suggestName(['REFRIG COCA COLA 350ML LAT']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'models/gemini-2.0-flash:generateContent')
                && str_contains($request->url(), 'key=test-key')
                && $request['generationConfig']['responseMimeType'] === 'application/json'
                && $request['generationConfig']['responseSchema']['required'] === ['confident', 'suggested_name'];
        });
    }

    public function test_truncates_descriptions_over_max_limit(): void
    {
        $this->fakeGeminiResponse(['confident' => true, 'suggested_name' => 'Produto X']);

        $this->service->suggestName(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']);

        Http::assertSent(function ($request) {
            $text = $request['contents'][0]['parts'][0]['text'];

            return substr_count($text, "\n- ") === 5;
        });
    }
}
