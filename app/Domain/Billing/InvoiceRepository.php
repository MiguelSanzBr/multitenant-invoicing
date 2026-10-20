<?php

namespace App\Domain\Billing;

use DateTimeImmutable;

interface InvoiceRepository
{
    /** Só enxerga a empresa do usuário atual. */
    public function find(int $id): ?Invoice;

    /** Sem filtro de empresa, pra job e comando. O nome é longo de propósito, pra chamar atenção no review. */
    public function findAcrossTenants(int $id): ?Invoice;

    public function save(Invoice $invoice): Invoice;

    public function delete(Invoice $invoice): void;

    public function existsForClient(int $clientId): bool;

    /** @return list<Invoice> em aberto com vencimento antes de $today, de todas as empresas */
    public function openPastDue(DateTimeImmutable $today): array;

    /** @return list<int> ids das vencidas de todas as empresas */
    public function overdueIds(): array;
}
