<?php

declare(strict_types=1);

namespace App\Domain\Billing;

interface InvoiceRepository
{
    /** Só enxerga a empresa do usuário atual. */
    public function find(int $id): ?Invoice;

    public function save(Invoice $invoice): Invoice;

    public function delete(Invoice $invoice): void;

    /** Empresa vem do próprio cliente, não do usuário logado: sem usuário a regra não pode falhar aberta. */
    public function existsForClient(Client $client): bool;
}
