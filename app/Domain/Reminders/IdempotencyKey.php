<?php

declare(strict_types=1);

namespace App\Domain\Reminders;

use DateTimeImmutable;

final readonly class IdempotencyKey
{
    private function __construct(public string $value) {}

    // um lembrete por fatura por dia: retry no mesmo dia reusa a chave e o gateway descarta o repetido
    public static function forInvoice(int $invoiceId, DateTimeImmutable $day): self
    {
        return new self('invoice-'.$invoiceId.'-'.$day->format('Y-m-d'));
    }
}
