<?php

namespace App\Exceptions;

use Exception;

class ProFeatureRequiredException extends Exception
{
    public function __construct(private readonly ?string $feature = null)
    {
        parent::__construct(
            ($feature !== null ? config("plans.features.{$feature}.paywall") : null)
                ?? 'Este recurso é exclusivo do plano Pro.'
        );
    }

    public function feature(): ?string
    {
        return $this->feature;
    }
}
