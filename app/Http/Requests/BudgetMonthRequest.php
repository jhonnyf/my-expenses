<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BudgetMonthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Mês (Y-m) já iniciado: não há o que mostrar de um mês futuro.
            'month' => ['nullable', 'date_format:Y-m', 'before_or_equal:'.now()->format('Y-m'), 'after_or_equal:2000-01'],
        ];
    }

    public function month(): ?string
    {
        return $this->input('month') ?: null;
    }
}
