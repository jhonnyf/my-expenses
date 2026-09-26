<?php

namespace App\Support;

use Illuminate\Support\Facades\RateLimiter;

/**
 * Limite de senhas erradas POR E-MAIL, somado ao `throttle:5,1` por IP das rotas de login.
 *
 * O limite por IP não segura quem espalha as tentativas por muitos IPs contra uma conta só; este segura,
 * contando só as falhas e zerando no primeiro acesso correto. Com a conta travada, até a senha certa é
 * recusada (senão o atacante continuaria adivinhando). O dono destrava sozinho pelo "esqueci a senha"
 * ou esperando: o custo de travar a conta de alguém é só esse.
 */
class LoginThrottle
{
    public const MAX_FAILURES = 10;

    public const WINDOW_SECONDS = 900;

    public function isLocked(string $email): bool
    {
        return RateLimiter::tooManyAttempts($this->key($email), self::MAX_FAILURES);
    }

    public function recordFailure(string $email): void
    {
        RateLimiter::hit($this->key($email), self::WINDOW_SECONDS);
    }

    public function reset(string $email): void
    {
        RateLimiter::clear($this->key($email));
    }

    public function message(string $email): string
    {
        $minutes = max(1, (int) ceil(RateLimiter::availableIn($this->key($email)) / 60));

        return "Muitas tentativas de login para este e-mail. Tente novamente em {$minutes} ".($minutes === 1 ? 'minuto' : 'minutos').' ou redefina a senha.';
    }

    private function key(string $email): string
    {
        return 'login-email:'.sha1(mb_strtolower(trim($email)));
    }
}
