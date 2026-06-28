<?php

namespace Tests\Feature\Services;

use App\Actions\ImportInvoiceAction;
use App\Models\Issuer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportInvoiceActionTest extends TestCase
{
    use RefreshDatabase;

    private array $parsedData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parsedData = [
            'chave'     => '35260600000000000191650010000099991234567890',
            'numero'    => '99999',
            'serie'     => '001',
            'emitido_em' => '2026-01-15T10:00:00-03:00',
            'ambiente'  => 'producao',
            'emitente'  => [
                'cnpj'       => '11222333000181',
                'nome'       => 'MERCADO ACTION TEST',
                'logradouro' => 'RUA DO TESTE',
                'numero'     => '1',
                'bairro'     => 'BAIRRO',
                'municipio'  => 'CIDADE',
                'uf'         => 'SP',
                'cep'        => '01001000',
            ],
            'destinatario' => ['cpf' => '11122233344', 'cnpj' => '', 'nome' => 'CLIENTE'],
            'itens'        => [
                [
                    'numero_item'    => 1,
                    'codigo'         => '001',
                    'descricao'      => 'PRODUTO A',
                    'ncm'            => '21069090',
                    'cfop'           => '5102',
                    'unidade'        => 'UN',
                    'quantidade'     => 2,
                    'valor_unitario' => 5.00,
                    'valor_total'    => 10.00,
                ],
                [
                    'numero_item'    => 2,
                    'codigo'         => '002',
                    'descricao'      => 'PRODUTO B',
                    'ncm'            => '21069090',
                    'cfop'           => '5102',
                    'unidade'        => 'KG',
                    'quantidade'     => 1,
                    'valor_unitario' => 15.00,
                    'valor_total'    => 15.00,
                ],
            ],
            'total'    => [
                'base_calculo_icms' => 0,
                'valor_icms'        => 0,
                'valor_produtos'    => 25.00,
                'valor_nota'        => 25.00,
                'valor_tributos'    => 0,
            ],
            'pagamento' => [
                ['forma' => 'cartao_credito', 'valor' => 25.00],
            ],
        ];
    }

    public function test_execute_creates_invoice_with_issuer_and_items(): void
    {
        $user   = User::factory()->create();
        $action = app(ImportInvoiceAction::class);

        $invoice = $action->execute($this->parsedData, '<nfeProc/>', $user->id);

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'user_id' => $user->id]);
        $this->assertDatabaseHas('issuers', ['cnpj' => '11222333000181']);
        $this->assertDatabaseCount('invoices_items', 2);
    }

    public function test_execute_reuses_existing_issuer_by_cnpj(): void
    {
        $user   = User::factory()->create();
        Issuer::factory()->create(['cnpj' => '11222333000181', 'name' => 'NOME ANTIGO']);
        $action = app(ImportInvoiceAction::class);

        $action->execute($this->parsedData, '<nfeProc/>', $user->id);

        $this->assertDatabaseCount('issuers', 1);
        $this->assertDatabaseHas('issuers', ['cnpj' => '11222333000181']);
    }

    public function test_execute_creates_payment_records(): void
    {
        $user   = User::factory()->create();
        $action = app(ImportInvoiceAction::class);

        $invoice = $action->execute($this->parsedData, '<nfeProc/>', $user->id);

        $this->assertDatabaseHas('invoices_payments', [
            'invoice_id' => $invoice->id,
            'method'     => 'cartao_credito',
        ]);
    }

    public function test_execute_links_invoice_to_user(): void
    {
        $user   = User::factory()->create();
        $action = app(ImportInvoiceAction::class);

        $invoice = $action->execute($this->parsedData, '<nfeProc/>', $user->id);

        $this->assertEquals($user->id, $invoice->user_id);
    }
}
