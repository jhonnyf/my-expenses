<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\FailsAsJson;
use Illuminate\Foundation\Http\FormRequest;

class StoreShoppingListRequest extends FormRequest
{
    use FailsAsJson;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return ['name.max' => 'O nome da lista pode ter no máximo :max caracteres.'];
    }
}
