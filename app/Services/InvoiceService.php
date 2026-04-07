<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Issuer;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function createFromParsed(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            $user = $this->findOrCreateUser($data['destinatario']);

            $issuer = Issuer::firstOrCreate(
                ['cnpj' => $data['emitente']['cnpj']],
                [
                    'name'          => $data['emitente']['nome'],
                    'street'        => $data['emitente']['logradouro'],
                    'street_number' => $data['emitente']['numero'],
                    'neighborhood'  => $data['emitente']['bairro'],
                    'city'          => $data['emitente']['municipio'],
                    'state'         => $data['emitente']['uf'],
                    'zip_code'      => $data['emitente']['cep'],
                ]
            );

            $invoice = Invoice::firstOrCreate(
                ['access_key' => $data['chave']],
                [
                    'user_id'              => $user?->id,
                    'number'               => $data['numero'],
                    'series'               => $data['serie'],
                    'issued_at'            => $data['emitido_em'],
                    'environment'          => $data['ambiente'] === 'producao' ? 'production' : 'staging',

                    'issuer_id'            => $issuer->id,

                    'total_icms_base'      => $data['total']['base_calculo_icms'],
                    'total_icms'           => $data['total']['valor_icms'],
                    'total_products'       => $data['total']['valor_produtos'],
                    'total_amount'         => $data['total']['valor_nota'],
                    'total_taxes'          => $data['total']['valor_tributos'],
                ]
            );

            if ($invoice->wasRecentlyCreated) {
                $now = now();
                
                $itemsData = [];
                foreach ($data['itens'] as $item) {
                    $itemsData[] = [
                        'invoice_id'  => $invoice->id,
                        'item_number' => $item['numero_item'],
                        'code'        => $item['codigo'],
                        'description' => $item['descricao'],
                        'ncm'         => $item['ncm'],
                        'cfop'        => $item['cfop'],
                        'unit'        => $item['unidade'],
                        'quantity'    => $item['quantidade'],
                        'unit_price'  => $item['valor_unitario'],
                        'total_price' => $item['valor_total'],
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ];
                }
                if (!empty($itemsData)) {
                    $invoice->items()->insert($itemsData);
                }

                $paymentsData = [];
                foreach ($data['pagamento'] as $payment) {
                    $paymentsData[] = [
                        'invoice_id' => $invoice->id,
                        'method'     => $payment['forma'],
                        'amount'     => $payment['valor'],
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ];
                }
                if (!empty($paymentsData)) {
                    $invoice->payments()->insert($paymentsData);
                }
            }

            $invoice->setRelation('issuer', $issuer);
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
