<?php

namespace App\Domain\Billing;

use InvalidArgumentException;

final readonly class Money
{
    public function __construct(public int $cents, public string $currency = 'BRL')
    {
        if ($cents < 0) {
            throw new InvalidArgumentException('Valor negativo.');
        }

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException("Moeda inválida: $currency");
        }
    }

    public static function brl(int $cents): self
    {
        return new self($cents, 'BRL');
    }
}
