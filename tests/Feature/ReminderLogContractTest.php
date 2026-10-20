<?php

declare(strict_types=1);

use App\Domain\Reminders\DeliveryOutcome;
use App\Domain\Reminders\DeliveryResult;
use App\Domain\Reminders\IdempotencyKey;
use App\Domain\Reminders\ReminderAttempt;
use App\Domain\Reminders\ReminderLog;
use App\Infrastructure\Persistence\EloquentReminderLog;
use App\Infrastructure\Persistence\Models\Invoice;
use Tests\Support\InMemoryReminderLog;

// o fake dos testes unitários tem q se comportar igual ao adapter de verdade
dataset('reminder logs', [
    'em memória' => fn () => new InMemoryReminderLog,
    'eloquent' => fn () => app(EloquentReminderLog::class),
]);

beforeEach(function () {
    $this->invoice = Invoice::factory()->create();
    $this->key = IdempotencyKey::forInvoice($this->invoice->id, new DateTimeImmutable('2026-10-20'));
});

function attempt(DeliveryOutcome $outcome, ?IdempotencyKey $key = null): ReminderAttempt
{
    return new ReminderAttempt(test()->invoice->company_id, test()->invoice->id, $key ?? test()->key, new DeliveryResult($outcome, 202));
}

it('entregue é sent ou already_sent, por chave', function (ReminderLog $log) {
    $log->record(attempt(DeliveryOutcome::Sent));

    expect($log->alreadyDelivered($this->key))->toBeTrue()
        ->and($log->alreadyDelivered(IdempotencyKey::forInvoice($this->invoice->id, new DateTimeImmutable('2026-10-21'))))->toBeFalse();
})->with('reminder logs');

it('already_sent também conta como entregue', function (ReminderLog $log) {
    $log->record(attempt(DeliveryOutcome::AlreadySent));

    expect($log->alreadyDelivered($this->key))->toBeTrue();
})->with('reminder logs');

it('retry e recusa não contam como entregue', function (ReminderLog $log) {
    $log->record(attempt(DeliveryOutcome::RetryLater));
    $log->record(attempt(DeliveryOutcome::Rejected));

    expect($log->alreadyDelivered($this->key))->toBeFalse();
})->with('reminder logs');
