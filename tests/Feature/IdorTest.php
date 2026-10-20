<?php

use App\Infrastructure\Persistence\Models\Client;
use App\Infrastructure\Persistence\Models\Invoice;
use App\Infrastructure\Persistence\Models\User;

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

it('web: não lista lembrete de outra empresa', function () {
    DB::table('reminders')->insert([
        'company_id' => $this->victim->company_id, 'invoice_id' => $this->invoice->id,
        'idempotency_key' => 'invoice-x', 'outcome' => 'sent',
    ]);

    $this->get(route('reminders.index'))->assertInertia(fn ($page) => $page->has('reminders.data', 0));
});

it('web: não cria fatura apontando pra cliente de outra empresa', function () {
    $this->post(route('invoices.store'), [
        'client_id' => $this->client->id, 'description' => 'Hack', 'amount_cents' => 1, 'due_date' => '2026-01-01', 'status' => 'open',
    ])->assertSessionHasErrors('client_id');

    expect(Invoice::withoutGlobalScopes()->count())->toBe(1);
});

describe('api', function () {
    beforeEach(function () {
        $this->withToken($this->intruder->createToken('t')->plainTextToken);
    });

    it('não vê nem lista cliente e fatura de outra empresa', function () {
        $this->getJson("/api/v1/clients/{$this->client->id}")->assertNotFound();
        $this->getJson("/api/v1/invoices/{$this->invoice->id}")->assertNotFound();
        $this->getJson('/api/v1/clients')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/invoices')->assertOk()->assertJsonCount(0, 'data');
    });

    it('não atualiza cliente nem fatura de outra empresa', function () {
        $mine = Client::factory()->for($this->intruder->company)->create();

        // controle: no próprio cliente funciona, então o 404 abaixo é do isolamento e não de rota faltando
        $this->putJson("/api/v1/clients/{$mine->id}", ['name' => 'Meu', 'phone' => '5511999990000'])->assertOk();
        $this->putJson("/api/v1/clients/{$this->client->id}", ['name' => 'Hack', 'phone' => '5511999990000'])->assertNotFound();
        $this->putJson("/api/v1/invoices/{$this->invoice->id}", [
            'client_id' => $mine->id, 'description' => 'Hack', 'amount_cents' => 1, 'due_date' => '2026-01-01', 'status' => 'open',
        ])->assertNotFound();
        $this->postJson("/api/v1/invoices/{$this->invoice->id}/pay")->assertNotFound();

        expect($this->client->fresh()?->name)->toBe('Original')
            ->and($this->invoice->fresh()?->description)->toBe('Original')
            ->and($this->invoice->fresh()?->status)->toBe($this->invoice->status);
    });

    it('não apaga cliente nem fatura de outra empresa', function () {
        $mine = Client::factory()->for($this->intruder->company)->create();
        $this->deleteJson("/api/v1/clients/{$mine->id}")->assertNoContent();

        $this->deleteJson("/api/v1/invoices/{$this->invoice->id}")->assertNotFound();
        $this->deleteJson("/api/v1/clients/{$this->client->id}")->assertNotFound();

        $this->assertModelExists($this->invoice);
        $this->assertModelExists($this->client);
    });

    it('não cria fatura pra cliente de outra empresa', function () {
        $this->postJson('/api/v1/invoices', [
            'client_id' => $this->client->id, 'description' => 'Hack', 'amount_cents' => 1, 'due_date' => '2026-01-01', 'status' => 'open',
        ])->assertUnprocessable()->assertJsonValidationErrors('client_id');
    });
});
