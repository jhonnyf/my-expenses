<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\FailsAsJson;
use Illuminate\Foundation\Http\FormRequest;

class DismissRecurringRequest extends FormRequest
{
    use FailsAsJson;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['description' => ['required', 'string', 'max:255']];
    }
}
