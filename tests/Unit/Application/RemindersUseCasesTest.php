<?php

declare(strict_types=1);

use App\Application\Billing\MarkOverdueInvoices;
use App\Application\Reminders\SendInvoiceReminder;
use App\Domain\Billing\Client;
use App\Domain\Billing\DueDate;
use App\Domain\Billing\Invoice;
use App\Domain\Billing\InvoiceStatus;
use App\Domain\Billing\Money;
use App\Domain\Billing\PhoneNumber;
use App\Domain\InvalidValue;
use App\Domain\Reminders\DeliveryOutcome;
use App\Domain\Reminders\DeliveryResult;
use App\Domain\Reminders\ReminderLimits;
use App\Domain\RuleViolation;
use Tests\Support\FakeReminderSender;
use Tests\Support\InMemoryClients;
use Tests\Support\InMemoryInvoices;
use Tests\Support\InMemoryReminderLog;
use Tests\Support\SpyReminderQueue;

beforeEach(function () {
    // tenant 99 no find(): os casos de uso de lembrete têm q funcionar sem usuário, então usam findAcrossTenants
    $this->invoices = new InMemoryInvoices(99);
    $this->clients = new InMemoryClients(99);
    $this->sender = new FakeReminderSender;
    $this->log = new InMemoryReminderLog;
    $this->today = new DateTimeImmutable('2026-10-20 09:00');
    $this->limits = new ReminderLimits(perInvoice: 3, perCompanyPerDay: 200);
    $this->ana = $this->clients->save(Client::create(1, 'Ana', null, new PhoneNumber('5511999990000')));
});

function invoiceFor(Client $client, InvoiceStatus $status, string $due): Invoice
{
    return test()->invoices->save(new Invoice(null, $client->companyId, $client->id, 'Site', Money::brl(150000), DueDate::fromString($due), $status));
}

function sendReminder(int $invoiceId): ?DeliveryResult
{
    return (new SendInvoiceReminder(test()->invoices, test()->clients, test()->sender, test()->log, test()->limits))($invoiceId, test()->today);
}

it('vence as em aberto com vencimento no passado e enfileira lembrete de todas as vencidas', function () {
    $bia = $this->clients->save(Client::create(2, 'Bia', null, new PhoneNumber('5511999990001')));
    $late = invoiceFor($this->ana, InvoiceStatus::Open, '2026-10-19');
    $otherCompany = invoiceFor($bia, InvoiceStatus::Open, '2026-10-01');
    $alreadyOverdue = invoiceFor($this->ana, InvoiceStatus::Overdue, '2026-09-01');
    $dueToday = invoiceFor($this->ana, InvoiceStatus::Open, '2026-10-20');
    $draft = invoiceFor($this->ana, InvoiceStatus::Draft, '2026-01-01');
    $queue = new SpyReminderQueue;

    $marked = (new MarkOverdueInvoices($this->invoices, $queue, chunkSize: 2))($this->today);

    expect($marked)->toBe(2)
        ->and($this->invoices->items[$late->id]->status)->toBe(InvoiceStatus::Overdue)
        ->and($this->invoices->items[$otherCompany->id]->status)->toBe(InvoiceStatus::Overdue)
        ->and($this->invoices->items[$dueToday->id]->status)->toBe(InvoiceStatus::Open)
        ->and($this->invoices->items[$draft->id]->status)->toBe(InvoiceStatus::Draft)
        // em blocos: o comando enfileira um job por bloco, não um por fatura
        ->and($queue->pushed)->toBe([
            [[$late->id, $otherCompany->id], '2026-10-20'],
            [[$alreadyOverdue->id], '2026-10-20'],
        ]);
});

it('manda lembrete com chave de idempotência do dia e registra a tentativa', function () {
    $invoice = invoiceFor($this->ana, InvoiceStatus::Overdue, '2026-10-10');

    $result = sendReminder($invoice->id);

    expect($result?->outcome)->toBe(DeliveryOutcome::Sent)
        ->and($this->sender->sent)->toBe([[
            'key' => "invoice-{$invoice->id}-2026-10-20",
            'recipient' => '5511999990000',
            'body' => 'Olá Ana, a fatura "Site" de R$ 1.500,00 venceu em 10/10/2026.',
        ]])
        ->and($this->log->attempts)->toHaveCount(1)
        ->and($this->log->attempts[0]->companyId)->toBe(1)
        ->and($this->log->attempts[0]->invoiceId)->toBe($invoice->id)
        ->and($this->log->attempts[0]->key->value)->toBe("invoice-{$invoice->id}-2026-10-20");
});

