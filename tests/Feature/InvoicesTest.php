<?php

declare(strict_types=1);

use App\Application\Billing\MarkInvoicePaid;
use App\Domain\Billing\ConcurrencyConflict;
use App\Domain\Billing\CrossTenantInvoices;
use App\Domain\Billing\DueDate;
use App\Domain\Billing\InvoiceRepository;
use App\Domain\Billing\InvoiceStatus;
use App\Domain\Billing\Money;
use App\Infrastructure\Persistence\Models\Client;
use App\Infrastructure\Persistence\Models\Invoice;
use App\Infrastructure\Persistence\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->client = Client::factory()->for($this->user->company)->create();
    Client::factory()->create(); // de outra empresa, criado antes do login pq o trait força a empresa do usuário logado
    $this->actingAs($this->user);
});

function invoiceData(array $overrides = []): array
{
    return [
        'client_id' => test()->client->id,
        'description' => 'Consultoria outubro',
        'amount_cents' => 150000,
        'due_date' => '2026-11-10',
        'status' => 'open',
        ...$overrides,
    ];
}

it('lista as faturas com o cliente', function () {
    Invoice::factory()->for($this->client)->create(['due_date' => '2026-10-01']);
    Invoice::factory()->for($this->client)->create(['due_date' => '2026-12-01']);

    $this->get(route('invoices.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('invoices/index')
            ->where('invoices.data.0.due_date', '2026-12-01')
            ->where('invoices.data.0.client.name', $this->client->name)
            ->has('invoices.data', 2));
});

it('mostra o formulário só com os clientes da empresa', function () {
    $invoice = Invoice::factory()->for($this->client)->create();

    $this->get(route('invoices.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('invoices/form')
            ->missing('invoice')
            ->where('clients', [['id' => $this->client->id, 'name' => $this->client->name]]));

    $this->get(route('invoices.edit', $invoice))
        ->assertInertia(fn (Assert $page) => $page->where('invoice.id', $invoice->id));
});

it('cria fatura em centavos e BRL', function () {
    $this->post(route('invoices.store'), invoiceData())->assertRedirect(route('invoices.index'));

    $invoice = Invoice::sole();
    expect($invoice->amount_cents)->toBe(150000)
        ->and($invoice->currency)->toBe('BRL')
        ->and($invoice->status)->toBe(InvoiceStatus::Open)
        ->and($invoice->company_id)->toBe($this->user->company_id)
        ->and($invoice->due_date->toDateString())->toBe('2026-11-10');
});

it('valida a fatura', function (array $data, string $field) {
    $this->post(route('invoices.store'), invoiceData($data))->assertSessionHasErrors($field);
})->with([
    'sem cliente' => [['client_id' => null], 'client_id'],
    'valor quebrado' => [['amount_cents' => 10.5], 'amount_cents'],
    'valor zero' => [['amount_cents' => 0], 'amount_cents'],
    'sem vencimento' => [['due_date' => 'x'], 'due_date'],
    'status paga pelo form' => [['status' => 'paid'], 'status'],
]);

it('atualiza fatura', function () {
    $invoice = Invoice::factory()->for($this->client)->create();

    $this->put(route('invoices.update', $invoice), invoiceData(['description' => 'Outra']))
        ->assertRedirect(route('invoices.index'));

    expect($invoice->fresh()?->description)->toBe('Outra');
});

it('edita fatura vencida sem mandar status e ela continua vencida', function () {
    $invoice = Invoice::factory()->for($this->client)->create(['status' => InvoiceStatus::Overdue, 'due_date' => '2026-10-01']);
    $this->travelTo(new DateTime('2026-10-20 10:00'));

    $data = invoiceData(['description' => 'Typo corrigido', 'due_date' => '2026-10-01']);
    unset($data['status']);
    $this->put(route('invoices.update', $invoice), $data)->assertRedirect(route('invoices.index'));

    expect($invoice->fresh()?->status)->toBe(InvoiceStatus::Overdue)
        ->and($invoice->fresh()?->description)->toBe('Typo corrigido');
});

it('vencida com vencimento renegociado volta pra em aberto', function () {
    $invoice = Invoice::factory()->for($this->client)->create(['status' => InvoiceStatus::Overdue, 'due_date' => '2026-10-01']);
    $this->travelTo(new DateTime('2026-10-20 10:00'));

    $data = invoiceData(['due_date' => '2026-11-15']);
    unset($data['status']);
    $this->put(route('invoices.update', $invoice), $data)->assertRedirect(route('invoices.index'));

    expect($invoice->fresh()?->status)->toBe(InvoiceStatus::Open);
});

it('não manda vencida pra rascunho pela edição', function () {
    $invoice = Invoice::factory()->for($this->client)->create(['status' => InvoiceStatus::Overdue, 'due_date' => '2026-10-01']);

    $this->put(route('invoices.update', $invoice), invoiceData(['due_date' => '2026-10-01', 'status' => 'draft']))->assertRedirect();

    expect($invoice->fresh()?->status)->toBe(InvoiceStatus::Overdue);
});

it('status é obrigatório só na criação', function () {
    $data = invoiceData();
    unset($data['status']);

    $this->post(route('invoices.store'), $data)->assertSessionHasErrors('status');
});

it('pagamento entre carregar e salvar a edição não é desfeito', function () {
    $invoice = Invoice::factory()->for($this->client)->create(['status' => InvoiceStatus::Open]);
    $repository = app(InvoiceRepository::class);
    $stale = $repository->find($invoice->id);

    app(MarkInvoicePaid::class)($invoice->id, now());
    $stale?->edit($this->client->id, 'Editada', Money::brl(100), DueDate::fromString('2026-12-01'), now());

    expect(fn () => $repository->save($stale))->toThrow(ConcurrencyConflict::class)
        ->and($invoice->fresh()?->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->fresh()?->description)->toBe($invoice->description);
});

it('fatura marcada como vencida pelo comando invalida a cópia carregada antes', function () {
    $invoice = Invoice::factory()->for($this->client)->create(['status' => InvoiceStatus::Open, 'due_date' => '2026-10-01']);
    $repository = app(InvoiceRepository::class);
    $stale = $repository->find($invoice->id);

    app(CrossTenantInvoices::class)->markOpenPastDueAsOverdue(new DateTimeImmutable('2026-10-20'));
    $stale?->revertToDraft();

    expect(fn () => $repository->save($stale))->toThrow(ConcurrencyConflict::class)
        ->and($invoice->fresh()?->status)->toBe(InvoiceStatus::Overdue);
});

it('edição concorrente vira aviso pro usuário recarregar', function () {
    $invoice = Invoice::factory()->for($this->client)->create(['status' => InvoiceStatus::Open]);
    $repository = app(InvoiceRepository::class);
    $first = $repository->find($invoice->id);
    $second = $repository->find($invoice->id);

    $first?->edit($this->client->id, 'Primeira', Money::brl(100), DueDate::fromString('2026-12-01'), now());
    $saved = $repository->save($first);
    $second?->edit($this->client->id, 'Segunda', Money::brl(100), DueDate::fromString('2026-12-01'), now());

    expect($saved->version)->toBe($first->version + 1)
        ->and(fn () => $repository->save($second))->toThrow(ConcurrencyConflict::class, 'A fatura mudou enquanto você mexia nela. Recarregue a página e tente de novo.')
        ->and($invoice->fresh()?->description)->toBe('Primeira');
});

it('não edita fatura paga', function () {
    $invoice = Invoice::factory()->for($this->client)->paid()->create();

    $this->put(route('invoices.update', $invoice), invoiceData(['description' => 'Outra']))->assertRedirect();

    expect($invoice->fresh()?->description)->toBe($invoice->description);
});

it('marca como paga', function () {
    $invoice = Invoice::factory()->for($this->client)->create(['status' => InvoiceStatus::Overdue]);

    $this->post(route('invoices.pay', $invoice))->assertRedirect();

    expect($invoice->fresh()?->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->fresh()?->paid_at)->not->toBeNull();
});

it('não paga rascunho nem fatura já paga', function (string $state) {
    $invoice = Invoice::factory()->for($this->client)->{$state}()->create();

    $this->post(route('invoices.pay', $invoice))->assertRedirect();

    expect($invoice->fresh()?->status)->toBe($invoice->status);
})->with(['draft', 'paid']);

it('só o dono apaga fatura', function () {
    $invoice = Invoice::factory()->for($this->client)->create();

    $this->delete(route('invoices.destroy', $invoice))->assertForbidden();

    $owner = User::factory()->owner()->for($this->user->company)->create();
    $this->actingAs($owner)->delete(route('invoices.destroy', $invoice))->assertRedirect(route('invoices.index'));
    $this->assertModelMissing($invoice);
});

it('não apaga cliente que tem fatura', function () {
    Invoice::factory()->for($this->client)->create();
    $owner = User::factory()->owner()->for($this->user->company)->create();

    $this->actingAs($owner)->delete(route('clients.destroy', $this->client))->assertRedirect();

    $this->assertModelExists($this->client);
});
