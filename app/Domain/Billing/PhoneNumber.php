<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Domain\InvalidValue;

final readonly class PhoneNumber
{
    public function __construct(public string $value)
    {
        if (preg_match('/^\+?\d{10,15}$/', $value) !== 1) {
            throw new InvalidValue("Telefone inválido: $value");
        }
    }
}
