<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Models\Client;
use App\Infrastructure\Persistence\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->user = User::factory()->owner()->create(['email' => 'dona@acme.test']);
    $this->client = Client::factory()->for($this->user->company)->create();
});

function issueToken(array $data = []): string
{
    return test()->postJson('/api/v1/tokens', ['email' => 'dona@acme.test', 'password' => 'password', 'device_name' => 'cli', ...$data])
        ->assertCreated()
        ->json('token');
}

function invoicePayload(): array
{
    return ['client_id' => test()->client->id, 'description' => 'Site', 'amount_cents' => 1000, 'due_date' => '2026-11-10', 'status' => 'open'];
}

it('token novo é só leitura por padrão', function () {
    $this->withToken(issueToken());

    $this->getJson('/api/v1/invoices')->assertOk();
    $this->getJson('/api/v1/clients')->assertOk();
    $this->postJson('/api/v1/invoices', invoicePayload())->assertForbidden();
    $this->postJson('/api/v1/clients', ['name' => 'Bia', 'phone' => '5511999990000'])->assertForbidden();
});

it('token de escrita só escreve no q pediu', function () {
    $this->withToken(issueToken(['abilities' => ['invoices:write']]));

    $this->postJson('/api/v1/invoices', invoicePayload())->assertCreated();
    $this->getJson('/api/v1/invoices')->assertOk();
    $this->getJson('/api/v1/clients')->assertForbidden();
    $this->deleteJson("/api/v1/clients/{$this->client->id}")->assertForbidden();
});

it('recusa ability q não existe', function () {
    $this->postJson('/api/v1/tokens', ['email' => 'dona@acme.test', 'password' => 'password', 'device_name' => 'cli', 'abilities' => ['*']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('abilities.0');
});

it('token tem prefixo pro secret scanning e validade gravada', function () {
    $this->travelTo(new DateTime('2026-10-20 09:00'));

    expect(issueToken())->toMatch('/^\d+\|mti_/')
        ->and($this->user->tokens()->sole()->expires_at?->toDateString())->toBe('2026-11-19');
});

it('lista os tokens do usuário sem o segredo', function () {
    issueToken(['device_name' => 'ci']);
    User::factory()->create()->createToken('de outra pessoa');
    $this->withToken(issueToken(['device_name' => 'notebook']));

    $this->getJson('/api/v1/tokens')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'ci')
        ->assertJsonPath('data.0.abilities', ['clients:read', 'invoices:read'])
        ->assertJsonMissingPath('data.0.token');
});

it('revoga o token atual', function () {
    $token = issueToken();

    $this->withToken($token)->deleteJson('/api/v1/tokens/current')->assertNoContent();
    app('auth')->forgetGuards();

    $this->withToken($token)->getJson('/api/v1/invoices')->assertUnauthorized();
});

it('e-mail com maiúscula entra igual ao login web', function () {
    expect(issueToken(['email' => 'Dona@Acme.test']))->toMatch('/^\d+\|mti_/');
});

it('usuário inexistente também paga o custo do hash, sem oráculo de tempo', function () {
    Hash::spy();

    $this->postJson('/api/v1/tokens', ['email' => 'ninguem@acme.test', 'password' => 'x', 'device_name' => 'cli'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => 'Credenciais inválidas.']);

    Hash::shouldHaveReceived('check')->once();
});

it('limita tentativas de token por e-mail mesmo trocando de IP', function () {
    foreach (range(1, 6) as $i) {
        $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.$i"])
            ->postJson('/api/v1/tokens', ['email' => 'dona@acme.test', 'password' => 'errada', 'device_name' => 'cli'])
            ->assertUnprocessable();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
        ->postJson('/api/v1/tokens', ['email' => 'dona@acme.test', 'password' => 'errada', 'device_name' => 'cli'])
        ->assertTooManyRequests();
});

it('limita tentativas de token por IP mesmo trocando de e-mail', function () {
    foreach (range(1, 6) as $i) {
        $this->postJson('/api/v1/tokens', ['email' => "x$i@acme.test", 'password' => 'errada', 'device_name' => 'cli'])->assertUnprocessable();
    }

    $this->postJson('/api/v1/tokens', ['email' => 'y@acme.test', 'password' => 'errada', 'device_name' => 'cli'])->assertTooManyRequests();
});

it('API autenticada tem limite de 60 pedidos por minuto por usuário', function () {
    $this->withToken($this->user->createToken('t')->plainTextToken);

    foreach (range(1, 60) as $_) {
        $this->getJson('/api/v1/clients')->assertOk();
    }

    $this->getJson('/api/v1/clients')->assertTooManyRequests();
});
