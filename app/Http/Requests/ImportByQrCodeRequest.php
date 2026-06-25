<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportByQrCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qrcode_url' => ['required', 'string', 'regex:/^https?:\/\/.+/i'],
        ];
    }
}
