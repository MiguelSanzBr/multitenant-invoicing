<?php

declare(strict_types=1);

namespace App\Application\Billing;

use App\Domain\Billing\CrossTenantInvoices;
use App\Domain\Reminders\ReminderQueue;
use DateTimeImmutable;

final readonly class MarkOverdueInvoices
{
    public function __construct(
        private CrossTenantInvoices $invoices,
        private ReminderQueue $reminders,
        private int $chunkSize = 500,
    ) {}

    /** Roda sem usuário logado, por isso lê pelas portas q atravessam empresas. */
    public function __invoke(DateTimeImmutable $today): int
    {
        $marked = $this->invoices->markOpenPastDueAsOverdue($today);

        // um lembrete por dia pra cada vencida, inclusive as q já venciam antes, enfileirado em blocos
        foreach ($this->invoices->overdueIdChunks($this->chunkSize) as $ids) {
            $this->reminders->push($ids, $today);
        }

        return $marked;
    }
}
