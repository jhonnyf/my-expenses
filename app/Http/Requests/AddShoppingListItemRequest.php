<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\FailsAsJson;
use Illuminate\Foundation\Http\FormRequest;

class AddShoppingListItemRequest extends FormRequest
{
    use FailsAsJson;

    /** Limites do banco: `unit_price` é decimal(10,4) (máx. 999.999,9999). */
    public const MAX_PRICE = 999999.99;

    public const MAX_QUANTITY = 9999;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:20'],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'max:'.self::MAX_PRICE],
            'issuer_id' => ['nullable', 'integer', 'exists:issuers,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.self::MAX_QUANTITY],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.max' => 'A quantidade máxima é '.self::MAX_QUANTITY.'.',
            'unit_price.min' => 'O preço não pode ser negativo.',
            'unit_price.max' => 'O preço informado é alto demais.',
        ];
    }
}
