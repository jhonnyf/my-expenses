<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\FailsAsJson;
use App\Http\Requests\Concerns\ParsesKeywords;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PreviewCategoryKeywordsRequest extends FormRequest
{
    use FailsAsJson;
    use ParsesKeywords;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['keywords' => ['nullable']];
    }

    public function after(): array
    {
        return [fn (Validator $validator) => $this->validateKeywordLimits($validator)];
    }
}
