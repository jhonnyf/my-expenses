<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\FailsAsJson;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MergeCategoryRequest extends FormRequest
{
    use FailsAsJson;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_id' => [
                'required',
                'integer',
                Rule::notIn([$this->route('category')?->id]),
                Rule::exists('categories', 'id')->where(
                    fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $this->user()->id)
                ),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'target_id.not_in' => 'Escolha outra categoria para receber os itens.',
            'target_id.exists' => 'Categoria de destino inválida.',
        ];
    }
}
