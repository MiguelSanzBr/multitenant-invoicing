<?php

declare(strict_types=1);

namespace App\Application\Billing;

use App\Domain\Billing\InvoiceRepository;
use App\Domain\Reminders\ReminderQueue;
use DateTimeImmutable;

final readonly class MarkOverdueInvoices
{
    public function __construct(private InvoiceRepository $invoices, private ReminderQueue $reminders) {}

    /** Roda sem usuário logado, por isso só usa os métodos que atravessam empresas. */
    public function __invoke(DateTimeImmutable $today): int
    {
        $marked = 0;

        foreach ($this->invoices->openPastDue($today) as $invoice) {
            $invoice->markOverdue($today);
            $this->invoices->save($invoice);
            $marked++;
        }

        // um lembrete por dia pra cada vencida, inclusive as q já venciam antes
        foreach ($this->invoices->overdueIds() as $id) {
            $this->reminders->push($id, $today);
        }

        return $marked;
    }
}
