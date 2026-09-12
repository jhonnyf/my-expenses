<?php

namespace Tests\Unit\Support;

use App\Support\ProductNameNormalizer;
use Tests\TestCase;

class ProductNameNormalizerTest extends TestCase
{
    public function test_normalize_uppercases_text(): void
    {
        $this->assertSame('ARROZ', ProductNameNormalizer::normalize('arroz'));
    }

    public function test_normalize_strips_accents(): void
    {
        $this->assertSame('ALIMENTACAO', ProductNameNormalizer::normalize('Alimentação'));
        $this->assertSame('ACAO', ProductNameNormalizer::normalize('Ação'));
        $this->assertSame('CAFE', ProductNameNormalizer::normalize('café'));
    }

    /**
     * Regressão: iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', ...) tem implementação
     * dependente de libc — na musl (Alpine, imagem de produção deste projeto) "Ã"/"Ç"
     * viravam sequências com til (ex.: "AÇÃO" -> "AC~AO") em vez de simplesmente cair
     * o acento, fazendo o texto acentuado normalizar diferente do equivalente sem
     * acento e quebrando cache/busca por nome. Str::ascii() não depende da libc.
     */
    public function test_accented_and_unaccented_versions_of_the_same_text_normalize_equally(): void
    {
        $this->assertSame(
            ProductNameNormalizer::normalize('Alimentação'),
            ProductNameNormalizer::normalize('  ALIMENTACAO  '),
        );
    }

    public function test_normalize_replaces_non_alphanumeric_characters_with_space(): void
    {
        $this->assertSame('COCA COLA 350ML', ProductNameNormalizer::normalize('Coca-Cola 350ml'));
    }

    public function test_normalize_collapses_multiple_spaces_and_trims(): void
    {
        $this->assertSame('ARROZ INTEGRAL', ProductNameNormalizer::normalize('  arroz    integral  '));
    }

    public function test_tokenize_splits_normalized_text_into_words(): void
    {
        $this->assertSame(['REFRIGERANTE', 'COCA', 'COLA', '350ML'], ProductNameNormalizer::tokenize('Refrigerante Coca-Cola 350ml'));
    }

    public function test_tokenize_returns_empty_array_for_blank_text(): void
    {
        $this->assertSame([], ProductNameNormalizer::tokenize('   '));
        $this->assertSame([], ProductNameNormalizer::tokenize('%%%'));
    }
}
