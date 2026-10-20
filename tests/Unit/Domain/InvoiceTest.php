<?php

declare(strict_types=1);

use App\Domain\Billing\Client;
use App\Domain\Billing\DueDate;
use App\Domain\Billing\Invoice;
use App\Domain\Billing\InvoiceStatus;
use App\Domain\Billing\Money;
use App\Domain\Billing\PhoneNumber;
use App\Domain\RuleViolation;

function newInvoice(InvoiceStatus $status = InvoiceStatus::Draft, string $due = '2026-10-10'): Invoice
{
    return Invoice::create(1, 2, 'Site', Money::brl(1000), DueDate::fromString($due), $status);
}

it('fatura nova nasce rascunho ou em aberto', function () {
    expect(newInvoice()->status)->toBe(InvoiceStatus::Draft)
        ->and(newInvoice()->id)->toBeNull()
        ->and(newInvoice(InvoiceStatus::Open)->status)->toBe(InvoiceStatus::Open);
});

it('fatura nova não nasce paga nem vencida', function (InvoiceStatus $status) {
    newInvoice($status);
})->with([InvoiceStatus::Paid, InvoiceStatus::Overdue])->throws(RuleViolation::class);

it('fatura exige descrição e valor positivo', function (string $description, int $cents) {
    Invoice::create(1, 2, $description, Money::brl($cents), DueDate::fromString('2026-10-10'), InvoiceStatus::Draft);
})->with([[' ', 100], ['Site', 0]])->throws(RuleViolation::class);

it('rascunho abre, mas só uma vez', function () {
    $invoice = newInvoice();
    $invoice->open();

    expect($invoice->status)->toBe(InvoiceStatus::Open);
    expect(fn () => $invoice->open())->toThrow(RuleViolation::class);
});

it('paga fatura em aberto ou vencida', function () {
    $at = new DateTimeImmutable('2026-10-20 10:00');
    $open = newInvoice(InvoiceStatus::Open);
    $overdue = newInvoice(InvoiceStatus::Open);
    $overdue->markOverdue(new DateTimeImmutable('2026-10-20'));

    $open->markPaid($at);
    $overdue->markPaid($at);

    expect($open->status)->toBe(InvoiceStatus::Paid)
        ->and($open->paidAt)->toBe($at)
        ->and($overdue->status)->toBe(InvoiceStatus::Paid);
});

it('não paga rascunho nem paga duas vezes', function () {
    $draft = newInvoice();
    $paid = newInvoice(InvoiceStatus::Open);
    $paid->markPaid(new DateTimeImmutable);

    expect(fn () => $draft->markPaid(new DateTimeImmutable))->toThrow(RuleViolation::class)
        ->and(fn () => $paid->markPaid(new DateTimeImmutable))->toThrow(RuleViolation::class);
});

it('só vence fatura em aberto com vencimento no passado', function () {
    $invoice = newInvoice(InvoiceStatus::Open, '2026-10-19');

    expect(fn () => $invoice->markOverdue(new DateTimeImmutable('2026-10-19')))->toThrow(RuleViolation::class)
        ->and(fn () => newInvoice()->markOverdue(new DateTimeImmutable('2027-01-01')))->toThrow(RuleViolation::class);

    $invoice->markOverdue(new DateTimeImmutable('2026-10-20'));
    expect($invoice->status)->toBe(InvoiceStatus::Overdue);
});

it('edita fatura não paga', function () {
    $invoice = newInvoice(InvoiceStatus::Open);
    $invoice->markOverdue(new DateTimeImmutable('2026-10-20'));

    $invoice->edit(3, 'Outra', Money::brl(5000), DueDate::fromString('2026-12-01'), InvoiceStatus::Open);

    expect($invoice->clientId)->toBe(3)
        ->and($invoice->description)->toBe('Outra')
        ->and($invoice->amount->cents)->toBe(5000)
        ->and((string) $invoice->dueDate)->toBe('2026-12-01')
        ->and($invoice->status)->toBe(InvoiceStatus::Open);
});

it('edição não mexe em fatura paga nem marca paga', function () {
    $paid = newInvoice(InvoiceStatus::Open);
    $paid->markPaid(new DateTimeImmutable);
    $due = DueDate::fromString('2026-12-01');

    expect(fn () => $paid->edit(2, 'x', Money::brl(1), $due, InvoiceStatus::Open))->toThrow(RuleViolation::class)
        ->and(fn () => newInvoice()->edit(2, 'x', Money::brl(1), $due, InvoiceStatus::Paid))->toThrow(RuleViolation::class)
        ->and(fn () => newInvoice()->edit(2, '', Money::brl(1), $due, InvoiceStatus::Open))->toThrow(RuleViolation::class);
});

it('cliente exige nome e atualiza dados', function () {
    $client = Client::create(1, 'Ana', null, new PhoneNumber('5511999990000'));
    $client->update('Ana Maria', 'ana@example.com', new PhoneNumber('5511988887777'));

    expect($client->id)->toBeNull()
        ->and($client->companyId)->toBe(1)
        ->and($client->name)->toBe('Ana Maria')
        ->and($client->email)->toBe('ana@example.com')
        ->and($client->phone->value)->toBe('5511988887777');
    expect(fn () => $client->update(' ', null, $client->phone))->toThrow(RuleViolation::class);
});
