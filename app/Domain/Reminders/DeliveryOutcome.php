<?php

namespace App\Domain\Reminders;

enum DeliveryOutcome: string
{
    case Sent = 'sent';
    case AlreadySent = 'already_sent';
    case RetryLater = 'retry_later';
    case Rejected = 'rejected';

    public function isDelivered(): bool
    {
        return $this === self::Sent || $this === self::AlreadySent;
    }
}
