<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function createFromParsed(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            $user = $this->findOrCreateUser($data['destinatario']);

            $invoice = Invoice::firstOrCreate(
                ['access_key' => $data['chave']],
                [
                    'user_id'              => $user?->id,
                    'number'               => $data['numero'],
                    'series'               => $data['serie'],
                    'issued_at'            => $data['emitido_em'],
                    'environment'          => $data['ambiente'] === 'producao' ? 'production' : 'staging',

                    'issuer_cnpj'          => $data['emitente']['cnpj'],
                    'issuer_name'          => $data['emitente']['nome'],
                    'issuer_street'        => $data['emitente']['logradouro'],
                    'issuer_street_number' => $data['emitente']['numero'],
                    'issuer_neighborhood'  => $data['emitente']['bairro'],
                    'issuer_city'          => $data['emitente']['municipio'],
                    'issuer_state'         => $data['emitente']['uf'],
                    'issuer_zip_code'      => $data['emitente']['cep'],

                    'total_icms_base'      => $data['total']['base_calculo_icms'],
                    'total_icms'           => $data['total']['valor_icms'],
                    'total_products'       => $data['total']['valor_produtos'],
                    'total_amount'         => $data['total']['valor_nota'],
                    'total_taxes'          => $data['total']['valor_tributos'],
                ]
            );

            if ($invoice->wasRecentlyCreated) {
                foreach ($data['itens'] as $item) {
                    $invoice->items()->create([
                        'item_number' => $item['numero_item'],
                        'code'        => $item['codigo'],
                        'description' => $item['descricao'],
                        'ncm'         => $item['ncm'],
                        'cfop'        => $item['cfop'],
                        'unit'        => $item['unidade'],
                        'quantity'    => $item['quantidade'],
                        'unit_price'  => $item['valor_unitario'],
                        'total_price' => $item['valor_total'],
                    ]);
                }

                foreach ($data['pagamento'] as $payment) {
                    $invoice->payments()->create([
                        'method' => $payment['forma'],
                        'amount' => $payment['valor'],
                    ]);
                }
            }

            return $invoice->load('user', 'items', 'payments');
        });
    }

    private function findOrCreateUser(array $recipient): ?User
    {
        $cpf  = $recipient['cpf']  ?: null;
        $cnpj = $recipient['cnpj'] ?: null;
        $name = $recipient['nome'] ?: null;

        if (!$cpf && !$cnpj) {
            return null;
        }

        $profile = $cpf
            ? UserProfile::firstOrNew(['cpf' => $cpf])
            : UserProfile::firstOrNew(['cnpj' => $cnpj]);

        if (!$profile->exists) {
            $user = User::create(['name' => $name]);
            $profile->cnpj    = $cnpj;
            $profile->cpf     = $cpf;
            $profile->user_id = $user->id;
            $profile->save();
        } else {
            $user = $profile->user;
        }

        return $user;
    }
}
