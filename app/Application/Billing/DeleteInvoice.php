<?php

namespace App\Application\Billing;

use App\Domain\Billing\InvoiceRepository;
use App\Domain\NotFound;

final readonly class DeleteInvoice
{
    public function __construct(private InvoiceRepository $invoices) {}

    public function __invoke(int $id): void
    {
        $this->invoices->delete($this->invoices->find($id) ?? throw new NotFound("Fatura $id"));
    }
}
