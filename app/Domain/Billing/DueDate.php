<?php

namespace App\Domain\Billing;

use DateTimeImmutable;
use InvalidArgumentException;
use Stringable;

final readonly class DueDate implements Stringable
{
    private function __construct(private string $date) {}

    public static function fromString(string $date): self
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        // createFromFormat aceita 2026-02-30 e joga pra março, por isso compara de volta
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException("Vencimento inválido: $date");
        }

        return new self($date);
    }

    public function isBefore(DateTimeImmutable $day): bool
    {
        return $this->date < $day->format('Y-m-d');
    }

    public function __toString(): string
    {
        return $this->date;
    }
}
