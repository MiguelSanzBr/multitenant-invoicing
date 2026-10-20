<?php

namespace App\Domain\Reminders;

use DateTimeImmutable;

interface ReminderQueue
{
    public function push(int $invoiceId, DateTimeImmutable $day): void;
}
