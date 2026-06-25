<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadXmlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'xml' => ['required', 'file', 'mimes:xml,text/xml', 'max:10240'],
        ];
    }
}
