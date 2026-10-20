<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Reminders\DeliveryOutcome;
use App\Domain\Reminders\IdempotencyKey;
use App\Domain\Reminders\ReminderAttempt;
use App\Domain\Reminders\ReminderLog;
use App\Infrastructure\Persistence\Models\Reminder;
use App\Infrastructure\Persistence\Models\Scopes\CompanyScope;

final class EloquentReminderLog implements ReminderLog
{
    public function alreadyDelivered(IdempotencyKey $key): bool
    {
        return Reminder::withoutGlobalScope(CompanyScope::class)
            ->where('idempotency_key', $key->value)
            ->whereIn('outcome', [DeliveryOutcome::Sent, DeliveryOutcome::AlreadySent])
            ->exists();
    }

    public function record(ReminderAttempt $attempt): void
    {
        (new Reminder)->forceFill([
            'company_id' => $attempt->companyId,
            'invoice_id' => $attempt->invoiceId,
            'idempotency_key' => $attempt->key->value,
            'outcome' => $attempt->result->outcome,
            'http_status' => $attempt->result->httpStatus,
            'error' => $attempt->result->error,
        ])->save();
    }
}
