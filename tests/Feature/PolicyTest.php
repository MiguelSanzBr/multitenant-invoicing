<?php

declare(strict_types=1);

use App\Domain\Billing\InvoiceStatus;
use App\Infrastructure\Persistence\Models\Client;
use App\Infrastructure\Persistence\Models\Invoice;
use App\Infrastructure\Persistence\Models\User;
use Illuminate\Support\Facades\Gate;

// quem pode o q, pelas duas portas (web e API). Só o dono exclui; membro faz o resto
beforeEach(function () {
    $this->owner = User::factory()->owner()->create();
    $this->member = User::factory()->for($this->owner->company)->create();
    $this->client = Client::factory()->for($this->owner->company)->create();
    $this->lonely = Client::factory()->for($this->owner->company)->create();
    $this->invoice = Invoice::factory()->for($this->client)->create(['status' => InvoiceStatus::Open]);
});

function act(string $role, string $channel, string $method, string $url, array $data = []): Illuminate\Testing\TestResponse
{
    $user = test()->{$role};

    if ($channel === 'api') {
        return test()->withToken($user->createToken('t')->plainTextToken)->json($method, "/api/v1$url", $data);
    }

    return test()->actingAs($user)->call($method, $url, $data);
}

function invoiceBody(): array
{
    return ['client_id' => test()->client->id, 'description' => 'Site', 'amount_cents' => 1000, 'due_date' => '2026-11-10', 'status' => 'open'];
}

it('membro não exclui cliente nem fatura, em nenhuma porta', function (string $channel, string $what) {
    $model = $what === 'clients' ? $this->lonely : $this->invoice;

    act('member', $channel, 'DELETE', "/$what/{$model->id}")->assertForbidden();

    $this->assertModelExists($model);
})->with(['web', 'api'])->with(['clients', 'invoices']);

it('dono exclui cliente e fatura, nas duas portas', function (string $channel, string $what) {
    $model = $what === 'clients' ? $this->lonely : $this->invoice;

    act('owner', $channel, 'DELETE', "/$what/{$model->id}")->assertStatus($channel === 'api' ? 204 : 302);

    $this->assertModelMissing($model);
})->with(['web', 'api'])->with(['clients', 'invoices']);

it('membro cria, edita e paga', function (string $channel) {
    $created = $channel === 'api' ? 201 : 302;
    $ok = $channel === 'api' ? 200 : 302;

    act('member', $channel, 'POST', '/clients', ['name' => 'Bia', 'phone' => '5511999990000'])->assertStatus($created);
    act('member', $channel, 'PUT', "/clients/{$this->client->id}", ['name' => 'Ana', 'phone' => '5511999990000'])->assertStatus($ok);
    act('member', $channel, 'POST', '/invoices', invoiceBody())->assertStatus($created);
    act('member', $channel, 'PUT', "/invoices/{$this->invoice->id}", invoiceBody())->assertStatus($ok);
    act('member', $channel, 'POST', "/invoices/{$this->invoice->id}/pay")->assertStatus($ok);

    expect($this->invoice->fresh()?->status)->toBe(InvoiceStatus::Paid);
})->with(['web', 'api']);

it('ver cliente usa a ability view da policy', function () {
    $theirs = Client::factory()->create();

    expect(Gate::forUser($this->member)->allows('view', $this->client))->toBeTrue()
        ->and(Gate::forUser($this->member)->denies('view', $theirs))->toBeTrue();
});
