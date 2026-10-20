<?php

namespace App\Domain\Reminders;

use App\Domain\Billing\PhoneNumber;

interface ReminderSender
{
    public function send(IdempotencyKey $key, PhoneNumber $recipient, string $body): DeliveryResult;
}
