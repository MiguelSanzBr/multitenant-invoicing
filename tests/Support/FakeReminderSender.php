<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Billing\PhoneNumber;
use App\Domain\Reminders\DeliveryOutcome;
use App\Domain\Reminders\DeliveryResult;
use App\Domain\Reminders\IdempotencyKey;
use App\Domain\Reminders\ReminderSender;

final class FakeReminderSender implements ReminderSender
{
    /** @var list<array{key: string, recipient: string, body: string}> */
    public array $sent = [];

    public function __construct(public DeliveryResult $result = new DeliveryResult(DeliveryOutcome::Sent, 202)) {}

    public function send(IdempotencyKey $key, PhoneNumber $recipient, string $body): DeliveryResult
    {
        $this->sent[] = ['key' => $key->value, 'recipient' => $recipient->value, 'body' => $body];

        return $this->result;
    }
}
