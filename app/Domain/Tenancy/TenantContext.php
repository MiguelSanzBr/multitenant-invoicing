<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

interface TenantContext
{
    /** Empresa do usuário autenticado. */
    public function companyId(): int;
}
