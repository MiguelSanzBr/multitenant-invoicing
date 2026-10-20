<?php

declare(strict_types=1);

namespace App\Domain\Billing;

/** Clientes de todas as empresas, só pro job de lembrete (ver CrossTenantInvoices). */
interface CrossTenantClients
{
    public function findAcrossTenants(int $id): ?Client;
}
