<?php

declare(strict_types=1);

namespace App\Application\Billing;

use App\Domain\Billing\CrossTenantInvoices;
use App\Domain\Reminders\ReminderQueue;
use DateTimeImmutable;

final readonly class MarkOverdueInvoices
{
    public function __construct(private CrossTenantInvoices $invoices, private ReminderQueue $reminders) {}

    /** Roda sem usuário logado, por isso lê pelas portas q atravessam empresas. */
    public function __invoke(DateTimeImmutable $today): int
    {
        $marked = $this->invoices->markOpenPastDueAsOverdue($today);

        // um lembrete por dia pra cada vencida, inclusive as q já venciam antes
        foreach ($this->invoices->overdueIds() as $id) {
            $this->reminders->push($id, $today);
        }

        return $marked;
    }
}
