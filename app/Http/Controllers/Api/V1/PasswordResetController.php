<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordResetController extends Controller
{
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'O e-mail é obrigatório.',
            'email.email'    => 'Informe um e-mail válido.',
        ]);

        Password::broker()->sendResetLink($request->only('email'));

        // Sempre retorna sucesso para não revelar se o e-mail existe
        return response()->json(['message' => 'Se este e-mail estiver cadastrado, você receberá o link em breve.']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token'                 => ['required', 'string'],
            'email'                 => ['required', 'email'],
            'password'              => ['required', 'confirmed', PasswordRule::min(8)],
        ], [
            'token.required'             => 'O token é obrigatório.',
            'email.required'             => 'O e-mail é obrigatório.',
            'email.email'                => 'Informe um e-mail válido.',
            'password.required'          => 'A nova senha é obrigatória.',
            'password.confirmed'         => 'A confirmação de senha não confere.',
        ]);

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill(['password' => $password])->save();
                $user->tokens()->delete();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['message' => 'Senha redefinida com sucesso. Faça login novamente.']);
        }

        return $this->error(__($status), 422);
    }
}
