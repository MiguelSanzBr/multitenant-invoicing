<?php

declare(strict_types=1);

use App\Domain\Billing\InvoiceStatus;
use App\Infrastructure\Persistence\Models\Client;
use App\Infrastructure\Persistence\Models\Invoice;
use App\Infrastructure\Persistence\Models\User;

beforeEach(function () {
    $this->user = User::factory()->owner()->create();
    $this->client = Client::factory()->for($this->user->company)->create(['name' => 'Ana']);
    $this->withToken($this->user->createToken('teste')->plainTextToken);
});

it('emite token com e-mail e senha', function () {
    $this->withToken('')->postJson('/api/v1/tokens', ['email' => $this->user->email, 'password' => 'password', 'device_name' => 'cli'])
        ->assertCreated()
        ->assertJsonStructure(['token']);
});

it('recusa token com senha errada', function () {
    $this->withToken('')->postJson('/api/v1/tokens', ['email' => $this->user->email, 'password' => 'errada', 'device_name' => 'cli'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('sem token responde 401', function () {
    $this->withToken('')->getJson('/api/v1/clients')->assertUnauthorized();
});

it('CRUD de clientes', function () {
    $this->getJson('/api/v1/clients')->assertOk()->assertJsonPath('data.0.name', 'Ana');

    $id = $this->postJson('/api/v1/clients', ['name' => 'Bia', 'phone' => '5511999990000'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Bia')
        ->json('data.id');

    $this->getJson("/api/v1/clients/$id")->assertOk()->assertJson(['data' => ['id' => $id, 'email' => null, 'phone' => '5511999990000']]);
    $this->putJson("/api/v1/clients/$id", ['name' => 'Bianca', 'phone' => '5511999990000'])->assertOk()->assertJsonPath('data.name', 'Bianca');
    $this->deleteJson("/api/v1/clients/$id")->assertNoContent();
    $this->getJson("/api/v1/clients/$id")->assertNotFound();
});

it('valida cliente e recusa apagar cliente com fatura', function () {
    $this->postJson('/api/v1/clients', ['name' => 'Bia'])->assertUnprocessable()->assertJsonValidationErrors('phone');

    Invoice::factory()->for($this->client)->create();
    $this->deleteJson("/api/v1/clients/{$this->client->id}")->assertConflict();
});

it('CRUD de faturas e pagamento', function () {
    $id = $this->postJson('/api/v1/invoices', [
        'client_id' => $this->client->id, 'description' => 'Site', 'amount_cents' => 150000,
        'due_date' => '2026-11-10', 'status' => 'open',
    ])->assertCreated()
        ->assertJson(['data' => ['amount_cents' => 150000, 'currency' => 'BRL', 'due_date' => '2026-11-10', 'status' => 'open', 'paid_at' => null]])
        ->json('data.id');

    $this->getJson('/api/v1/invoices')->assertOk()->assertJsonPath('data.0.id', $id);
    $this->getJson("/api/v1/invoices/$id")->assertOk()->assertJsonPath('data.description', 'Site');
    $this->putJson("/api/v1/invoices/$id", [
        'client_id' => $this->client->id, 'description' => 'Site novo', 'amount_cents' => 1000,
        'due_date' => '2026-11-10', 'status' => 'open',
    ])->assertOk()->assertJsonPath('data.amount_cents', 1000);
    $this->postJson("/api/v1/invoices/$id/pay")->assertOk()->assertJsonPath('data.status', 'paid');
    $this->postJson("/api/v1/invoices/$id/pay")->assertConflict()->assertJsonStructure(['message']);
    $this->deleteJson("/api/v1/invoices/$id")->assertNoContent();
});

it('membro não apaga pela API', function () {
    $member = User::factory()->for($this->user->company)->create();
    $invoice = Invoice::factory()->for($this->client)->create(['status' => InvoiceStatus::Open]);

    $this->withToken($member->createToken('t')->plainTextToken)
        ->deleteJson("/api/v1/invoices/{$invoice->id}")
        ->assertForbidden();
});

it('token expira em 30 dias', function () {
    $token = $this->user->createToken('curto')->plainTextToken;

    $this->travel(29)->days();
    $this->withToken($token)->getJson('/api/v1/clients')->assertOk();

    $this->travel(2)->days();
    app('auth')->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/clients')->assertUnauthorized();
});
