<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateIssuerNicknameRequest extends FormRequest
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
            'nickname' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('issuer_nicknames', 'nickname')
                    ->where('user_id', Auth::id())
                    ->ignore($this->route('id'), 'issuer_id'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'nickname.unique' => 'Você já usa este apelido para outro emissor.',
            'nickname.max' => 'O apelido pode ter no máximo :max caracteres.',
        ];
    }
}
