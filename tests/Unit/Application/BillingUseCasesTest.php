<?php

declare(strict_types=1);

use App\Application\Billing\CreateInvoice;
use App\Application\Billing\DeleteClient;
use App\Application\Billing\DeleteInvoice;
use App\Application\Billing\InvoiceData;
use App\Application\Billing\MarkInvoicePaid;
use App\Application\Billing\SaveClient;
use App\Application\Billing\UpdateInvoice;
use App\Domain\Billing\Client;
use App\Domain\Billing\DueDate;
use App\Domain\Billing\Invoice;
use App\Domain\Billing\InvoiceStatus;
use App\Domain\Billing\Money;
use App\Domain\Billing\PhoneNumber;
use App\Domain\NotFound;
use App\Domain\RuleViolation;
use Tests\Support\FixedTenant;
use Tests\Support\InMemoryClients;
use Tests\Support\InMemoryInvoices;

// empresa 1 é a do usuário, empresa 2 é a vizinha
beforeEach(function () {
    $this->clients = new InMemoryClients(1);
    $this->invoices = new InMemoryInvoices(1);
    $this->mine = $this->clients->save(Client::create(1, 'Ana', null, new PhoneNumber('5511999990000')));
    $this->foreign = $this->clients->save(Client::create(2, 'Bia', null, new PhoneNumber('5511999990001')));
    $this->createInvoice = new CreateInvoice($this->invoices, $this->clients, new FixedTenant(1));
});

function data(int $clientId, array $overrides = []): InvoiceData
{
    return new InvoiceData(...[
        'clientId' => $clientId, 'description' => 'Site', 'amountCents' => 1000,
        'dueDate' => '2026-11-10', 'status' => InvoiceStatus::Open, ...$overrides,
    ]);
}

it('cria cliente na empresa do usuário e atualiza', function () {
    $save = new SaveClient($this->clients, new FixedTenant(1));

    $client = $save(null, 'Caio', 'c@example.com', '5511977776666');
    $save($client->id, 'Caio Lima', null, '5511977776666');

    expect($client->companyId)->toBe(1)
        ->and($this->clients->items[$client->id]->name)->toBe('Caio Lima');
});

it('não atualiza cliente de outra empresa', function () {
    (new SaveClient($this->clients, new FixedTenant(1)))($this->foreign->id, 'Hack', null, '5511977776666');
})->throws(NotFound::class);

it('apaga cliente sem fatura, recusa com fatura e não acha o de outra empresa', function () {
    $delete = new DeleteClient($this->clients, $this->invoices);
    ($this->createInvoice)(data($this->mine->id));
    $lonely = $this->clients->save(Client::create(1, 'Davi', null, new PhoneNumber('5511999990002')));

    $delete($lonely->id);

    expect($this->clients->items)->not->toHaveKey($lonely->id)
        ->and(fn () => $delete($this->mine->id))->toThrow(RuleViolation::class)
        ->and(fn () => $delete($this->foreign->id))->toThrow(NotFound::class);
});

it('cria fatura em centavos na empresa do usuário', function () {
    $invoice = ($this->createInvoice)(data($this->mine->id));

    expect($invoice->id)->toBe(1)
        ->and($invoice->companyId)->toBe(1)
        ->and($invoice->amount)->toEqual(Money::brl(1000))
        ->and((string) $invoice->dueDate)->toBe('2026-11-10')
        ->and($invoice->status)->toBe(InvoiceStatus::Open);
});

it('não cria nem move fatura pra cliente de outra empresa', function () {
    $invoice = ($this->createInvoice)(data($this->mine->id));
    $update = new UpdateInvoice($this->invoices, $this->clients);

    expect(fn () => ($this->createInvoice)(data($this->foreign->id)))->toThrow(NotFound::class)
        ->and(fn () => $update($invoice->id, data($this->foreign->id), new DateTimeImmutable))->toThrow(NotFound::class);
});

it('atualiza fatura e aplica a transição pedida', function (InvoiceStatus $from, ?InvoiceStatus $asked, InvoiceStatus $expected) {
    $invoice = ($this->createInvoice)(data($this->mine->id, ['status' => $from]));

    (new UpdateInvoice($this->invoices, $this->clients))($invoice->id, data($this->mine->id, ['description' => 'Outra', 'status' => $asked]), new DateTimeImmutable('2026-10-20'));

    expect($this->invoices->items[$invoice->id]->description)->toBe('Outra')
        ->and($this->invoices->items[$invoice->id]->status)->toBe($expected);
})->with([
    'em aberto pra rascunho' => [InvoiceStatus::Open, InvoiceStatus::Draft, InvoiceStatus::Draft],
    'rascunho abre' => [InvoiceStatus::Draft, InvoiceStatus::Open, InvoiceStatus::Open],
    'mesmo status' => [InvoiceStatus::Open, InvoiceStatus::Open, InvoiceStatus::Open],
    'sem status' => [InvoiceStatus::Draft, null, InvoiceStatus::Draft],
]);

it('edita vencida sem tirar ela de vencida e recusa transição q não existe', function () {
    $overdue = $this->invoices->save(new Invoice(null, 1, $this->mine->id, 'Site', Money::brl(1000), DueDate::fromString('2026-10-01'), InvoiceStatus::Overdue));
    $update = new UpdateInvoice($this->invoices, $this->clients);
    $today = new DateTimeImmutable('2026-10-20');

    $update($overdue->id, data($this->mine->id, ['description' => 'Site corrigido', 'dueDate' => '2026-10-01', 'status' => null]), $today);

    expect($this->invoices->items[$overdue->id]->status)->toBe(InvoiceStatus::Overdue)
        ->and($this->invoices->items[$overdue->id]->description)->toBe('Site corrigido')
        ->and(fn () => $update($overdue->id, data($this->mine->id, ['dueDate' => '2026-10-01', 'status' => InvoiceStatus::Draft]), $today))
        ->toThrow(RuleViolation::class)
        ->and(fn () => $update($overdue->id, data($this->mine->id, ['dueDate' => '2026-10-01', 'status' => InvoiceStatus::Paid]), $today))
        ->toThrow(RuleViolation::class, 'Na edição a fatura só pode ir pra rascunho ou em aberto.');
});

it('marca como paga e apaga', function () {
    $invoice = ($this->createInvoice)(data($this->mine->id));
    $now = new DateTimeImmutable('2026-10-20 10:00');

    (new MarkInvoicePaid($this->invoices))($invoice->id, $now);
    expect($this->invoices->items[$invoice->id]->paidAt)->toBe($now);

    (new DeleteInvoice($this->invoices))($invoice->id);
    expect($this->invoices->items)->toBeEmpty();
});

it('não acha fatura de outra empresa em nenhum caso de uso', function (string $useCase) {
    $theirs = $this->invoices->save(new Invoice(null, 2, $this->foreign->id, 'Dela', Money::brl(1), DueDate::fromString('2026-01-01'), InvoiceStatus::Open));

    match ($useCase) {
        'update' => (new UpdateInvoice($this->invoices, $this->clients))($theirs->id, data($this->mine->id), new DateTimeImmutable),
        'pay' => (new MarkInvoicePaid($this->invoices))($theirs->id, new DateTimeImmutable),
        'delete' => (new DeleteInvoice($this->invoices))($theirs->id),
    };
})->with(['update', 'pay', 'delete'])->throws(NotFound::class);
