<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListIssuersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['name', 'spent', 'visits', 'last'])],
            'city' => ['nullable', 'string', 'max:100'],
            'favorites' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array{q: string, sort: string, city: string, favorites: bool}
     */
    public function filters(): array
    {
        return [
            'q' => trim((string) $this->input('q')),
            'sort' => (string) $this->input('sort', 'name') ?: 'name',
            'city' => trim((string) $this->input('city')),
            'favorites' => $this->boolean('favorites'),
        ];
    }
}
