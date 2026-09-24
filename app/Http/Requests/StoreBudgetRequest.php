<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\FailsAsJson;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBudgetRequest extends FormRequest
{
    use FailsAsJson;

    public const MAX_AMOUNT = 99999999.99;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Só categorias do próprio usuário ou do sistema: id alheio vazaria nome/cor da categoria.
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where(
                    fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $this->user()->id)
                ),
            ],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.self::MAX_AMOUNT],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.exists' => 'Categoria inválida.',
            'amount.min' => 'O limite deve ser de pelo menos R$ 0,01.',
            'amount.max' => 'O limite máximo é R$ 99.999.999,99.',
        ];
    }
}
