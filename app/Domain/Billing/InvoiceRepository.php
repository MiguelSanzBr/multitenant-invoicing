<?php

namespace App\Domain\Billing;

interface InvoiceRepository
{
    /** Só enxerga a empresa do usuário atual. */
    public function find(int $id): ?Invoice;

    public function save(Invoice $invoice): Invoice;

    public function delete(Invoice $invoice): void;

    public function existsForClient(int $clientId): bool;
}
