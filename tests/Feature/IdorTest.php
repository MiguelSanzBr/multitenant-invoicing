<?php

use App\Models\Client;
use App\Models\User;

// usuário da empresa A tentando mexer no que é da empresa B: tem q dar 404 e nada muda
beforeEach(function () {
    $this->intruder = User::factory()->owner()->create();
    $this->victim = User::factory()->owner()->create();
    $this->client = Client::factory()->for($this->victim->company)->create(['name' => 'Original']);
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
