<?php

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
