<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Tenancy\TenantContext;

final class FixedTenant implements TenantContext
{
    public function __construct(private int $companyId) {}

    public function companyId(): int
    {
        return $this->companyId;
    }
}
