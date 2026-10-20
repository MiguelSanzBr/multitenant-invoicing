<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use DateTimeImmutable;

/**
 * Faturas de todas as empresas, pra job e comando q rodam sem usuário logado.
 * Fica separado do InvoiceRepository pra caso de uso de tela não receber isso por engano (arch test trava).
 */
interface CrossTenantInvoices
{
    public function findAcrossTenants(int $id): ?Invoice;

    /** @return list<Invoice> em aberto com vencimento antes de $today */
    public function openPastDue(DateTimeImmutable $today): array;

    /** @return list<int> ids das vencidas */
    public function overdueIds(): array;
}
