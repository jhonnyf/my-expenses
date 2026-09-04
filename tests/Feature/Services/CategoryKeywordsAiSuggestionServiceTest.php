<?php

namespace Tests\Feature\Services;

use App\Services\CategoryKeywordsAiSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CategoryKeywordsAiSuggestionServiceTest extends TestCase
{
    use RefreshDatabase;

    private CategoryKeywordsAiSuggestionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.gemini.api_key' => 'test-key',
            'ai.gemini.model' => 'gemini-2.0-flash',
            'ai.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'ai.gemini.timeout' => 10,
        ]);

        $this->service = app(CategoryKeywordsAiSuggestionService::class);
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

    public function test_returns_empty_when_api_key_is_not_configured(): void
    {
        config(['ai.gemini.api_key' => null]);
        Http::fake();

        $result = $this->service->suggestKeywords('Alimentação');

        $this->assertSame([], $result->keywords);
        Http::assertNothingSent();
    }

    public function test_returns_empty_when_name_is_blank(): void
    {
        Http::fake();

        $result = $this->service->suggestKeywords('   ');

        $this->assertSame([], $result->keywords);
        Http::assertNothingSent();
    }

    public function test_returns_keywords_on_successful_response(): void
    {
        $this->fakeGeminiResponse(['keywords' => ['ARROZ', 'FEIJAO', 'MACARRAO']]);

        $result = $this->service->suggestKeywords('Alimentação');

        $this->assertSame(['ARROZ', 'FEIJAO', 'MACARRAO'], $result->keywords);
    }

    public function test_returns_empty_when_gemini_returns_empty_list(): void
    {
        $this->fakeGeminiResponse(['keywords' => []]);

        $result = $this->service->suggestKeywords('Categoria Vaga Demais');

        $this->assertSame([], $result->keywords);
    }

    public function test_returns_empty_on_http_error_response(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'quota exceeded'], 429),
        ]);
        Log::shouldReceive('warning')->once();

        $result = $this->service->suggestKeywords('Transporte');

        $this->assertSame([], $result->keywords);
    }

    public function test_returns_empty_on_malformed_json_in_text_field(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'isso não é json']]]],
                ],
            ], 200),
        ]);
        Log::shouldReceive('warning')->once();

        $result = $this->service->suggestKeywords('Transporte');

        $this->assertSame([], $result->keywords);
    }

    public function test_returns_empty_on_connection_exception(): void
    {
        Http::fake(function () {
            throw new ConnectionException('timeout');
        });
        Log::shouldReceive('warning')->once();

        $result = $this->service->suggestKeywords('Transporte');

        $this->assertSame([], $result->keywords);
    }

    public function test_does_not_cache_a_transient_http_failure(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'unavailable'], 503),
        ]);
        Log::shouldReceive('warning')->twice();

        $this->service->suggestKeywords('Alimentos');
        $result = $this->service->suggestKeywords('Alimentos');

        $this->assertSame([], $result->keywords);
        // Duas chamadas de verdade ao Gemini — se a falha tivesse sido
        // cacheada, a segunda chamada nem chegaria a sair.
        Http::assertSentCount(2);
    }

    public function test_caches_successful_response_and_does_not_call_api_twice(): void
    {
        $this->fakeGeminiResponse(['keywords' => ['ARROZ', 'FEIJAO']]);

        $this->service->suggestKeywords('Alimentação');
        $this->service->suggestKeywords('Alimentação');

        Http::assertSentCount(1);
    }

    public function test_cache_key_ignores_case_and_accents_of_name(): void
    {
        $this->fakeGeminiResponse(['keywords' => ['ARROZ', 'FEIJAO']]);

        $this->service->suggestKeywords('Alimentação');
        $this->service->suggestKeywords('  ALIMENTACAO  ');

        Http::assertSentCount(1);
    }

    public function test_sends_expected_request_payload_with_response_schema(): void
    {
        $this->fakeGeminiResponse(['keywords' => ['ARROZ']]);

        $this->service->suggestKeywords('Alimentação');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'models/gemini-2.0-flash:generateContent')
                && str_contains($request->url(), 'key=test-key')
                && str_contains($request['contents'][0]['parts'][0]['text'], 'Alimentação')
                && $request['generationConfig']['responseMimeType'] === 'application/json'
                && $request['generationConfig']['responseSchema']['required'] === ['keywords'];
        });
    }
}
