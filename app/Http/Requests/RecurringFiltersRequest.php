<?php

namespace App\Http\Requests;

use App\Services\RecurringPurchaseService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecurringFiltersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in([...RecurringPurchaseService::STATUSES, 'active', 'all'])],
            'sort' => ['nullable', Rule::in(RecurringPurchaseService::SORTS)],
            'q' => ['nullable', 'string', 'max:100'],
            'dismissed' => ['nullable', 'boolean'],
        ];
    }

    /** @return array{status: string, sort: string, q: string, dismissed: bool} */
    public function filters(): array
    {
        return [
            'status' => $this->validated('status') ?? 'active',
            'sort' => $this->validated('sort') ?? 'due',
            'q' => (string) ($this->validated('q') ?? ''),
            'dismissed' => $this->boolean('dismissed'),
        ];
    }
}
