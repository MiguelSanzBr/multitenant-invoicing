<?php

// regra de dependência do hexagonal: domínio no centro, sem framework
arch('domínio não depende de framework nem de outra camada')
    ->expect('App\Domain')
    ->not->toUse(['Illuminate', 'Laravel', 'Inertia', 'Carbon', 'App\Application', 'App\Infrastructure', 'App\Http']);

arch('aplicação só conhece o domínio')
    ->expect('App\Application')
    ->not->toUse(['Illuminate', 'Laravel', 'Inertia', 'Carbon', 'App\Infrastructure', 'App\Http']);

arch('portas do domínio são interfaces')
    ->expect(['App\Domain\Billing\InvoiceRepository', 'App\Domain\Billing\ClientRepository', 'App\Domain\Reminders\ReminderSender'])
    ->toBeInterfaces();
