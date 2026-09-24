<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\FailsAsJson;
use Illuminate\Validation\Rule;

class ReportEmailRequest extends ReportFiltersRequest
{
    use FailsAsJson;

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'format' => ['required', Rule::in(['pdf', 'csv'])],
        ];
    }
}
