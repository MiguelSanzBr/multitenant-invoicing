<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Reminders\IdempotencyKey;
use App\Domain\Reminders\ReminderAttempt;
use App\Domain\Reminders\ReminderLog;

final class InMemoryReminderLog implements ReminderLog
{
    /** @var list<ReminderAttempt> */
    public array $attempts = [];

    public function alreadyDelivered(IdempotencyKey $key): bool
    {
        return array_any($this->attempts, fn (ReminderAttempt $a) => $a->key->value === $key->value && $a->result->outcome->isDelivered());
    }

    public function record(ReminderAttempt $attempt): void
    {
        $this->attempts[] = $attempt;
    }
}
