<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Reminders\ReminderQueue;
use DateTimeImmutable;

final class SpyReminderQueue implements ReminderQueue
{
    /** @var list<array{list<int>, string}> */
    public array $pushed = [];

    public function push(array $invoiceIds, DateTimeImmutable $day): void
    {
        $this->pushed[] = [$invoiceIds, $day->format('Y-m-d')];
    }
}
