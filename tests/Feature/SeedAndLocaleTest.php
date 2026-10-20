<?php

use App\Domain\Billing\InvoiceStatus;
use App\Infrastructure\Persistence\Models\Invoice;
use App\Infrastructure\Persistence\Models\User;

it('seed cria duas empresas com dono e membro que conseguem entrar', function () {
    $this->seed();

    foreach (['dona@acme.test', 'membro@acme.test', 'dono@globex.test'] as $email) {
        $this->post(route('login.store'), ['email' => $email, 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs(User::where('email', $email)->sole());
        auth()->logout();
    }

    expect(User::where('email', 'dona@acme.test')->sole()->isOwner())->toBeTrue()
        ->and(Invoice::withoutGlobalScopes()->where('status', InvoiceStatus::Open)->where('due_date', '<', today())->exists())->toBeTrue();
});

it('mensagens de validação em português', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('clients.store'), [])
        ->assertSessionHasErrors(['name' => 'O campo nome é obrigatório.']);
});
