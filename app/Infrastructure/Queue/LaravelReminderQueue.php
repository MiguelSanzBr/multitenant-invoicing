<?php

namespace App\Infrastructure\Queue;

use App\Domain\Reminders\ReminderQueue;
use DateTimeImmutable;

final class LaravelReminderQueue implements ReminderQueue
{
    public function push(int $invoiceId, DateTimeImmutable $day): void
    {
        SendInvoiceReminderJob::dispatch($invoiceId, $day->format('Y-m-d'));
    }
}
