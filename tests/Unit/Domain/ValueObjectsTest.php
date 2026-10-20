<?php

use App\Domain\Billing\DueDate;
use App\Domain\Billing\Money;
use App\Domain\Billing\PhoneNumber;
use App\Domain\Reminders\DeliveryOutcome;
use App\Domain\Reminders\IdempotencyKey;

it('Money guarda centavos inteiros e moeda', function () {
    $money = Money::brl(150000);

    expect($money->cents)->toBe(150000)
        ->and($money->currency)->toBe('BRL')
        ->and(new Money(10, 'USD')->currency)->toBe('USD');
});

it('Money rejeita valor negativo e moeda inválida', function (int $cents, string $currency) {
    new Money($cents, $currency);
})->with([[-1, 'BRL'], [10, 'real']])->throws(InvalidArgumentException::class);

it('DueDate aceita só Y-m-d válido', function () {
    expect((string) DueDate::fromString('2026-11-10'))->toBe('2026-11-10');
});

it('DueDate rejeita data inválida', function (string $date) {
    DueDate::fromString($date);
})->with(['10/11/2026', '2026-02-30', ''])->throws(InvalidArgumentException::class);

it('DueDate só está vencida a partir do dia seguinte', function () {
    $due = DueDate::fromString('2026-10-20');

    expect($due->isBefore(new DateTimeImmutable('2026-10-20 23:59')))->toBeFalse()
        ->and($due->isBefore(new DateTimeImmutable('2026-10-21 00:00')))->toBeTrue();
});

it('PhoneNumber aceita só dígitos com DDI', function () {
    expect(new PhoneNumber('+5511999990000')->value)->toBe('+5511999990000');
});

it('PhoneNumber rejeita lixo', function (string $phone) {
    new PhoneNumber($phone);
})->with(['11-abc', '123', ''])->throws(InvalidArgumentException::class);

it('IdempotencyKey é por fatura e por dia', function () {
    expect(IdempotencyKey::forInvoice(42, new DateTimeImmutable('2026-10-20 23:59'))->value)
        ->toBe('invoice-42-2026-10-20');
});

it('entregue é enviado ou já enviado antes', function () {
    expect(DeliveryOutcome::Sent->isDelivered())->toBeTrue()
        ->and(DeliveryOutcome::AlreadySent->isDelivered())->toBeTrue()
        ->and(DeliveryOutcome::RetryLater->isDelivered())->toBeFalse()
        ->and(DeliveryOutcome::Rejected->isDelivered())->toBeFalse();
});
