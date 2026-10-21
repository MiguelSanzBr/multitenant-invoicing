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
    $this->travelTo(new DateTime('2026-10-20 09:00'));
    $this->invoice = Invoice::factory()->create();
    $this->key = IdempotencyKey::forInvoice($this->invoice->id, new DateTimeImmutable('2026-10-20'));
});

function attempt(DeliveryOutcome $outcome, ?IdempotencyKey $key = null, ?Invoice $invoice = null): ReminderAttempt
{
    $invoice ??= test()->invoice;

    return new ReminderAttempt($invoice->company_id, $invoice->id, $key ?? test()->key, new DeliveryResult($outcome, 202));
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

it('conta lembretes entregues por fatura, uma vez por chave', function (ReminderLog $log) {
    $day = fn (string $d) => IdempotencyKey::forInvoice($this->invoice->id, new DateTimeImmutable($d));
    $log->record(attempt(DeliveryOutcome::RetryLater));
    $log->record(attempt(DeliveryOutcome::Sent));
    $log->record(attempt(DeliveryOutcome::AlreadySent)); // mesma chave, não é outro lembrete
    $log->record(attempt(DeliveryOutcome::Sent, $day('2026-10-21')));
    $log->record(attempt(DeliveryOutcome::Rejected, $day('2026-10-22')));

    expect($log->deliveredCount($this->invoice->id))->toBe(2)
        ->and($log->deliveredCount($this->invoice->id + 1000))->toBe(0);
})->with('reminder logs');

it('conta lembretes entregues da empresa no dia', function (ReminderLog $log) {
    $sibling = Invoice::factory()->create(['client_id' => $this->invoice->client_id]);
    $other = Invoice::factory()->create();
    $today = new DateTimeImmutable('2026-10-20');
    $log->record(attempt(DeliveryOutcome::Sent));
    $log->record(attempt(DeliveryOutcome::Sent, IdempotencyKey::forInvoice($sibling->id, $today), $sibling));
    $log->record(attempt(DeliveryOutcome::RetryLater, IdempotencyKey::forInvoice($sibling->id, $today), $sibling));
    $log->record(attempt(DeliveryOutcome::Sent, IdempotencyKey::forInvoice($other->id, $today), $other));

    expect($log->deliveredOn($this->invoice->company_id, $today))->toBe(2)
        ->and($log->deliveredOn($this->invoice->company_id, new DateTimeImmutable('2026-10-21')))->toBe(0)
        ->and($log->deliveredOn($other->company_id, $today))->toBe(1);
})->with('reminder logs');

it('eloquent: o dia da cota vai da meia-noite à meia-noite, qualquer q seja a hora pedida', function () {
    $log = app(EloquentReminderLog::class);
    $at = function (string $when, string $day) use ($log) {
        $this->travelTo(new DateTime($when));
        $log->record(attempt(DeliveryOutcome::Sent, IdempotencyKey::forInvoice($this->invoice->id, new DateTimeImmutable($day))));
    };
    $at('2026-10-19 23:59:30', '2026-10-19');
    $at('2026-10-20 00:00:30', '2026-10-20');
    $at('2026-10-20 23:59:30', '2026-10-21');
    $at('2026-10-21 00:00:30', '2026-10-22');

    $count = fn (string $day) => $log->deliveredOn($this->invoice->company_id, new DateTimeImmutable($day));

    expect($count('2026-10-20 15:30'))->toBe(2)
        ->and($count('2026-10-19'))->toBe(1)
        ->and($count('2026-10-21 23:00'))->toBe(1);
});
