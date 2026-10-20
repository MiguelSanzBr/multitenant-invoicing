<?php

declare(strict_types=1);

use App\Domain\Billing\InvoiceStatus;
use App\Infrastructure\Persistence\Models\Invoice;
use App\Infrastructure\Persistence\Models\User;
use Database\Factories\InvoiceFactory;
use Database\Seeders\DatabaseSeeder;

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

it('seed usa descrições de fatura em português', function () {
    $this->seed();

    $descriptions = Invoice::withoutGlobalScopes()->distinct()->pluck('description')->all();

    expect($descriptions)->not->toBeEmpty()
        ->and(array_diff($descriptions, InvoiceFactory::DESCRIPTIONS))->toBeEmpty();
});

it('seed de demo se recusa a rodar em produção', function () {
    app()->detectEnvironment(fn () => 'production');

    // direto no seeder: o db:seed em produção pararia antes, pedindo confirmação
    expect(fn () => app(DatabaseSeeder::class)->run())->toThrow(RuntimeException::class, 'Seed de demo (senha password) não roda em produção.')
        ->and(User::count())->toBe(0);
});
