<?php

namespace App\Domain\Reminders;

interface ReminderLog
{
    public function alreadyDelivered(IdempotencyKey $key): bool;

    public function record(ReminderAttempt $attempt): void;
}
