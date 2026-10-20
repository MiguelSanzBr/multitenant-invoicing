<?php

declare(strict_types=1);

namespace App\Domain\Reminders;

final readonly class ReminderAttempt
{
    public function __construct(
        public int $companyId,
        public int $invoiceId,
        public IdempotencyKey $key,
        public DeliveryResult $result,
    ) {}
}
