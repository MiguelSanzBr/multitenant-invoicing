<?php

namespace App\Domain\Tenancy;

interface TenantContext
{
    /** Empresa do usuário autenticado. */
    public function companyId(): int;
}
