<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportFiltersRequest extends FormRequest
{
    public const SORTS = ['recent', 'oldest', 'highest', 'lowest', 'name'];

    /** Valor do filtro de categoria para itens sem categoria. */
    public const NO_CATEGORY = 'none';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'issuer_id' => ['nullable', 'integer'],
            // Categoria do usuário/sistema, ou "none" (sem categoria): id alheio não é aceito.
            'category_id' => [
                'nullable',
                Rule::when($this->input('category_id') !== self::NO_CATEGORY, [
                    'integer',
                    Rule::exists('categories', 'id')->where(
                        fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $this->user()->id)
                    ),
                ]),
            ],
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(self::SORTS)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.exists' => 'Categoria inválida.',
            'end_date.after_or_equal' => 'A data final deve ser igual ou posterior à data inicial.',
        ];
    }

    /**
     * @return array{start_date: ?string, end_date: ?string, issuer_id: ?string, category_id: ?string, q: string, sort: string}
     */
    public function filters(): array
    {
        return [
            'start_date' => $this->input('start_date') ?: null,
            'end_date' => $this->input('end_date') ?: null,
            'issuer_id' => $this->filled('issuer_id') ? (string) $this->input('issuer_id') : null,
            'category_id' => $this->filled('category_id') ? (string) $this->input('category_id') : null,
            'q' => trim((string) $this->input('q')),
            'sort' => (string) ($this->input('sort') ?: 'recent'),
        ];
    }

    public function perPage(): ?int
    {
        return $this->filled('per_page') ? (int) $this->input('per_page') : null;
    }
}
