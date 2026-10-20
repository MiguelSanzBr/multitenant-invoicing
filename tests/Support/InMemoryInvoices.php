<?php

namespace Tests\Support;

use App\Domain\Billing\Invoice;
use App\Domain\Billing\InvoiceRepository;

final class InMemoryInvoices implements InvoiceRepository
{
    /** @var array<int, Invoice> */
    public array $items = [];

    public function __construct(private int $companyId) {}

    public function find(int $id): ?Invoice
    {
        $invoice = $this->items[$id] ?? null;

        return $invoice?->companyId === $this->companyId ? $invoice : null;
    }

    public function save(Invoice $invoice): Invoice
    {
        $id = $invoice->id ?? count($this->items) + 1;

        return $this->items[$id] = new Invoice($id, $invoice->companyId, $invoice->clientId, $invoice->description,
            $invoice->amount, $invoice->dueDate, $invoice->status, $invoice->paidAt);
    }

    public function delete(Invoice $invoice): void
    {
        unset($this->items[$invoice->id]);
    }

    public function existsForClient(int $clientId): bool
    {
        return array_any($this->items, fn (Invoice $i) => $i->clientId === $clientId);
    }
}
