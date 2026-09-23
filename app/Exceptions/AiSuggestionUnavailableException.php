<?php

namespace App\Exceptions;

use RuntimeException;

class AiSuggestionUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('A sugestão por IA está indisponível no momento. Tente novamente em instantes.');
    }
}
