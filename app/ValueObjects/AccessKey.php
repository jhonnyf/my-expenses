<?php

namespace App\ValueObjects;

readonly class AccessKey
{
    public function __construct(public readonly string $value)
    {
        if (! preg_match('/^\d{44}$/', $value)) {
            throw new \InvalidArgumentException('Chave de acesso deve conter exatamente 44 dígitos.');
        }
    }

    public static function fromRaw(string $raw): self
    {
        return new self(preg_replace('/\D/', '', $raw));
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
