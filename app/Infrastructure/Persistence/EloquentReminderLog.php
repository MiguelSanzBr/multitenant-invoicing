<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Reminders\DeliveryOutcome;
use App\Domain\Reminders\IdempotencyKey;
use App\Domain\Reminders\ReminderAttempt;
use App\Domain\Reminders\ReminderLog;
use App\Infrastructure\Persistence\Models\Reminder;
use App\Infrastructure\Persistence\Models\Scopes\CompanyScope;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;

final class EloquentReminderLog implements ReminderLog
{
    public function alreadyDelivered(IdempotencyKey $key): bool
    {
        return $this->delivered()->where('idempotency_key', $key->value)->exists();
    }

    public function deliveredCount(int $invoiceId): int
    {
        return $this->delivered()->where('invoice_id', $invoiceId)->distinct()->count('idempotency_key');
    }

    public function deliveredOn(int $companyId, DateTimeImmutable $day): int
    {
        $start = $day->setTime(0, 0);

        return $this->delivered()
            ->where('company_id', $companyId)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $start->modify('+1 day'))
            ->distinct()
            ->count('idempotency_key');
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

    /**
     * @return Builder<Reminder>
     */
    private function delivered(): Builder
    {
        return Reminder::withoutGlobalScope(CompanyScope::class)
            ->whereIn('outcome', [DeliveryOutcome::Sent, DeliveryOutcome::AlreadySent]);
    }
}
