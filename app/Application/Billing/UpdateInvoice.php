<?php

declare(strict_types=1);

namespace App\Application\Billing;

use App\Domain\Billing\ClientRepository;
use App\Domain\Billing\DueDate;
use App\Domain\Billing\Invoice;
use App\Domain\Billing\InvoiceRepository;
use App\Domain\Billing\Money;
use App\Domain\NotFound;

final readonly class UpdateInvoice
{
    public function __construct(private InvoiceRepository $invoices, private ClientRepository $clients) {}

    public function __invoke(int $id, InvoiceData $data): Invoice
    {
        $invoice = $this->invoices->find($id) ?? throw new NotFound("Fatura $id");
        $this->clients->find($data->clientId) ?? throw new NotFound("Cliente {$data->clientId}");

        $invoice->edit($data->clientId, $data->description, Money::brl($data->amountCents), DueDate::fromString($data->dueDate), $data->status);

        return $this->invoices->save($invoice);
    }
}
