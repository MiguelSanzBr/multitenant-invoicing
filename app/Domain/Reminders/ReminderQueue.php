<?php

declare(strict_types=1);

namespace App\Domain\Reminders;

use DateTimeImmutable;

interface ReminderQueue
{
    /**
     * Um bloco de faturas por vez: quem enfileira não paga um INSERT por fatura.
     *
     * @param  list<int>  $invoiceIds
     */
    public function push(array $invoiceIds, DateTimeImmutable $day): void;
}
