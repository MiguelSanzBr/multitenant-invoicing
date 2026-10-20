<?php

declare(strict_types=1);

namespace App\Domain\Reminders;

use DateTimeImmutable;

interface ReminderLog
{
    public function alreadyDelivered(IdempotencyKey $key): bool;

    public function record(ReminderAttempt $attempt): void;

    /** Lembretes entregues da fatura, contando cada chave uma vez só. */
    public function deliveredCount(int $invoiceId): int;

    /** Lembretes entregues da empresa no dia, contando cada chave uma vez só. */
    public function deliveredOn(int $companyId, DateTimeImmutable $day): int;
}
