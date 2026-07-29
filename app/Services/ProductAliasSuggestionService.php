<?php

namespace App\Services;

use App\Models\InvoiceItem;
use App\Models\ProductAlias;
use App\Models\ProductAliasSuggestionDismissal;
use App\Support\ProductNameNormalizer;
use Illuminate\Support\Collection;

class ProductAliasSuggestionService
{
    private const SIMILARITY_THRESHOLD = 70.0;

    /**
     * Limita quantas descrições distintas entram na comparação par-a-par (O(n²)).
     * Mantém a checagem instantânea sem precisar de um índice de busca dedicado.
     * Usado apenas no fluxo automático (tela de Histórico de Preços); a revisão
     * completa (suggestAll) processa todas as descrições do usuário.
     */
    private const MAX_CANDIDATES = 300;

    private const MAX_SUGGESTIONS = 20;

    private const MAX_SUGGESTIONS_FULL_REVIEW = 200;

    public function suggest(int $userId): array
    {
        return $this->resolveSuggestions($userId, self::MAX_CANDIDATES, self::MAX_SUGGESTIONS);
    }

    /**
     * Varre todas as descrições de produto do usuário, sem o teto de
     * candidatos do fluxo automático. Pensado para uma tela dedicada de
     * revisão em lote, não para ser chamado a cada carregamento de página.
     */
    public function suggestAll(int $userId): array
    {
        return $this->resolveSuggestions($userId, null, self::MAX_SUGGESTIONS_FULL_REVIEW);
    }

    public function dismiss(int $userId, string $descriptionA, string $descriptionB): void
    {
        ProductAliasSuggestionDismissal::firstOrCreate([
            'user_id' => $userId,
            'description_a' => min($descriptionA, $descriptionB),
            'description_b' => max($descriptionA, $descriptionB),
        ]);
    }

    private function resolveSuggestions(int $userId, ?int $candidateLimit, int $suggestionLimit): array
    {
        $query = InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->where('invoices.user_id', $userId)
            ->select('invoices_items.description')
            ->distinct()
            ->orderBy('invoices_items.description');

        if ($candidateLimit !== null) {
            $query->limit($candidateLimit);
        }

        $descriptions = $query->pluck('description')->values();

        $canonicalByDescription = ProductAlias::where('user_id', $userId)
            ->pluck('canonical_name', 'description');

        $dismissedPairs = ProductAliasSuggestionDismissal::where('user_id', $userId)
            ->get()
            ->map(fn ($d) => $this->pairKey($d->description_a, $d->description_b))
            ->flip();

        $suggestions = $this->buildSuggestions($descriptions, $canonicalByDescription, $dismissedPairs);

        usort($suggestions, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return array_slice($suggestions, 0, $suggestionLimit);
    }

    private function buildSuggestions(Collection $descriptions, Collection $canonicalByDescription, Collection $dismissedPairs): array
    {
        // Normaliza/tokeniza cada descrição uma única vez fora do loop O(n²),
        // em vez de refazer isso a cada par comparado.
        $tokensByDescription = $descriptions->mapWithKeys(
            fn (string $description) => [$description => ProductNameNormalizer::tokenize($description)]
        );

        $suggestions = [];
        $count = $descriptions->count();

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $a = $descriptions[$i];
                $b = $descriptions[$j];

                if ($this->alreadyUnified($a, $b, $canonicalByDescription)) {
                    continue;
                }

                if (isset($dismissedPairs[$this->pairKey($a, $b)])) {
                    continue;
                }

                $score = $this->similarity($tokensByDescription[$a], $tokensByDescription[$b]);

                if ($score >= self::SIMILARITY_THRESHOLD) {
                    $suggestions[] = ['description_a' => $a, 'description_b' => $b, 'score' => round($score, 1)];
                }
            }
        }

        return $suggestions;
    }

    private function alreadyUnified(string $a, string $b, Collection $canonicalByDescription): bool
    {
        $canonicalA = $canonicalByDescription[$a] ?? null;
        $canonicalB = $canonicalByDescription[$b] ?? null;

        return $canonicalA !== null && $canonicalB !== null && $canonicalA === $canonicalB;
    }

    private function pairKey(string $a, string $b): string
    {
        return min($a, $b)."\0".max($a, $b);
    }

    /**
     * Compara pelas palavras normalizadas em vez da string inteira: descrições
     * de NFC-e do mesmo produto costumam vir em ordem diferente e com
     * abreviações distintas entre lojas (ex.: "REFRIG COCA COLA 350ML LAT" x
     * "COCA-COLA LATA 350ML"), o que penaliza demais um similar_text() puro.
     *
     * @param  list<string>  $tokensA
     * @param  list<string>  $tokensB
     */
    private function similarity(array $tokensA, array $tokensB): float
    {
        if ($tokensA === [] || $tokensB === []) {
            return 0.0;
        }

        $usedB = [];
        $matches = 0;

        foreach ($tokensA as $tokenA) {
            foreach ($tokensB as $index => $tokenB) {
                if (isset($usedB[$index])) {
                    continue;
                }

                if ($this->tokensMatch($tokenA, $tokenB)) {
                    $usedB[$index] = true;
                    $matches++;
                    break;
                }
            }
        }

        return ($matches / max(count($tokensA), count($tokensB))) * 100;
    }

    /**
     * Tokens com dígito (medidas/quantidades como "350ML", "5KG") só casam
     * exatamente; os demais podem casar por prefixo (mín. 3 letras) para
     * cobrir abreviações comuns em NFC-e ("REFRIG" x "REFRIGERANTE").
     */
    private function tokensMatch(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        if (preg_match('/\d/', $a) || preg_match('/\d/', $b)) {
            return false;
        }

        if (strlen($a) < 3 || strlen($b) < 3) {
            return false;
        }

        return str_starts_with($a, $b) || str_starts_with($b, $a);
    }
}
