<?php

namespace App\Providers;

use App\Domain\Billing\ClientRepository;
use App\Domain\Billing\InvoiceRepository;
use App\Domain\Tenancy\TenantContext;
use App\Http\Policies\ClientPolicy;
use App\Http\Policies\InvoicePolicy;
use App\Infrastructure\Persistence\EloquentClientRepository;
use App\Infrastructure\Persistence\EloquentInvoiceRepository;
use App\Infrastructure\Persistence\Models\Client;
use App\Infrastructure\Persistence\Models\Invoice;
use App\Infrastructure\Tenancy\AuthTenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Portas do domínio ligadas aos adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        ClientRepository::class => EloquentClientRepository::class,
        InvoiceRepository::class => EloquentInvoiceRepository::class,
        TenantContext::class => AuthTenantContext::class,
    ];

    public function boot(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(app()->isProduction());

        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
    }
}
