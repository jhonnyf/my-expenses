<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddToShoppingListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shopping_list_id' => ['required', 'exists:shopping_lists,id'],
            'description'      => ['required', 'string'],
            'unit_price'       => ['required', 'numeric'],
            'issuer_id'        => ['required', 'exists:issuers,id'],
            'unit'             => ['nullable', 'string'],
        ];
    }
}
