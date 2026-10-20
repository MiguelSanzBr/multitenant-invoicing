<?php

namespace Tests\Support;

use App\Domain\Reminders\ReminderQueue;
use DateTimeImmutable;

final class SpyReminderQueue implements ReminderQueue
{
    /** @var list<array{int, string}> */
    public array $pushed = [];

    public function push(int $invoiceId, DateTimeImmutable $day): void
    {
        $this->pushed[] = [$invoiceId, $day->format('Y-m-d')];
    }
}
