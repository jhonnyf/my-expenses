<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AiSuggestItemCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_id' => ['required', 'integer', 'exists:invoices_items,id'],
        ];
    }
}
