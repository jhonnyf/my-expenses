<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\FailsAsJson;
use Illuminate\Foundation\Http\FormRequest;

class AddToShoppingListRequest extends FormRequest
{
    use FailsAsJson;

    public function authorize(): bool
    {
        return true;
    }

    /** Sem `shopping_list_id` o item vai para uma lista nova; sem mercado/preço entra só o produto. */
    public function rules(): array
    {
        return [
            'shopping_list_id' => ['nullable', 'exists:shopping_lists,id'],
            'description' => ['required', 'string', 'max:255'],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'max:'.AddShoppingListItemRequest::MAX_PRICE],
            'issuer_id' => ['nullable', 'exists:issuers,id'],
            'unit' => ['nullable', 'string', 'max:20'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:'.AddShoppingListItemRequest::MAX_QUANTITY],
        ];
    }
}
