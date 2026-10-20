<?php

declare(strict_types=1);

// regra de dependência do hexagonal: domínio no centro, sem framework
arch('domínio não depende de framework nem de outra camada')
    ->expect('App\Domain')
    ->not->toUse(['Illuminate', 'Laravel', 'Inertia', 'Carbon', 'App\Application', 'App\Infrastructure', 'App\Http']);

arch('aplicação só conhece o domínio')
    ->expect('App\Application')
    ->not->toUse(['Illuminate', 'Laravel', 'Inertia', 'Carbon', 'App\Infrastructure', 'App\Http']);

const DOMAIN_PORTS = [
    'App\Domain\Billing\InvoiceRepository',
    'App\Domain\Billing\ClientRepository',
    'App\Domain\Reminders\ReminderSender',
    'App\Domain\Reminders\ReminderLog',
    'App\Domain\Reminders\ReminderQueue',
    'App\Domain\Tenancy\TenantContext',
];

arch('portas do domínio são interfaces')->expect(DOMAIN_PORTS)->toBeInterfaces();

// o arch() passa calado com nome q não existe, então confere a existência à parte
it('toda porta listada existe como interface', function (string $port) {
    expect(interface_exists($port))->toBeTrue("$port não é uma interface existente");
})->with(DOMAIN_PORTS);

arch('todo arquivo de app declara strict_types')->expect('App')->toUseStrictTypes();

// controller fala com caso de uso e model de leitura, nunca com adapter de saída direto
arch('http não usa os adapters de saída')
    ->expect('App\Http')
    ->not->toUse(['App\Infrastructure\Outbox', 'App\Infrastructure\Queue', 'App\Infrastructure\Tenancy', 'App\Infrastructure\Persistence\EloquentClientRepository', 'App\Infrastructure\Persistence\EloquentInvoiceRepository', 'App\Infrastructure\Persistence\EloquentReminderLog']);

arch('classes do domínio são finais, menos as exceções base')
    ->expect('App\Domain')
    ->classes()
    ->toBeFinal()
    ->ignoring(['App\Domain\NotFound', 'App\Domain\RuleViolation']);

arch()->preset()->php();
