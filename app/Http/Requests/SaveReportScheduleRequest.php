<?php

namespace App\Http\Requests;

use App\Enums\ReportFrequency;
use App\Http\Requests\Concerns\FailsAsJson;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveReportScheduleRequest extends FormRequest
{
    use FailsAsJson;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'frequency' => ['required', Rule::enum(ReportFrequency::class)],
            'format' => ['required', Rule::in(['pdf', 'csv'])],
        ];
    }
}
