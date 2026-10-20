<?php

namespace App\Application\Billing;

use App\Domain\Billing\Invoice;
use App\Domain\Billing\InvoiceRepository;
use App\Domain\NotFound;
use DateTimeImmutable;

final readonly class MarkInvoicePaid
{
    public function __construct(private InvoiceRepository $invoices) {}

    public function __invoke(int $id, DateTimeImmutable $now): Invoice
    {
        $invoice = $this->invoices->find($id) ?? throw new NotFound("Fatura $id");
        $invoice->markPaid($now);

        return $this->invoices->save($invoice);
    }
}
