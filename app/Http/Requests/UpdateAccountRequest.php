<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : null,
            'email' => is_string($this->input('email')) ? mb_strtolower(trim($this->input('email'))) : null,
            'cidade' => is_string($this->input('cidade')) ? trim($this->input('cidade')) : null,
            'estado' => is_string($this->input('estado')) ? strtoupper(trim($this->input('estado'))) : null,
        ], fn ($value) => $value !== null));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->user()?->id),
            ],
            'cidade' => ['nullable', 'string', 'max:255'],
            'estado' => ['nullable', 'string', Rule::in(array_keys(config('brazilian-states')))],
            // Trocar o e-mail (dado de acesso à conta) exige confirmar a senha; conta social não tem senha.
            'current_password' => [
                Rule::requiredIf(fn () => $this->user()?->password !== null && $this->emailIsChanging()),
                'nullable',
                'string',
                'current_password',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome é obrigatório.',
            'name.min' => 'O nome deve ter pelo menos 2 caracteres.',
            'name.max' => 'O nome não pode ter mais de 255 caracteres.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'Informe um endereço de e-mail válido.',
            'email.max' => 'O e-mail não pode ter mais de 255 caracteres.',
            'email.unique' => 'Este e-mail já está em uso por outra conta.',
            'estado.in' => 'Informe uma sigla de estado (UF) válida.',
            'current_password.required' => 'Informe sua senha atual para trocar o e-mail.',
            'current_password.current_password' => 'A senha atual está incorreta.',
        ];
    }

    private function emailIsChanging(): bool
    {
        return mb_strtolower((string) $this->input('email')) !== mb_strtolower((string) $this->user()->email);
    }
}
