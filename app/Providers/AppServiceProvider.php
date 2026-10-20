<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Billing\ClientRepository;
use App\Domain\Billing\CrossTenantClients;
use App\Domain\Billing\CrossTenantInvoices;
use App\Domain\Billing\InvoiceRepository;
use App\Domain\Reminders\ReminderLimits;
use App\Domain\Reminders\ReminderLog;
use App\Domain\Reminders\ReminderQueue;
use App\Domain\Reminders\ReminderSender;
use App\Domain\Tenancy\TenantContext;
use App\Http\Policies\ClientPolicy;
use App\Http\Policies\InvoicePolicy;
use App\Http\Policies\ReminderPolicy;
use App\Infrastructure\Outbox\OutboxReminderSender;
use App\Infrastructure\Persistence\EloquentClientRepository;
use App\Infrastructure\Persistence\EloquentInvoiceRepository;
use App\Infrastructure\Persistence\EloquentReminderLog;
use App\Infrastructure\Persistence\Models\Client;
use App\Infrastructure\Persistence\Models\Invoice;
use App\Infrastructure\Persistence\Models\Reminder;
use App\Infrastructure\Persistence\Models\User;
use App\Infrastructure\Queue\LaravelReminderQueue;
use App\Infrastructure\Tenancy\AuthTenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        CrossTenantClients::class => EloquentClientRepository::class,
        CrossTenantInvoices::class => EloquentInvoiceRepository::class,
        TenantContext::class => AuthTenantContext::class,
        ReminderSender::class => OutboxReminderSender::class,
        ReminderLog::class => EloquentReminderLog::class,
        ReminderQueue::class => LaravelReminderQueue::class,
    ];

    public function register(): void
    {
        $this->app->bind(ReminderLimits::class, fn () => new ReminderLimits(
            Config::integer('services.outbox.max_per_invoice'),
            Config::integer('services.outbox.max_per_company_per_day'),
        ));
    }

    public function boot(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(app()->isProduction());

        // API autenticada: 60 por minuto por usuário, pra token válido não raspar tudo nem martelar POST
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user() instanceof User ? 'user:'.$request->user()->id : 'ip:'.$request->ip()));

        // emissão de token: por IP segura password spraying, por e-mail segura stuffing vindo de vários IPs
        RateLimiter::for('api-token', fn (Request $request) => [
            Limit::perMinute(6)->by('ip:'.$request->ip()),
            Limit::perMinute(6)->by('email:'.mb_strtolower($request->string('email')->value())),
        ]);

        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Reminder::class, ReminderPolicy::class);
    }
}
