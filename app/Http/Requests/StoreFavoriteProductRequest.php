<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFavoriteProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'canonical_name' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:50'],
        ];
    }
}
