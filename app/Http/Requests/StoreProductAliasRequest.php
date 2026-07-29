<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreProductAliasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Este endpoint é sempre chamado via AJAX (modal), então a resposta de
     * validação deve ser sempre JSON — mesmo nas rotas web, onde o padrão da
     * aplicação é redirecionar de volta com os erros na sessão.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Dados inválidos.',
            'errors' => $validator->errors(),
        ], 422));
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'canonical_name' => ['nullable', 'string', 'max:150'],
        ];
    }
}
