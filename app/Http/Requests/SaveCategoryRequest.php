<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'color'    => ['nullable', 'string', 'max:7'],
            'keywords' => ['nullable', 'string'],
        ];
    }

    public function parsedKeywords(): array
    {
        return $this->input('keywords')
            ? array_map('trim', explode(',', $this->input('keywords')))
            : [];
    }
}
