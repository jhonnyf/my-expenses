<?php

namespace Tests\Feature;

use App\Imports\NfceXmlImporter;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Issuer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NfceImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_importar_nfce_successfully_parses_and_saves_data()
    {
        // Mocking the importer to prevent file system dependency
        $this->mock(NfceXmlImporter::class, function ($mock) {
            $mock->shouldReceive('fromFile')->andReturn([
                'chave'      => '12345678901234567890123456789012345678901234',
                'numero'     => '12345',
                'serie'      => '1',
                'emitido_em' => '2026-03-15T12:00:00-03:00',
                'ambiente'   => 'producao',
                'emitente'   => [
                    'cnpj'       => '00000000000191',
                    'nome'       => 'SUPERMERCADO TESTE',
                    'logradouro' => 'RUA TESTE',
                    'numero'     => '123',
                    'bairro'     => 'BAIRRO TESTE',
                    'municipio'  => 'CIDADE TESTE',
                    'uf'         => 'SP',
                    'cep'        => '00000000',
                ],
                'destinatario' => [
                    'cpf'  => '11122233344',
                    'cnpj' => '',
                    'nome' => 'CLIENTE TESTE',
                ],
                'itens'      => [
                    [
                        'numero_item'    => 1,
                        'codigo'         => '1001',
                        'descricao'      => 'PRODUTO TESTE',
                        'ncm'            => '00000000',
                        'cfop'           => '5102',
                        'unidade'        => 'UN',
                        'quantidade'     => 2,
                        'valor_unitario' => 10.50,
                        'valor_total'    => 21.00,
                    ]
                ],
                'total'      => [
                    'base_calculo_icms' => 0,
                    'valor_icms'        => 0,
                    'valor_produtos'    => 21.00,
                    'valor_nota'        => 21.00,
                    'valor_tributos'    => 0,
                ],
                'pagamento'  => [
                    [
                        'forma' => 'dinheiro',
                        'valor' => 21.00,
                    ]
                ],
            ]);
        });

        $response = $this->postJson(route('nfce.importar'));

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'access_key',
            'issuer_id',
            'total_amount',
            'issuer' => [
                'id',
                'cnpj',
                'name',
            ],
            'items' => [
                '*' => ['id', 'description', 'total_price']
            ],
            'payments' => [
                '*' => ['id', 'method', 'amount']
            ],
        ]);

        $this->assertDatabaseCount(Issuer::class, 1);
        $this->assertDatabaseHas(Issuer::class, [
            'cnpj' => '00000000000191',
            'name' => 'SUPERMERCADO TESTE',
        ]);

        $this->assertDatabaseCount(Invoice::class, 1);
        $this->assertDatabaseHas(Invoice::class, [
            'access_key'   => '12345678901234567890123456789012345678901234',
            'total_amount' => 21.00,
        ]);

        $this->assertDatabaseCount(InvoiceItem::class, 1);
        $this->assertDatabaseHas(InvoiceItem::class, [
            'description' => 'PRODUTO TESTE',
            'total_price' => 21.00,
        ]);

        $this->assertDatabaseCount(InvoicePayment::class, 1);
        $this->assertDatabaseHas(InvoicePayment::class, [
            'method' => 'dinheiro',
            'amount' => 21.00,
        ]);

        $this->assertDatabaseCount(User::class, 1);
        $this->assertDatabaseHas(User::class, [
            'name' => 'CLIENTE TESTE',
        ]);
    }
}
