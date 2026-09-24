<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\FailsAsJson;
use App\Http\Requests\Concerns\ParsesKeywords;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveCategoryRequest extends FormRequest
{
    use FailsAsJson;
    use ParsesKeywords;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')
                    ->where('user_id', $this->user()->id)
                    ->ignore($this->route('category')?->id),
            ],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'keywords' => ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Você já tem uma categoria com este nome.',
            'color.regex' => 'Use uma cor no formato #RRGGBB.',
        ];
    }

    public function after(): array
    {
        return [fn (Validator $validator) => $this->validateKeywordLimits($validator)];
    }
}
