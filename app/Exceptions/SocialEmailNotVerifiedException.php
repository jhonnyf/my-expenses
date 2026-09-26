<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * O provedor social não garantiu que o e-mail é de quem está entrando: vincular a uma conta existente
 * por esse e-mail entregaria a conta a quem só digitou o e-mail no provedor.
 */
class SocialEmailNotVerifiedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('O provedor não confirmou o e-mail informado.');
    }
}
