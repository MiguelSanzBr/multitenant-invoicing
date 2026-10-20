<?php

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->client = Client::factory()->for($this->user->company)->create();
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

it('mostra o formulário com os clientes da empresa', function () {
    Client::factory()->create();
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

it('não edita fatura paga', function () {
    $invoice = Invoice::factory()->for($this->client)->paid()->create();

    $this->put(route('invoices.update', $invoice), invoiceData())->assertForbidden();
});

it('marca como paga', function () {
    $invoice = Invoice::factory()->for($this->client)->create(['status' => InvoiceStatus::Overdue]);

    $this->post(route('invoices.pay', $invoice))->assertRedirect();

    expect($invoice->fresh()?->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->fresh()?->paid_at)->not->toBeNull();
});

it('não paga rascunho nem fatura já paga', function (string $state) {
    $invoice = Invoice::factory()->for($this->client)->{$state}()->create();

    $this->post(route('invoices.pay', $invoice))->assertForbidden();
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
