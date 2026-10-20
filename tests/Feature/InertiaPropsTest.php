<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Models\Client;
use App\Infrastructure\Persistence\Models\Invoice;
use App\Infrastructure\Persistence\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

// o front recebe só o q o Resource lista: company_id, timestamps e coluna nova não vazam por padrão
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->client = Client::factory()->for($this->user->company)->create();
    $this->invoice = Invoice::factory()->for($this->client)->create();
    $this->actingAs($this->user);
});

$clientKeys = ['id', 'name', 'email', 'phone'];
$invoiceKeys = ['id', 'client_id', 'description', 'amount_cents', 'currency', 'due_date', 'status', 'paid_at'];

it('lista de clientes', function () use ($clientKeys) {
    $this->get(route('clients.index'))->assertInertia(fn (Assert $page) => $page
        ->has('clients.data.0', fn (Assert $row) => $row->hasAll($clientKeys))
        ->hasAll(['clients.current_page', 'clients.last_page', 'clients.prev_page_url', 'clients.next_page_url']));
});

it('form de cliente', function () use ($clientKeys) {
    $this->get(route('clients.edit', $this->client))->assertInertia(fn (Assert $page) => $page
        ->has('client', fn (Assert $client) => $client->hasAll($clientKeys)));
});

it('lista de faturas com o nome do cliente', function () use ($invoiceKeys) {
    $this->get(route('invoices.index'))->assertInertia(fn (Assert $page) => $page
        ->has('invoices.data.0', fn (Assert $row) => $row
            ->hasAll($invoiceKeys)
            ->has('client', fn (Assert $client) => $client->hasAll(['id', 'name']))));
});

it('form de fatura', function () use ($invoiceKeys) {
    $this->get(route('invoices.edit', $this->invoice))->assertInertia(fn (Assert $page) => $page
        ->has('invoice', fn (Assert $invoice) => $invoice->hasAll($invoiceKeys))
        ->has('clients.0', fn (Assert $client) => $client->hasAll(['id', 'name'])));
});

it('usuário compartilhado sem company_id nem timestamps', function () {
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('auth.user', fn (Assert $user) => $user->hasAll(['id', 'name', 'email', 'role'])));
});
