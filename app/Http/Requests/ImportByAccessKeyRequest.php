<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportByAccessKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'access_key' => preg_replace('/\D/', '', $this->input('access_key', '')),
        ]);
    }

    public function rules(): array
    {
        return [
            'access_key' => ['required', 'string', 'regex:/^\d{44}$/'],
        ];
    }
}
