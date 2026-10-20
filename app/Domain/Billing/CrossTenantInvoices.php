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

    /**
     * Marca como vencidas as em aberto com vencimento antes de $today e devolve quantas.
     * Mesma regra do Invoice::markOverdue, mas num UPDATE condicional: sem carregar a fatura
     * não tem janela pra sobrescrever um pagamento feito no meio do caminho.
     */
    public function markOpenPastDueAsOverdue(DateTimeImmutable $today): int;

    /**
     * Ids das vencidas em blocos por id, sem carregar tudo na memória.
     *
     * @return iterable<list<int>>
     */
    public function overdueIdChunks(int $size): iterable;
}
