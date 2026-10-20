<?php

declare(strict_types=1);

namespace App\Domain\Reminders;

use DateTimeImmutable;

interface ReminderQueue
{
    public function push(int $invoiceId, DateTimeImmutable $day): void;
}
