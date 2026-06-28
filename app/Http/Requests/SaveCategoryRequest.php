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
            'keywords' => ['nullable'],
        ];
    }

    public function parsedKeywords(): array
    {
        $raw = $this->input('keywords');

        if (!$raw) {
            return [];
        }

        if (is_array($raw)) {
            return array_map('trim', $raw);
        }

        return array_map('trim', explode(',', (string) $raw));
    }
}
