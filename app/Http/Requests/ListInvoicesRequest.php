<?php

namespace App\Http\Requests;

use App\Enums\InvoiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListInvoicesRequest extends FormRequest
{
    public const SORTS = ['recent', 'oldest', 'highest', 'lowest'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'issuer_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(InvoiceStatus::class)],
            'sort' => ['nullable', Rule::in(self::SORTS)],
        ];
    }

    /**
     * @return array{search: string, start_date: ?string, end_date: ?string, issuer_id: ?int, status: ?string, sort: string}
     */
    public function filters(): array
    {
        return [
            'search' => trim((string) $this->input('search')),
            'start_date' => $this->input('start_date') ?: null,
            'end_date' => $this->input('end_date') ?: null,
            'issuer_id' => $this->filled('issuer_id') ? (int) $this->input('issuer_id') : null,
            'status' => $this->input('status') ?: null,
            'sort' => (string) ($this->input('sort') ?: 'recent'),
        ];
    }
}
