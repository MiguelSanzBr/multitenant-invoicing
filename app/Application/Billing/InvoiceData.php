<?php

declare(strict_types=1);

namespace App\Application\Billing;

use App\Domain\Billing\InvoiceStatus;

final readonly class InvoiceData
{
    public function __construct(
        public int $clientId,
        public string $description,
        public int $amountCents,
        public string $dueDate,
        public InvoiceStatus $status,
    ) {}
}