it('não reenvia no mesmo dia depois de entregue', function (DeliveryOutcome $first) {
    $invoice = invoiceFor($this->ana, InvoiceStatus::Overdue, '2026-10-10');
    $this->sender->result = new DeliveryResult($first, 200);

    sendReminder($invoice->id);
    expect(sendReminder($invoice->id))->toBeNull()
        ->and($this->sender->sent)->toHaveCount(1);
})->with([DeliveryOutcome::Sent, DeliveryOutcome::AlreadySent]);

it('tenta de novo depois de falha temporária e registra cada tentativa', function () {
    $invoice = invoiceFor($this->ana, InvoiceStatus::Overdue, '2026-10-10');
    $this->sender->result = new DeliveryResult(DeliveryOutcome::RetryLater, 503, 'Service Unavailable');

    expect(sendReminder($invoice->id)?->outcome)->toBe(DeliveryOutcome::RetryLater);

    $this->sender->result = new DeliveryResult(DeliveryOutcome::Sent, 202);
    expect(sendReminder($invoice->id)?->outcome)->toBe(DeliveryOutcome::Sent)
        ->and($this->sender->sent)->toHaveCount(2)
        ->and($this->log->attempts[0]->result->error)->toBe('Service Unavailable');
});

it('não manda lembrete de fatura que não está vencida ou não existe', function (InvoiceStatus $status) {
    $invoice = invoiceFor($this->ana, $status, '2026-10-10');

    expect(sendReminder($invoice->id))->toBeNull()
        ->and(sendReminder(999))->toBeNull()
        ->and($this->sender->sent)->toBeEmpty();
})->with([InvoiceStatus::Paid, InvoiceStatus::Open, InvoiceStatus::Draft]);

it('não manda SMS pra cliente de outra empresa nem se o dado vier torto', function () {
    $bia = $this->clients->save(Client::create(2, 'Bia', null, new PhoneNumber('5511999990001')));
    // fatura da empresa 1 apontando pro cliente da empresa 2: o banco recusa, mas o caso de uso não confia
    $invoice = $this->invoices->save(new Invoice(null, 1, $bia->id, 'Site', Money::brl(150000), DueDate::fromString('2026-10-10'), InvoiceStatus::Overdue));

    expect(fn () => sendReminder($invoice->id))->toThrow(RuleViolation::class, "Fatura {$invoice->id} aponta pra cliente de outra empresa.")
        ->and($this->sender->sent)->toBeEmpty()
        ->and($this->log->attempts)->toBeEmpty();
});

it('para depois de 3 lembretes entregues da mesma fatura', function () {
    $invoice = invoiceFor($this->ana, InvoiceStatus::Overdue, '2026-10-10');

    foreach (['2026-10-20', '2026-10-21', '2026-10-22', '2026-10-23'] as $day) {
        $this->today = new DateTimeImmutable($day);
        $results[] = sendReminder($invoice->id)?->outcome;
    }

    expect($results)->toBe([DeliveryOutcome::Sent, DeliveryOutcome::Sent, DeliveryOutcome::Sent, null])
        ->and($this->sender->sent)->toHaveCount(3);
});

it('cota diária por empresa: passou do teto, o resto fica pro dia seguinte', function () {
    $this->limits = new ReminderLimits(perInvoice: 3, perCompanyPerDay: 2);
    $bia = $this->clients->save(Client::create(2, 'Bia', null, new PhoneNumber('5511999990001')));
    $mine = array_map(fn () => invoiceFor($this->ana, InvoiceStatus::Overdue, '2026-10-10'), range(1, 3));
    $theirs = invoiceFor($bia, InvoiceStatus::Overdue, '2026-10-10');

    $results = array_map(fn (Invoice $i) => sendReminder($i->id)?->outcome, [...$mine, $theirs]);

    expect($results)->toBe([DeliveryOutcome::Sent, DeliveryOutcome::Sent, null, DeliveryOutcome::Sent]);

    $this->today = new DateTimeImmutable('2026-10-21');
    expect(sendReminder($mine[2]->id)?->outcome)->toBe(DeliveryOutcome::Sent);
});

it('limites precisam ser positivos', function (int $perInvoice, int $perDay) {
    new ReminderLimits($perInvoice, $perDay);
})->with([[0, 1], [1, 0]])->throws(InvalidValue::class);
