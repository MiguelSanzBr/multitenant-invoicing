<?php

declare(strict_types=1);

namespace App\Application\Reminders;

use App\Domain\Billing\CrossTenantClients;
use App\Domain\Billing\CrossTenantInvoices;
use App\Domain\Billing\InvoiceStatus;
use App\Domain\NotFound;
use App\Domain\Reminders\DeliveryResult;
use App\Domain\Reminders\IdempotencyKey;
use App\Domain\Reminders\ReminderAttempt;
use App\Domain\Reminders\ReminderLog;
use App\Domain\Reminders\ReminderSender;
use App\Domain\RuleViolation;
use DateTimeImmutable;

final readonly class SendInvoiceReminder
{
    public function __construct(
        private CrossTenantInvoices $invoices,
        private CrossTenantClients $clients,
        private ReminderSender $sender,
        private ReminderLog $log,
    ) {}

    /** Devolve null quando não tinha nada pra mandar. */
    public function __invoke(int $invoiceId, DateTimeImmutable $day): ?DeliveryResult
    {
        $invoice = $this->invoices->findAcrossTenants($invoiceId);

        // pagou ou foi editada entre enfileirar e rodar: não cobra
        if ($invoice?->status !== InvoiceStatus::Overdue) {
            return null;
        }

        // chave do dia em q foi enfileirado, não do dia em q rodou: retry de madrugada reusa a mesma
        $key = IdempotencyKey::forInvoice($invoiceId, $day);

        if ($this->log->alreadyDelivered($key)) {
            return null;
        }

        $client = $this->clients->findAcrossTenants($invoice->clientId) ?? throw new NotFound("Cliente {$invoice->clientId}");

        // o banco já recusa (FK composta), mas o job roda sem tenant e um SMS errado não tem volta
        if ($client->companyId !== $invoice->companyId) {
            throw new RuleViolation("Fatura $invoiceId aponta pra cliente de outra empresa.");
        }
        $body = sprintf('Olá %s, a fatura "%s" de %s venceu em %s.',
            $client->name, $invoice->description, $invoice->amount->format(), $invoice->dueDate->toBrazilian());

        $result = $this->sender->send($key, $client->phone, $body);
        $this->log->record(new ReminderAttempt($invoice->companyId, $invoiceId, $key, $result));

        return $result;
    }
}
