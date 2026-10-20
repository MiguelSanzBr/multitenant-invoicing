<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Models\Client;
use App\Infrastructure\Persistence\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('lista os clientes da empresa em ordem alfabética', function () {
    Client::factory()->for($this->user->company)->create(['name' => 'Bruno']);
    Client::factory()->for($this->user->company)->create(['name' => 'Ana']);

    $this->get(route('clients.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('clients/index')
            ->where('clients.data.0.name', 'Ana')
            ->where('clients.data.1.name', 'Bruno'));
});

it('mostra o formulário de criação e de edição', function () {
    $client = Client::factory()->for($this->user->company)->create();

    $this->get(route('clients.create'))
        ->assertInertia(fn (Assert $page) => $page->component('clients/form')->missing('client'));

    $this->get(route('clients.edit', $client))
        ->assertInertia(fn (Assert $page) => $page->component('clients/form')->where('client.id', $client->id));
});

it('cria cliente na empresa do usuário', function () {
    $this->post(route('clients.store'), ['name' => 'Ana', 'email' => 'ana@example.com', 'phone' => '+5511999990000'])
        ->assertRedirect(route('clients.index'));

    $this->assertDatabaseHas('clients', ['name' => 'Ana', 'company_id' => $this->user->company_id]);
});

it('valida o cliente', function (array $data, string $field) {
    $this->post(route('clients.store'), $data)->assertSessionHasErrors($field);
})->with([
    'sem nome' => [['phone' => '5511999990000'], 'name'],
    'sem telefone' => [['name' => 'Ana'], 'phone'],
    'telefone com letra' => [['name' => 'Ana', 'phone' => '11-abc'], 'phone'],
    'e-mail inválido' => [['name' => 'Ana', 'phone' => '5511999990000', 'email' => 'x'], 'email'],
]);

it('atualiza cliente', function () {
    $client = Client::factory()->for($this->user->company)->create();

    $this->put(route('clients.update', $client), ['name' => 'Novo', 'phone' => '5511988887777'])
        ->assertRedirect(route('clients.index'));

    expect($client->fresh()?->name)->toBe('Novo');
});

it('só o dono apaga cliente', function () {
    $client = Client::factory()->for($this->user->company)->create();

    $this->delete(route('clients.destroy', $client))->assertForbidden();
    $this->assertModelExists($client);

    $owner = User::factory()->owner()->for($this->user->company)->create();
    $this->actingAs($owner)->delete(route('clients.destroy', $client))->assertRedirect(route('clients.index'));
    $this->assertModelMissing($client);
});

it('visitante vai pro login', function () {
    auth()->logout();

    $this->get(route('clients.index'))->assertRedirect(route('login'));
});
