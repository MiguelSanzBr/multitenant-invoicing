<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Billing\Client;
use App\Domain\Billing\ConcurrencyConflict;
use App\Domain\Billing\CrossTenantInvoices;
use App\Domain\Billing\Invoice;
use App\Domain\Billing\InvoiceRepository;
use App\Domain\Billing\InvoiceStatus;
use DateTimeImmutable;

final class InMemoryInvoices implements CrossTenantInvoices, InvoiceRepository
{
    /** @var array<int, Invoice> */
    public array $items = [];

    public function __construct(private int $companyId) {}

    public function find(int $id): ?Invoice
    {
        $invoice = $this->items[$id] ?? null;

        return $invoice?->companyId === $this->companyId ? $invoice : null;
    }

    public function findAcrossTenants(int $id): ?Invoice
    {
        return $this->items[$id] ?? null;
    }

    public function save(Invoice $invoice): Invoice
    {
        $id = $invoice->id ?? count($this->items) + 1;

        // mesmo locking otimista do repositório Eloquent
        if (isset($this->items[$id]) && $this->items[$id] !== $invoice && $this->items[$id]->version !== $invoice->version) {
            throw new ConcurrencyConflict;
        }

        return $this->items[$id] = new Invoice($id, $invoice->companyId, $invoice->clientId, $invoice->description,
            $invoice->amount, $invoice->dueDate, $invoice->status, $invoice->paidAt, $invoice->id === null ? 0 : $invoice->version + 1);
    }

    public function delete(Invoice $invoice): void
    {
        unset($this->items[$invoice->id]);
    }

    public function existsForClient(Client $client): bool
    {
        return array_any($this->items, fn (Invoice $i) => $i->clientId === $client->id && $i->companyId === $client->companyId);
    }

    public function markOpenPastDueAsOverdue(DateTimeImmutable $today): int
    {
        $late = array_filter($this->items, fn (Invoice $i) => $i->status === InvoiceStatus::Open && $i->dueDate->isBefore($today));
        array_walk($late, fn (Invoice $i) => $i->markOverdue($today));

        return count($late);
    }

    public function overdueIdChunks(int $size): iterable
    {
        $ids = array_keys(array_filter($this->items, fn (Invoice $i) => $i->status === InvoiceStatus::Overdue));

        return array_chunk($ids, $size);
    }
}
