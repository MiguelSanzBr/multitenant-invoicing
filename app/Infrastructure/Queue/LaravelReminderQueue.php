<?php

declare(strict_types=1);

namespace App\Infrastructure\Queue;

use App\Domain\Reminders\ReminderQueue;
use DateTimeImmutable;

final class LaravelReminderQueue implements ReminderQueue
{
    public function push(array $invoiceIds, DateTimeImmutable $day): void
    {
        QueueInvoiceRemindersJob::dispatch($invoiceIds, $day->format('Y-m-d'));
    }
}
