<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;

// usuário da empresa A tentando mexer no que é da empresa B: tem q dar 404 e nada muda
beforeEach(function () {
    $this->intruder = User::factory()->owner()->create();
    $this->victim = User::factory()->owner()->create();
    $this->client = Client::factory()->for($this->victim->company)->create(['name' => 'Original']);
    $this->invoice = Invoice::factory()->for($this->client)->create(['description' => 'Original']);
    $this->actingAs($this->intruder);
});

it('web: não vê cliente de outra empresa', function () {
    $this->get(route('clients.edit', $this->client))->assertNotFound();
});

it('web: não atualiza cliente de outra empresa', function () {
    $this->put(route('clients.update', $this->client), ['name' => 'Hack', 'phone' => '5511999990000'])
        ->assertNotFound();

    expect($this->client->fresh()?->name)->toBe('Original');
});

it('web: não apaga cliente de outra empresa', function () {
    $this->delete(route('clients.destroy', $this->client))->assertNotFound();

    $this->assertModelExists($this->client);
});

it('web: não lista cliente de outra empresa', function () {
    $this->get(route('clients.index'))
        ->assertInertia(fn ($page) => $page->has('clients.data', 0));
});

it('web: não vê fatura de outra empresa', function () {
    $this->get(route('invoices.edit', $this->invoice))->assertNotFound();
});

it('web: não atualiza nem paga fatura de outra empresa', function () {
    $mine = Client::factory()->for($this->intruder->company)->create();

    $this->put(route('invoices.update', $this->invoice), [
        'client_id' => $mine->id, 'description' => 'Hack', 'amount_cents' => 1, 'due_date' => '2026-01-01', 'status' => 'open',
    ])->assertNotFound();
    $this->post(route('invoices.pay', $this->invoice))->assertNotFound();

    $fresh = $this->invoice->fresh();
    expect($fresh?->description)->toBe('Original')
        ->and($fresh?->status)->toBe($this->invoice->status);
});

it('web: não apaga fatura de outra empresa', function () {
    $this->delete(route('invoices.destroy', $this->invoice))->assertNotFound();

    $this->assertModelExists($this->invoice);
});

it('web: não lista fatura de outra empresa', function () {
    $this->get(route('invoices.index'))
        ->assertInertia(fn ($page) => $page->has('invoices.data', 0));
});

it('web: não cria fatura apontando pra cliente de outra empresa', function () {
    $this->post(route('invoices.store'), [
        'client_id' => $this->client->id, 'description' => 'Hack', 'amount_cents' => 1, 'due_date' => '2026-01-01', 'status' => 'open',
    ])->assertSessionHasErrors('client_id');

    expect(Invoice::withoutGlobalScopes()->count())->toBe(1);
});
