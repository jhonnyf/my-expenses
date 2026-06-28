<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'password'         => ['required', 'string', 'confirmed', Password::min(8)],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required'         => 'A senha atual é obrigatória.',
            'current_password.current_password' => 'A senha atual está incorreta.',
            'password.required'                 => 'A nova senha é obrigatória.',
            'password.confirmed'                => 'A confirmação da nova senha não confere.',
            'password.min'                      => 'A nova senha deve ter pelo menos 8 caracteres.',
        ];
    }
}
