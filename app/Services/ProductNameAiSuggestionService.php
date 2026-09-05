<?php

namespace App\Services;

use App\DTOs\ProductNameAiSuggestion;
use App\Support\ProductNameNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProductNameAiSuggestionService
{
    /**
     * Bump manual sempre que o texto do prompt mudar de forma relevante —
     * invalida o cache automaticamente, sem precisar de comando manual.
     */
    private const PROMPT_VERSION = 2;

    private const MAX_DESCRIPTIONS = 5;

    public function isConfigured(): bool
    {
        return ! empty(config('ai.gemini.api_key'));
    }

    public function suggestName(array $descriptions): ProductNameAiSuggestion
    {
        $descriptions = $this->sanitizeDescriptions($descriptions);

        if ($descriptions === []) {
            return ProductNameAiSuggestion::notConfident();
        }

        if (! $this->isConfigured()) {
            Log::info('ProductNameAiSuggestionService: GEMINI_API_KEY não configurada, pulando sugestão de IA.');

            return ProductNameAiSuggestion::notConfident();
        }

        return Cache::rememberForever(
            $this->cacheKey($descriptions),
            fn () => $this->askGemini($descriptions)
        );
    }

    private function sanitizeDescriptions(array $descriptions): array
    {
        $cleaned = array_values(array_unique(array_filter(
            array_map(fn ($d) => trim((string) $d), $descriptions),
            fn (string $d) => $d !== ''
        )));

        return array_slice($cleaned, 0, self::MAX_DESCRIPTIONS);
    }

    /**
     * Chave estável por conjunto normalizado de descrições (ordem/caixa não
     * importam), prefixada pela versão do prompt e o modelo configurado —
     * trocar qualquer um dos dois invalida o cache automaticamente.
     */
    private function cacheKey(array $descriptions): string
    {
        $normalized = collect($descriptions)
            ->map(fn (string $d) => ProductNameNormalizer::normalize($d))
            ->unique()
            ->sort()
            ->values();

        return sprintf(
            'ai_product_name:%d:%s:%s',
            self::PROMPT_VERSION,
            config('ai.gemini.model'),
            sha1($normalized->implode('|'))
        );
    }

    private function askGemini(array $descriptions): ProductNameAiSuggestion
    {
        try {
            $response = Http::timeout((int) config('ai.gemini.timeout', 10))
                ->post($this->endpointUrl(), $this->buildRequestPayload($descriptions));

            if ($response->failed()) {
                Log::warning('ProductNameAiSuggestionService: Gemini retornou erro HTTP.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return ProductNameAiSuggestion::notConfident();
            }

            return $this->parseResponse($response->json());
        } catch (\Throwable $e) {
            Log::warning('ProductNameAiSuggestionService: falha ao consultar Gemini.', [
                'message' => $e->getMessage(),
            ]);

            return ProductNameAiSuggestion::notConfident();
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

    private function buildRequestPayload(array $descriptions): array
    {
        $lines = collect($descriptions)->map(fn (string $d) => "- {$d}")->implode("\n");

        return [
            'systemInstruction' => [
                'parts' => [['text' => $this->systemInstructionText()]],
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => "Descrições a analisar:\n{$lines}"]],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'responseMimeType' => 'application/json',
                'responseSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'confident' => ['type' => 'boolean'],
                        'suggested_name' => ['type' => 'string', 'nullable' => true],
                    ],
                    'required' => ['confident', 'suggested_name'],
                    'propertyOrdering' => ['confident', 'suggested_name'],
                ],
            ],
        ];
    }

    /**
     * Texto fixo, sem interpolar nada vindo do usuário (evita prompt
     * injection via descrição de produto).
     */
    private function systemInstructionText(): string
    {
        return 'Você é um assistente que padroniza nomes de produtos de supermercado/varejo '
            .'brasileiro a partir de descrições abreviadas extraídas de Notas Fiscais de '
            .'Consumidor Eletrônica (NFC-e). Essas descrições costumam vir em CAIXA ALTA, '
            .'truncadas, com abreviações inconsistentes entre estabelecimentos diferentes. '
            .'Abreviações comuns a decodificar: '
            .'categoria — "REFRIG"/"REFRI"/"REFG" = Refrigerante, "CERV" = Cerveja, '
            .'"AGUA"/"AG" = Água, "SUC" = Suco; '
            .'sabor/variação — "GUAR" = Guaraná, "LAR"/"LARANJ" = Laranja, "UV" = Uva, '
            .'"LIM" = Limão, "MORANG" = Morango, "COCO" = Coco, "ZERO"/"ZR" = Zero açúcar, '
            .'"DIET" = Diet, "LT"/"LATA" = Lata, "GRF"/"GARRF" = Garrafa; '
            .'tamanho/unidade — números seguidos de unidade truncada pelo corte de impressão '
            .'da nota, como "350M", "600M", "1L5", costumam significar "350ml", "600ml", '
            .'"1,5L" quando o contexto (bebida, refrigerante, cerveja) confirma; normalize '
            .'para a forma completa ("ml"/"L") sem alterar o valor numérico informado. '
            .'Dado um conjunto de uma ou mais descrições brutas que um usuário está tentando '
            .'unificar sob um único nome, decida se elas seguramente representam o MESMO '
            .'produto (mesma categoria/sabor, mesma variação/tamanho quando informado — marca '
            .'não é obrigatória) e, em caso afirmativo, proponha um nome limpo e conciso em '
            .'português do Brasil, com capitalização normal (não CAIXA ALTA). '
            .'Use o formato "Marca Produto Tamanho" quando a marca estiver identificável no '
            .'texto (ex.: "Coca-Cola 350ml", "Arroz Branco Tio João 5kg"). Quando a descrição '
            .'não trouxer marca mas tiver categoria + sabor/variação + tamanho reconhecíveis '
            .'(mesmo após decodificar as abreviações acima), proponha um nome sem marca no '
            .'formato "Categoria Sabor/Variação Tamanho" (ex.: "REFRI GUAR ZERO 350M" -> '
            .'"Refrigerante Guaraná Zero 350ml") — a ausência de marca sozinha NUNCA é motivo '
            .'para responder com baixa confiança. '
            .'Se houver apenas uma descrição, apenas limpe/formate o nome, sem inventar '
            .'marca, sabor ou tamanho que não estejam implícitos no texto. '
            .'Se houver duas ou mais descrições e você não tiver confiança razoável de que se '
            .'referem ao mesmo produto (categorias diferentes, sabores diferentes, tamanhos '
            .'incompatíveis, ou ambiguidade genuína), OU se a descrição for curta/genérica '
            .'demais para um nome específico mesmo após decodificar as abreviações acima '
            .'(ex.: apenas "DIVERSOS", "ITEM", um código numérico sem nome), responda com '
            .'confident=false e suggested_name=null — nunca invente marca, sabor ou tamanho '
            .'que não estejam presentes ou fortemente implícitos no texto original.';
    }

    private function parseResponse(?array $body): ProductNameAiSuggestion
    {
        $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (! is_string($text)) {
            Log::warning('ProductNameAiSuggestionService: resposta do Gemini sem texto gerado.', ['body' => $body]);

            return ProductNameAiSuggestion::notConfident();
        }

        $data = json_decode($text, true);

        if (! is_array($data)) {
            Log::warning('ProductNameAiSuggestionService: texto gerado pelo Gemini não é JSON válido.', ['text' => $text]);

            return ProductNameAiSuggestion::notConfident();
        }

        return ProductNameAiSuggestion::fromGeminiPayload($data);
    }
}
