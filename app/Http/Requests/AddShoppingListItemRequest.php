<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddShoppingListItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string'],
            'unit_price'  => ['required', 'numeric'],
            'issuer_id'   => ['required', 'integer', 'exists:issuers,id'],
            'quantity'    => ['required', 'integer', 'min:1'],
        ];
    }
}
