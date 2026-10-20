<?php

namespace App\Domain\Billing;

use InvalidArgumentException;

final readonly class PhoneNumber
{
    public function __construct(public string $value)
    {
        if (preg_match('/^\+?\d{10,15}$/', $value) !== 1) {
            throw new InvalidArgumentException("Telefone inválido: $value");
        }
    }
}
