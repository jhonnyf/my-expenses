<?php

namespace App\Services;

use App\DTOs\CategoryKeywordsAiSuggestion;
use App\Support\ProductNameNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CategoryKeywordsAiSuggestionService
{
    /**
     * Bump manual sempre que o texto do prompt mudar de forma relevante —
     * invalida o cache automaticamente, sem precisar de comando manual.
     */
    private const PROMPT_VERSION = 1;

    private const MAX_KEYWORDS = 15;

    public function isConfigured(): bool
    {
        return ! empty(config('ai.gemini.api_key'));
    }

    public function suggestKeywords(string $categoryName): CategoryKeywordsAiSuggestion
    {
        $categoryName = trim($categoryName);

        if ($categoryName === '') {
            return CategoryKeywordsAiSuggestion::empty();
        }

        if (! $this->isConfigured()) {
            Log::info('CategoryKeywordsAiSuggestionService: GEMINI_API_KEY não configurada, pulando sugestão de IA.');

            return CategoryKeywordsAiSuggestion::empty();
        }

        $cacheKey = $this->cacheKey($categoryName);
        $cached = Cache::get($cacheKey);

        if ($cached instanceof CategoryKeywordsAiSuggestion) {
            return $cached;
        }

        $suggestion = $this->askGemini($categoryName);

        if ($suggestion === null) {
            // Falha técnica (HTTP/timeout/JSON inválido) — não cacheia, pra
            // não travar uma indisponibilidade temporária do Gemini como
            // resultado permanente. Só o resultado de uma resposta válida
            // (mesmo que keywords=[]) é cacheado para sempre.
            return CategoryKeywordsAiSuggestion::empty();
        }

        Cache::forever($cacheKey, $suggestion);

        return $suggestion;
    }

    /**
     * Chave estável por nome normalizado (caixa/acentos/espaços não
     * importam), prefixada pela versão do prompt e o modelo configurado —
     * trocar qualquer um dos dois invalida o cache automaticamente.
     */
    private function cacheKey(string $categoryName): string
    {
        return sprintf(
            'ai_category_keywords:%d:%s:%s',
            self::PROMPT_VERSION,
            config('ai.gemini.model'),
            sha1(ProductNameNormalizer::normalize($categoryName))
        );
    }

    /**
     * @return CategoryKeywordsAiSuggestion|null `null` indica falha técnica
     *                                           (não deve ser cacheada) — distinto de uma sugestão válida vazia.
     */
    private function askGemini(string $categoryName): ?CategoryKeywordsAiSuggestion
    {
        try {
            $response = Http::timeout((int) config('ai.gemini.timeout', 10))
                ->post($this->endpointUrl(), $this->buildRequestPayload($categoryName));

            if ($response->failed()) {
                Log::warning('CategoryKeywordsAiSuggestionService: Gemini retornou erro HTTP.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $this->parseResponse($response->json());
        } catch (\Throwable $e) {
            Log::warning('CategoryKeywordsAiSuggestionService: falha ao consultar Gemini.', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function endpointUrl(): string
    {
        return sprintf(
            '%s/models/%s:generateContent?key=%s',
            config('ai.gemini.base_url'),
            config('ai.gemini.model'),
            config('ai.gemini.api_key')
        );
    }

    private function buildRequestPayload(string $categoryName): array
    {
        return [
            'systemInstruction' => [
                'parts' => [['text' => $this->systemInstructionText()]],
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => "Nome da categoria: {$categoryName}"]],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'responseMimeType' => 'application/json',
                'responseSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'keywords' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'maxItems' => self::MAX_KEYWORDS,
                        ],
                    ],
                    'required' => ['keywords'],
                    'propertyOrdering' => ['keywords'],
                ],
            ],
        ];
    }

    /**
     * Texto fixo, sem interpolar nada vindo do usuário (evita prompt
     * injection via nome de categoria) — o nome só entra no turno "user".
     */
    private function systemInstructionText(): string
    {
        return 'Você é um assistente que sugere palavras-chave para categorização automática de '
            .'gastos, a partir do nome de uma categoria de despesas de um app financeiro pessoal '
            .'brasileiro. As palavras-chave são usadas para casar, por substring, contra '
            .'descrições de produtos extraídas de Notas Fiscais de Consumidor Eletrônica (NFC-e) '
            .'de supermercado/varejo — essas descrições vêm em CAIXA ALTA, abreviadas e sem '
            .'acentuação (ex.: "ARROZ", "FEIJAO", "REFRIG COCA COLA 350ML"). '
            .'Dado o nome de uma categoria, gere uma lista de até '.self::MAX_KEYWORDS.' palavras-chave '
            .'em CAIXA ALTA, SEM ACENTOS, curtas (uma ou poucas palavras cada, sem frases), que '
            .'tendem a aparecer em descrições de produtos pertencentes a essa categoria (ex.: para '
            .'"Alimentação" → ARROZ, FEIJAO, MACARRAO, ACUCAR, OLEO, CAFE, LEITE, ACOUGUE, HORTIFRUTI). '
            .'Não repita o próprio nome da categoria a não ser que ele também seja um termo comum '
            .'de produto. Se o nome da categoria for vago, genérico demais, ou não fizer sentido '
            .'como categoria de despesa (impossível sugerir termos específicos com segurança), '
            .'responda com keywords=[] (lista vazia) — nunca invente termos aleatórios só para '
            .'preencher a lista.';
    }

    private function parseResponse(?array $body): ?CategoryKeywordsAiSuggestion
    {
        $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (! is_string($text)) {
            Log::warning('CategoryKeywordsAiSuggestionService: resposta do Gemini sem texto gerado.', ['body' => $body]);

            return null;
        }

        $data = json_decode($text, true);

        if (! is_array($data)) {
            Log::warning('CategoryKeywordsAiSuggestionService: texto gerado pelo Gemini não é JSON válido.', ['text' => $text]);

            return null;
        }

        return CategoryKeywordsAiSuggestion::fromGeminiPayload($data);
    }
}
