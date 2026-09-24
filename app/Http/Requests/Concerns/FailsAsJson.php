<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Para requests chamados sempre via AJAX: nas rotas web a falha de validação costuma redirecionar
 * de volta com os erros na sessão, o que quebra o cliente — aqui a resposta é sempre JSON 422.
 */
trait FailsAsJson
{
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Dados inválidos.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
