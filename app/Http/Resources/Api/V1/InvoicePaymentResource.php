<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoicePaymentResource extends JsonResource
{
    private const LABELS = [
        'dinheiro'         => 'Dinheiro',
        'cheque'           => 'Cheque',
        'cartao_credito'   => 'Cartão de Crédito',
        'cartao_debito'    => 'Cartão de Débito',
        'credito_loja'     => 'Crédito Loja',
        'vale_alimentacao' => 'Vale Alimentação',
        'vale_refeicao'    => 'Vale Refeição',
        'vale_presente'    => 'Vale Presente',
        'vale_combustivel' => 'Vale Combustível',
        'boleto'           => 'Boleto',
        'sem_pagamento'    => 'Sem Pagamento',
        'outros'           => 'Outros',
        'pix'              => 'Pix',
    ];

    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'method'       => $this->method,
            'method_label' => self::LABELS[$this->method] ?? $this->method,
            'amount'       => $this->amount,
        ];
    }
}
