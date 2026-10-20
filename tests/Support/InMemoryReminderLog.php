<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Reminders\IdempotencyKey;
use App\Domain\Reminders\ReminderAttempt;
use App\Domain\Reminders\ReminderLog;
use DateTimeImmutable;

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

    public function deliveredCount(int $invoiceId): int
    {
        return $this->deliveredKeys(fn (ReminderAttempt $a) => $a->invoiceId === $invoiceId);
    }

    // sem relógio aqui: o dia sai da chave (invoice-{id}-{Y-m-d}), q é o dia em q foi enfileirado
    public function deliveredOn(int $companyId, DateTimeImmutable $day): int
    {
        return $this->deliveredKeys(fn (ReminderAttempt $a) => $a->companyId === $companyId && str_ends_with($a->key->value, $day->format('Y-m-d')));
    }

    /** @param callable(ReminderAttempt): bool $filter */
    private function deliveredKeys(callable $filter): int
    {
        $delivered = array_filter($this->attempts, fn (ReminderAttempt $a) => $a->result->outcome->isDelivered() && $filter($a));

        return count(array_unique(array_map(fn (ReminderAttempt $a) => $a->key->value, $delivered)));
    }
}
