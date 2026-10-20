<?php

declare(strict_types=1);

namespace App\Application\Billing;

use App\Domain\Billing\ClientRepository;
use App\Domain\Billing\DueDate;
use App\Domain\Billing\Invoice;
use App\Domain\Billing\InvoiceRepository;
use App\Domain\Billing\InvoiceStatus;
use App\Domain\Billing\Money;
use App\Domain\NotFound;
use App\Domain\RuleViolation;
use DateTimeImmutable;

final readonly class UpdateInvoice
{
    public function __construct(private InvoiceRepository $invoices, private ClientRepository $clients) {}

    public function __invoke(int $id, InvoiceData $data, DateTimeImmutable $today): Invoice
    {
        $invoice = $this->invoices->find($id) ?? throw new NotFound("Fatura $id");
        $this->clients->find($data->clientId) ?? throw new NotFound("Cliente {$data->clientId}");

        $invoice->edit($data->clientId, $data->description, Money::brl($data->amountCents), DueDate::fromString($data->dueDate), $today);

        // status pedido vira a transição explícita; o q não tiver caminho no domínio é recusado
        match (true) {
            $data->status === null, $data->status === $invoice->status => null,
            $data->status === InvoiceStatus::Open => $invoice->open(),
            $data->status === InvoiceStatus::Draft => $invoice->revertToDraft(),
            default => throw new RuleViolation('Na edição a fatura só pode ir pra rascunho ou em aberto.'),
        };

        return $this->invoices->save($invoice);
    }
}
