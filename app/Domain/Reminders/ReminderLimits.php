<?php

declare(strict_types=1);

namespace App\Domain\Reminders;

use App\Domain\InvalidValue;

/**
 * Teto anti-abuso do canal de SMS. O tenant não é confiável: um cliente cadastrado com o telefone
 * de outra pessoa não pode receber lembrete todo dia pra sempre, nem uma empresa gastar SMS sem limite.
 */
final readonly class ReminderLimits
{
    public function __construct(public int $perInvoice, public int $perCompanyPerDay)
    {
        if ($perInvoice < 1 || $perCompanyPerDay < 1) {
            throw new InvalidValue('Limite de lembrete precisa ser positivo.');
        }
    }
}
