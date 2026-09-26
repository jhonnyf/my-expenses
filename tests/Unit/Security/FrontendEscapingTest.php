<?php

namespace Tests\Unit\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Nome de mercado, descrição, unidade e cidade vêm de notas fiscais de QUALQUER usuário e aparecem na tela
 * dos outros (Lista de Compras, Preços). Todo HTML montado no front com esses campos passa por
 * `Utils.escapeHtml`; o ApexCharts também insere título e valores do tooltip como HTML.
 *
 * Este teste é uma trava contra regressão: uma interpolação `${item.unit}` nova, sem escape, derruba a suíte.
 * O que já foi revisado e é seguro (escapado antes, em outro ponto) fica em SAFE_LINES.
 */
class FrontendEscapingTest extends TestCase
{
    private const FIELDS = 'unit|issuer_name|issuer|name|city|state|description|display_description|official_description|canonical_name|message|nickname|list_name|display_name';

    private const SOURCES = 'item|row|entry|r|s|point|n|data|saved|suggestion|issuer|group|list|payload|res|result';

    /** arquivo => trechos de linhas revisadas: o valor já chega escapado ou nunca vira HTML. */
    private const SAFE_LINES = [
        'prices.js' => [
            'return `${money(value)} — ${point.issuer}${tag}`', // point.issuer é escapado ao montar a série (issuer: Utils.escapeHtml(...))
            '<td class="text-center text-secondary-foreground text-sm">${r.unit}</td>', // r.unit escapado no map()
            '<span>Qtd: ${r.qtyFormatted} ${r.unit}</span>', // idem
            'renderComparisonChart(rows, rows.map(r => `${r.city}/${r.state}`))', // rótulo; o tooltip do gráfico escapa (tooltip.custom)
            'data-product="${encodeURIComponent(item.name)}"', // encodeURIComponent: sem aspas nem <>
        ],
    ];

    /** Linhas que não criam HTML (texto puro, título, mensagem, share). */
    private const NOT_HTML = '/escapeHtml|textContent|showFlash|title =|\.title|lines\.push|formatCurrency/';

    /**
     * @return array<string, array{string}>
     */
    public static function scripts(): array
    {
        // O provider roda antes de a aplicação subir: sem helpers do Laravel aqui.
        $root = dirname(__DIR__, 3);
        $files = array_merge(glob($root.'/resources/js/pages/*.js'), [$root.'/resources/js/utils.js']);

        return collect($files)->mapWithKeys(fn (string $file) => [basename($file) => [$file]])->all();
    }

    #[DataProvider('scripts')]
    public function test_server_text_is_escaped_before_it_becomes_html(string $file): void
    {
        $risky = '/\$\{[^}]*\b('.self::SOURCES.')\.('.self::FIELDS.')\b/';
        $safe = self::SAFE_LINES[basename($file)] ?? [];
        $offenders = [];

        foreach (file($file) as $number => $line) {
            if (! preg_match($risky, $line) || preg_match(self::NOT_HTML, $line)) {
                continue;
            }

            if (collect($safe)->contains(fn (string $snippet) => str_contains($line, $snippet))) {
                continue;
            }

            $offenders[] = basename($file).':'.($number + 1).': '.trim($line);
        }

        $this->assertSame([], $offenders, "Interpolação de texto vindo do servidor sem Utils.escapeHtml:\n".implode("\n", $offenders));
    }

    public function test_comparison_chart_tooltip_escapes_market_and_city_names(): void
    {
        $prices = file_get_contents(base_path('resources/js/pages/prices.js'));

        $this->assertStringContainsString('custom: ({ dataPointIndex })', $prices);
        $this->assertStringContainsString('escapeHtml(labels[dataPointIndex])', $prices);
    }

    public function test_blade_views_do_not_print_unescaped_variables_except_the_reviewed_one(): void
    {
        $unescaped = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('resources/views'))) as $file) {
            if (! str_ends_with((string) $file, '.blade.php') || str_contains((string) $file, '/vendor/')) {
                continue;
            }

            foreach (file((string) $file) as $number => $line) {
                if (str_contains($line, '{!!') && ! str_contains($line, '$proBadge')) {
                    $unescaped[] = str_replace(base_path().'/', '', (string) $file).':'.($number + 1);
                }
            }
        }

        $this->assertSame([], $unescaped, '{!! !!} imprime sem escape; revise e libere aqui só se o valor for fixo.');
    }
}
